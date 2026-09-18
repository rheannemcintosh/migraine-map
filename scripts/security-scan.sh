#!/usr/bin/env bash
#
# Runs the Migraine Map security scan: dependency vulnerabilities, committed
# secrets, static analysis and container/CI configuration checks.
#
# Requires Docker (for gitleaks, Semgrep and Trivy) plus Composer and npm on
# the host. Reports are written to the directory given as the first argument
# (default: ./security-scan-output, which is git-ignored).
#
# See docs/security/README.md for what each step covers and how to read the
# results.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/security-scan-output}"
mkdir -p "$OUT"

cd "$ROOT"

echo "==> 1/6 PHP dependencies (composer audit)"
composer audit --format=summary || true
composer audit --format=json > "$OUT/composer-audit.json" || true

echo "==> 2/6 JavaScript dependencies (npm audit)"
npm audit || true
npm audit --json > "$OUT/npm-audit.json" || true

echo "==> 3/6 Committed secrets, full git history (gitleaks)"
docker run --rm -v "$ROOT:/repo" -v "$OUT:/out" zricethezav/gitleaks:latest \
    git /repo --report-format json --report-path /out/gitleaks.json --exit-code 0

echo "==> 4/6 Static analysis (PHPStan)"
vendor/bin/phpstan analyse --no-progress --error-format=table | tee "$OUT/phpstan.txt" || true

echo "==> 5/6 Static analysis (Semgrep)"
docker run --rm -v "$ROOT:/src" -w /src semgrep/semgrep:latest semgrep scan \
    --config p/php --config p/javascript --config p/typescript \
    --config p/secrets --config p/security-audit \
    --config p/dockerfile --config p/github-actions \
    --exclude vendor --exclude node_modules --exclude public/build \
    --metrics=off --json -o /src/.semgrep.json || true
mv .semgrep.json "$OUT/semgrep.json"

echo "==> 6/6 Lockfiles, Dockerfiles and secrets (Trivy)"
docker run --rm -v "$ROOT:/src" -v "$OUT:/out" aquasec/trivy:latest fs \
    --scanners vuln,misconfig,secret --include-dev-deps \
    --skip-dirs vendor,node_modules,public/build \
    --format table -o /out/trivy.txt /src
cat "$OUT/trivy.txt"

echo
echo "Reports written to $OUT"
