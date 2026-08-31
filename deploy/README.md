# Deploying Migraine Map to Azure

A live, **private** URL for Migraine Map that costs **~$0 while idle**.

| Piece | Choice | Idle cost |
| --- | --- | --- |
| Compute | Azure Container Apps, consumption plan, `min-replicas 0` | $0 (free monthly grant; scales to zero) |
| Database | Azure SQL Database, serverless, **free offer**, auto-pause | $0 (free monthly grant; auto-pauses) |
| Image registry | GitHub Container Registry (`ghcr.io`) | $0 |
| Logs | none (console stream only) | $0 |
| Auth | Container Apps Easy Auth + Microsoft Entra ID, single user assigned | $0 |

Everything lives in one resource group (`rg-migraine-map`) so it can be deleted in one command.

---

## 1. Prerequisites

```bash
az login
az extension add --name containerapp --upgrade
az provider register --namespace Microsoft.App --wait
az provider register --namespace Microsoft.OperationalInsights --wait
```

You also need Docker locally if you want to build/test the image by hand.

---

## 2. First image

CI (`.github/workflows/deploy.yml`) builds and pushes on every push to `main`, but the
provisioning script needs an image to exist first. Either let CI run once, or push manually:

```bash
docker build -t ghcr.io/rheannemcintosh/migraine-map:latest .
echo "$GHCR_PAT" | docker login ghcr.io -u rheannemcintosh --password-stdin
docker push ghcr.io/rheannemcintosh/migraine-map:latest
```

Then make the package public: GitHub → your profile → Packages → `migraine-map` →
Package settings → Change visibility → **Public**. (Keeps the container app config
credential-free. If you prefer private, add `--registry-server ghcr.io
--registry-username ... --registry-password ...` to the `az containerapp create` call.)

---

## 3. Provision

```bash
export SQL_ADMIN_PASSWORD='<a strong password>'
# optional: export APP_KEY='base64:...'   to reuse an existing Laravel key
./deploy/azure-provision.sh
```

Note the printed **App URL** and **SQL server** name. The script:

- creates `rg-migraine-map`
- creates a serverless Azure SQL database on the free offer (auto-pause 60 min,
  auto-stops rather than bills if the free limit is exhausted)
- creates a consumption-only Container Apps environment (no Log Analytics)
- creates the `migraine-map` container app with `min-replicas 0`, port 8080, all env
  vars/secrets, then sets `APP_URL` to the real FQDN
- creates a $1/month budget alert

At this point the app is **live but PUBLIC**. Lock it down in step 5 before sharing the URL.

---

## 4. GitHub Actions → Azure (OIDC, no stored secrets)

One-time federated-credential setup so CI can deploy without a client secret:

```bash
SUB_ID=$(az account show --query id -o tsv)
APP_ID=$(az ad app create --display-name migraine-map-deploy --query appId -o tsv)
az ad sp create --id "$APP_ID"

# let it manage only this resource group
az role assignment create \
  --assignee "$APP_ID" \
  --role Contributor \
  --scope "/subscriptions/$SUB_ID/resourceGroups/rg-migraine-map"

# trust the GitHub repo's main branch
az ad app federated-credential create --id "$APP_ID" --parameters '{
  "name": "github-main",
  "issuer": "https://token.actions.githubusercontent.com",
  "subject": "repo:rheannemcintosh/migraine-map:ref:refs/heads/main",
  "audiences": ["api://AzureADTokenExchange"]
}'

echo "AZURE_CLIENT_ID       = $APP_ID"
echo "AZURE_TENANT_ID       = $(az account show --query tenantId -o tsv)"
echo "AZURE_SUBSCRIPTION_ID = $SUB_ID"
```

Add those three as **repository secrets** (Settings → Secrets and variables → Actions):
`AZURE_CLIENT_ID`, `AZURE_TENANT_ID`, `AZURE_SUBSCRIPTION_ID`.

