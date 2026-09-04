#!/usr/bin/env bash
#
# Provisioning for Migraine Map on Azure.
# Creates: resource group, serverless Azure SQL (free offer), a consumption-only
# Container Apps environment, and the container app itself (scale-to-zero).
#
# Prerequisites:
#   - az CLI logged in:  az login
#   - az extension add --name containerapp --upgrade
#   - A container image already pushed to ghcr.io (see .github/workflows/deploy.yml,
#     or push one manually first) and the ghcr package set to Public.
#
# Re-running is safe: existing resources are reused, never replaced. The SQL server
# is discovered from the resource group, the database and container app are left
# alone if they exist, and APP_KEY / secrets / data are preserved. Only the image
# and non-secret env vars are refreshed.
#
# Ingress is created behind Easy Auth in "deny everything" mode, so the URL is never
# publicly readable. Configure the Microsoft identity provider afterwards —
# see deploy/README.md section "Easy Auth".

set -euo pipefail

# ----------------------------------------------------------------------------
# Config — edit these
# ----------------------------------------------------------------------------
LOCATION="uksouth"
RESOURCE_GROUP="rg-migraine-map"

# Leave SQL_SERVER empty to reuse the server already in the resource group, or to
# generate a globally unique name on a first-time provision. Set it explicitly to
# pin a specific server.
SQL_SERVER="${SQL_SERVER:-}"
SQL_DB="migraine_map"
SQL_ADMIN_USER="mmadmin"
SQL_ADMIN_PASSWORD="${SQL_ADMIN_PASSWORD:?Set SQL_ADMIN_PASSWORD env var to a strong password}"

ACA_ENV="migraine-map-env"
ACA_APP="migraine-map"
IMAGE="ghcr.io/rheannemcintosh/migraine-map:latest"

# ----------------------------------------------------------------------------
echo "==> Resource group"
az group create --name "$RESOURCE_GROUP" --location "$LOCATION" --output none

echo "==> Azure SQL logical server"
if [[ -z "$SQL_SERVER" ]]; then
  # Not `mapfile` — macOS ships bash 3.2, which predates it (added in bash 4.0).
  existing_servers=()
  while IFS= read -r server_name; do
    [[ -n "$server_name" ]] && existing_servers+=("$server_name")
  done < <(az sql server list -g "$RESOURCE_GROUP" --query "[].name" -o tsv)
  case "${#existing_servers[@]}" in
    0) SQL_SERVER="migraine-map-sql-$RANDOM" ;;
    1) SQL_SERVER="${existing_servers[0]}" ;;
    *)
      echo "ERROR: $RESOURCE_GROUP contains several SQL servers (${existing_servers[*]})." >&2
      echo "       Re-run with SQL_SERVER=<name> so the right one is reused." >&2
      exit 1
      ;;
  esac
fi

if az sql server show -g "$RESOURCE_GROUP" -n "$SQL_SERVER" --output none 2>/dev/null; then
  echo "    reusing $SQL_SERVER"
else
  echo "    creating $SQL_SERVER — record this name, reruns need it"
  az sql server create \
    --name "$SQL_SERVER" \
    --resource-group "$RESOURCE_GROUP" \
    --location "$LOCATION" \
    --admin-user "$SQL_ADMIN_USER" \
    --admin-password "$SQL_ADMIN_PASSWORD" \
    --output none
fi

echo "==> Allow other Azure services (incl. Container Apps) to reach the server"
az sql server firewall-rule create \
  --resource-group "$RESOURCE_GROUP" \
  --server "$SQL_SERVER" \
  --name AllowAzureServices \
  --start-ip-address 0.0.0.0 --end-ip-address 0.0.0.0 \
  --output none

echo "==> Serverless database on the free offer (auto-pauses, stops if free limit hit)"
if az sql db show -g "$RESOURCE_GROUP" -s "$SQL_SERVER" -n "$SQL_DB" --output none 2>/dev/null; then
  echo "    reusing $SQL_DB — existing data left untouched"
else
  az sql db create \
    --resource-group "$RESOURCE_GROUP" \
    --server "$SQL_SERVER" \
    --name "$SQL_DB" \
    --edition GeneralPurpose \
    --compute-model Serverless \
    --family Gen5 \
    --capacity 2 \
    --min-capacity 0.5 \
    --auto-pause-delay 60 \
    --backup-storage-redundancy Local \
    --use-free-limit \
    --free-limit-exhaustion-behavior AutoPause \
    --output none
fi

SQL_FQDN="$(az sql server show -g "$RESOURCE_GROUP" -n "$SQL_SERVER" --query fullyQualifiedDomainName -o tsv)"

