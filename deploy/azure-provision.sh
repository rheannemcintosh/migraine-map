#!/usr/bin/env bash
#
# One-time provisioning for Migraine Map on Azure.
# Creates: resource group, serverless Azure SQL (free offer), a consumption-only
# Container Apps environment, and the container app itself (scale-to-zero).
#
# Prerequisites:
#   - az CLI logged in:  az login
#   - az extension add --name containerapp --upgrade
#   - A container image already pushed to ghcr.io (see .github/workflows/deploy.yml,
#     or push one manually first) and the ghcr package set to Public.
#
# Easy Auth (restrict to the owner's Microsoft account) is NOT done here —
# follow deploy/README.md section "Easy Auth" after this script completes.
#
# Re-running: most creates are idempotent-ish; if a resource exists Azure will
# either no-op or error harmlessly. Safe to comment out completed steps.

set -euo pipefail

# ----------------------------------------------------------------------------
# Config — edit these
# ----------------------------------------------------------------------------
LOCATION="uksouth"
RESOURCE_GROUP="rg-migraine-map"

SQL_SERVER="migraine-map-sql-$RANDOM"     # must be globally unique; note the final value
SQL_DB="migraine_map"
SQL_ADMIN_USER="mmadmin"
SQL_ADMIN_PASSWORD="${SQL_ADMIN_PASSWORD:?Set SQL_ADMIN_PASSWORD env var to a strong password}"

ACA_ENV="migraine-map-env"
ACA_APP="migraine-map"
IMAGE="ghcr.io/rheannemcintosh/migraine-map:latest"

APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"   # reuse an existing key if you have one

# ----------------------------------------------------------------------------
echo "==> Resource group"
az group create --name "$RESOURCE_GROUP" --location "$LOCATION" --output none

echo "==> Azure SQL logical server"
az sql server create \
  --name "$SQL_SERVER" \
  --resource-group "$RESOURCE_GROUP" \
  --location "$LOCATION" \
  --admin-user "$SQL_ADMIN_USER" \
  --admin-password "$SQL_ADMIN_PASSWORD" \
  --output none

echo "==> Allow other Azure services (incl. Container Apps) to reach the server"
az sql server firewall-rule create \
  --resource-group "$RESOURCE_GROUP" \
  --server "$SQL_SERVER" \
  --name AllowAzureServices \
  --start-ip-address 0.0.0.0 --end-ip-address 0.0.0.0 \
  --output none

echo "==> Serverless database on the free offer (auto-pauses, stops if free limit hit)"
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

SQL_FQDN="$(az sql server show -g "$RESOURCE_GROUP" -n "$SQL_SERVER" --query fullyQualifiedDomainName -o tsv)"

echo "==> Container Apps environment (consumption-only, no Log Analytics = no log cost)"
az containerapp env create \
  --name "$ACA_ENV" \
  --resource-group "$RESOURCE_GROUP" \
  --location "$LOCATION" \
  --logs-destination none \
  --output none

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
  --env-vars \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    APP_KEY=secretref:app-key \
    DB_CONNECTION=sqlsrv \
    DB_HOST="$SQL_FQDN" \
    DB_PORT=1433 \
    DB_DATABASE="$SQL_DB" \
    DB_USERNAME="$SQL_ADMIN_USER" \
    DB_PASSWORD=secretref:db-password \
    DB_ENCRYPT=yes \
    DB_TRUST_SERVER_CERTIFICATE=false \
    DB_LOGIN_TIMEOUT=60 \
    SESSION_DRIVER=cookie \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
  --output none

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

NEXT STEPS
  1. Push a real image if you haven't:  see .github/workflows/deploy.yml
  2. Lock it down — follow "Easy Auth" in deploy/README.md so only your
     Microsoft account can reach the URL. Until you do, the app is PUBLIC.
  3. Open the URL, register your Laravel account once.
============================================================================
EOF
