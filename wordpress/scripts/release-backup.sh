#!/usr/bin/env bash
# Run on the dedicated cml-siteground account; secrets remain outside the webroot.
set -euo pipefail
umask 077
site_root="$HOME/www/chinamudlogging.com/public_html"
backup_dir="$HOME/cml-backups/release-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -m 700 "$backup_dir"
cd "$site_root"
wp db export "$backup_dir/database.sql" --quiet
tar -czf "$backup_dir/files.tar.gz" -C "$site_root" .
tar -czf "$backup_dir/mail-and-staging-secrets.tar.gz" -C "$HOME" .cml-mail .cml-staging/auth.json .cml-staging/preview-access.json
cd "$backup_dir"
sha256sum database.sql files.tar.gz mail-and-staging-secrets.tar.gz > SHA256SUMS
printf '%s\n' "$backup_dir"