echo "==> Container Apps environment (consumption-only, no Log Analytics = no log cost)"
if az containerapp env show -g "$RESOURCE_GROUP" -n "$ACA_ENV" --output none 2>/dev/null; then
  echo "    reusing $ACA_ENV"
else
  az containerapp env create \
    --name "$ACA_ENV" \
    --resource-group "$RESOURCE_GROUP" \
    --location "$LOCATION" \
    --logs-destination none \
    --output none
fi

ENV_VARS=(
  APP_NAME="Migraine Map"
  APP_ENV=production
  APP_DEBUG=false
  LOG_CHANNEL=stderr
  APP_KEY=secretref:app-key
  DB_CONNECTION=sqlsrv
  DB_HOST="$SQL_FQDN"
  DB_PORT=1433
  DB_DATABASE="$SQL_DB"
  DB_USERNAME="$SQL_ADMIN_USER"
  DB_PASSWORD=secretref:db-password
  DB_ENCRYPT=yes
  DB_TRUST_SERVER_CERTIFICATE=false
  DB_LOGIN_TIMEOUT=60
  SESSION_DRIVER=cookie
  SESSION_SECURE_COOKIE=true
  CACHE_STORE=file
  QUEUE_CONNECTION=sync
)

if az containerapp show -g "$RESOURCE_GROUP" -n "$ACA_APP" --output none 2>/dev/null; then
  FIRST_PROVISION=false
  echo "==> Container app exists — refreshing image and env vars (APP_KEY secret kept)"
  az containerapp update \
    --name "$ACA_APP" \
    --resource-group "$RESOURCE_GROUP" \
    --image "$IMAGE" \
    --set-env-vars "${ENV_VARS[@]}" \
    --output none
  az containerapp secret set \
    --name "$ACA_APP" \
    --resource-group "$RESOURCE_GROUP" \
    --secrets "db-password=$SQL_ADMIN_PASSWORD" \
    --output none
else
  FIRST_PROVISION=true
  # Generated once, on the very first provision. A rerun never reaches this branch,
  # so sessions and encrypted payloads survive.
  APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"

  echo "==> Container app (scale-to-zero)"
  az containerapp create \
    --name "$ACA_APP" \
    --resource-group "$RESOURCE_GROUP" \
    --environment "$ACA_ENV" \
    --image "$IMAGE" \
    --target-port 8080 \
    --ingress external \
    --min-replicas 0 \
    --max-replicas 1 \
    --cpu 0.5 --memory 1.0Gi \
    --secrets "app-key=$APP_KEY" "db-password=$SQL_ADMIN_PASSWORD" \
    --env-vars "${ENV_VARS[@]}" \
    --output none

  echo "==> Deny all unauthenticated traffic until an identity provider is configured"
  az containerapp auth update \
    --name "$ACA_APP" \
    --resource-group "$RESOURCE_GROUP" \
    --enabled true \
    --unauthenticated-client-action Return403 \
    --require-https true \
    --proxy-convention Standard \
    --output none
fi

FQDN="$(az containerapp show -g "$RESOURCE_GROUP" -n "$ACA_APP" --query properties.configuration.ingress.fqdn -o tsv)"

echo "==> Set APP_URL to the real FQDN"
az containerapp update \
  --name "$ACA_APP" \
  --resource-group "$RESOURCE_GROUP" \
  --set-env-vars "APP_URL=https://$FQDN" \
  --output none

echo "==> Monthly \$1 budget alert"
SUB_ID="$(az account show --query id -o tsv)"
az consumption budget create \
  --budget-name migraine-map-1usd \
  --amount 1 \
  --category Cost \
  --time-grain Monthly \
  --start-date "$(date -u +%Y-%m-01)" \
  --end-date "$(date -u -v+5y +%Y-%m-01 2>/dev/null || date -u -d '+5 years' +%Y-%m-01)" \
  --resource-group-filter "$RESOURCE_GROUP" \
  --output none || echo "   (budget create failed — set it in the portal instead)"

cat <<EOF

============================================================================
Done.

  App URL      : https://$FQDN
  SQL server   : $SQL_SERVER  ($SQL_FQDN)
  SQL DB       : $SQL_DB  (admin: $SQL_ADMIN_USER)
EOF

if [[ "$FIRST_PROVISION" == true ]]; then
  cat <<EOF

Every request currently gets HTTP 403 — the app is not readable by anyone.

NEXT STEPS
  1. Push a real image if you haven't:  see .github/workflows/deploy.yml
  2. Follow "Easy Auth" in deploy/README.md to add the Microsoft identity
     provider and assign only your account. That step also switches the
     unauthenticated action from 403 to the sign-in redirect.
  3. Open the URL, sign in with Microsoft, register your Laravel account once.
EOF
fi

echo "============================================================================"
