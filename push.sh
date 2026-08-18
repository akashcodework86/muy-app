#!/bin/bash
set -euo pipefail

cd /c/xampp/htdocs/muy-app

echo "--- Pulling latest from GitHub first ---"
git pull origin master --rebase
echo ""

echo "--- Staging tracked file changes only (ignores outputs/, tmp/, node_modules) ---"
git add -u

if git diff --cached --quiet; then
  echo "No tracked changes to commit. Nothing to push."
  echo ""
  echo "=== PUSH SKIPPED ==="
  exit 0
fi

echo "--- Committing ---"
git commit -m "update code and db changes"

echo "--- Pushing ---"
git push origin master
echo ""
echo "=== PUSH COMPLETE ==="
