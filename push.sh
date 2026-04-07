#!/bin/bash
cd /c/xampp/htdocs/muy-app
echo "--- Pulling latest from GitHub first ---"
git pull origin master
echo ""
echo "--- Pushing your changes ---"
git add -A
git commit -m "update code and db changes"
git push origin master
echo ""
echo "=== PUSH COMPLETE ==="
