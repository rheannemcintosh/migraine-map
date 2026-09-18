# Security scanning

How to run the Migraine Map security scan, what it covers, and where the
results of past scans live. The scan is a snapshot: run it again whenever
dependencies change materially, before a release, or on a regular cadence.

Past scans are written up in [`scans/`](scans), one file per run, newest
first:

- [2026-09-18](scans/2026-09-18.md) (MM-45)

## Running the scan

Everything runs from one script. It needs Docker on the host (gitleaks,
Semgrep and Trivy run as containers, so nothing else is installed) plus the
host Composer and npm that a normal checkout already uses.

```bash
./scripts/security-scan.sh
```

Reports land in `security-scan-output/` (git-ignored). Pass a directory as
the first argument to put them somewhere else.

The script never fails early: every tool runs and its output is kept, so
read the console output and the report files rather than relying on the
exit code.

## What it covers

| Step | Tool | Scope |
| --- | --- | --- |
| Dependency vulnerabilities | `composer audit` | PHP packages in `composer.lock` against the Packagist security advisories database |
| Dependency vulnerabilities | `npm audit` | JavaScript packages in `package-lock.json` against the GitHub Advisory Database |
| Dependency vulnerabilities | Trivy (`--include-dev-deps`) | Both lockfiles again, including dev dependencies, using Trivy's own aggregated vulnerability feeds |
| Committed secrets | gitleaks | Every commit in the full git history, not just the working tree |
| Committed secrets | Semgrep `p/secrets`, Trivy `secret` scanner | The working tree |
| Static analysis | PHPStan (Larastan, level 7) | `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/` |
| Static analysis | Semgrep `p/php`, `p/javascript`, `p/typescript`, `p/security-audit` | All tracked PHP, TypeScript and Vue source |
| Container and CI configuration | Semgrep `p/dockerfile`, `p/github-actions`; Trivy `misconfig` | Both Dockerfiles and the workflows in `.github/workflows/` |

Two things the script cannot do, which each scan write-up should also cover:

- **Manual review** of the application code for authorisation, input
  handling, data exposure and configuration. Automated tools do not
  understand what "this user's data" means. The 2026-09-18 write-up lists
  the areas checked so the next review can start from the same checklist.
- **Runtime image checks.** The production image is built from
  `serversideup/php:8.4-fpm-nginx`. Its nginx security headers and PHP
  settings were inspected by hand; if the base image changes, repeat that.

## Reading the results

- `composer-audit.json`, `npm-audit.json`, `trivy.txt`: any advisory listed
  is a finding. Record its severity from the advisory itself.
- `gitleaks.json`: an empty array means no leaks. Any entry names the
  commit, file and rule; treat it as a Critical finding until proven to be a
  false positive, and remember that a secret in history stays exposed even
  after the file is deleted.
- `phpstan.txt`: errors here are code-quality findings, not necessarily
  security ones. Judge each on its own.
- `semgrep.json`: `results` holds the findings with a `check_id`, path,
  line and severity. Check `errors` too: a 404 downloading a ruleset means
  that ruleset did not run at all, so update the `--config` list in the
  script. `p/laravel` and `p/vue` no longer exist and are not used for that
  reason.
- `trivy.txt`: the Dockerfile misconfiguration section lists the base
  image hardening checks. Several are expected for the local Sail image;
  see the scan write-up before raising them.

## Writing up a scan

Create `scans/<YYYY-MM-DD>.md` with:

1. The commit scanned and the tool versions used.
2. A results table per step.
3. Every finding with an ID, severity, evidence, the follow-up ticket, or
   the reason it is accepted.
4. The manual review checklist and what was verified.

Raise one Jira ticket per finding that needs action and link it to the
scan ticket. Fixes are their own branches, never part of the scan branch.