Run the `deploy` workflow once manually (Actions → deploy → Run workflow) to confirm it
builds, pushes to ghcr, and updates the container app. Then edit
`.github/workflows/deploy.yml` and uncomment the `push` trigger so every merge to `main`
deploys automatically.

---

## 5. Easy Auth — restrict to your Microsoft account only

### a. Enable the Microsoft identity provider

Portal → `migraine-map` container app → **Authentication** → **Add identity provider** →
**Microsoft**:

- **App registration type**: Create new
- **Supported account types**: **Current tenant — Single tenant**
- **Restrict access**: **Require authentication**
- **Unauthenticated requests**: **HTTP 302 Found redirect** (recommended)
- **Token store**: enabled

Save. This creates an Entra app registration and wires it up. Unauthenticated visitors
are now bounced to Microsoft sign-in.

CLI equivalent (after the registration exists):

```bash
az containerapp auth update -g rg-migraine-map -n migraine-map \
  --unauthenticated-client-action RedirectToLoginPage \
  --require-authentication true
```

### b. Allow only your account

Any user in the tenant can still sign in at this point. To pin it to one account:

Portal → **Microsoft Entra ID** → **Enterprise applications** → open the
`migraine-map` app (the one Easy Auth just created) →

1. **Properties** → **Assignment required?** → **Yes** → Save
2. **Users and groups** → **Add user/group** → select **only your own account** → Assign

Now every other account gets *"needs admin approval / not assigned"* and is denied
**before the request ever reaches the container** — so it also can't wake the app or the
database.

### c. App-level login

Behind Easy Auth, Laravel still has its own login. Open the URL, sign in with Microsoft,
then **register your Laravel account once** on the app's register page. The cookie session
persists. (Optional later: remove `Features::registration()` from `config/fortify.php` and
redeploy so no further self-registration is possible.)

---

## 6. Verify the Definition of Done

**Live private URL**
```bash
curl -sI https://<fqdn>/            # expect 302 to login.microsoftonline.com
```
Open in a browser → Microsoft sign-in → your account → app loads. Try an incognito window
with a different Microsoft account → **denied**.

**Scales to zero**
```bash
# wait ~10 min with no traffic, then:
az containerapp replica list -g rg-migraine-map -n migraine-map -o table   # expect empty
az sql db show -g rg-migraine-map -s <sql-server> -n migraine_map \
  --query status -o tsv                                                    # expect "Paused"
```

**Cold start with no manual step**
With both paused, open the URL fresh. First load takes ~30–60s (container start +
`migrate` + DB resume), then serves normally. Re-load → fast. Your registered account
is still there (data persisted in Azure SQL).

**Idle cost is zero**
- Portal → **Cost Management** → **Cost analysis**, scope = `rg-migraine-map`. After
  24–48h it should read ~$0.00.
- Portal → **Subscriptions** → your sub → **Free services** — shows Container Apps and
  Azure SQL free-grant consumption.
- The `migraine-map-1usd` budget alert emails you if anything ever crosses $1.

---

## 7. Local image test (optional but recommended)

```bash
docker build -t migraine-map .
docker run --rm -p 8080:8080 \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_ENV=local -e APP_DEBUG=true \
  -e DB_CONNECTION=sqlsrv \
  -e DB_HOST=<your-sql-server>.database.windows.net \
  -e DB_DATABASE=migraine_map \
  -e DB_USERNAME=mmadmin -e DB_PASSWORD="$SQL_ADMIN_PASSWORD" \
  -e SESSION_DRIVER=cookie -e CACHE_STORE=file -e QUEUE_CONNECTION=sync \
  migraine-map

curl -sf http://localhost:8080/up      # health check → 200
```

(Add your machine's IP as an Azure SQL firewall rule first, or test against a local
SQL Server / Azure SQL Edge container.)

---

## Tear down

```bash
az group delete --name rg-migraine-map --yes --no-wait
```

Also delete the `migraine-map-deploy` Entra app registration and the Easy Auth app
registration if you want a completely clean slate.
