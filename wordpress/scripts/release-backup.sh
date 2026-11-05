#!/usr/bin/env bash
# Run on the dedicated cml-siteground account; secrets remain outside the webroot.
set -euo pipefail
umask 077
site_root="$HOME/www/chinamudlogging.com/public_html"
backup_dir="$HOME/cml-backups/release-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -m 700 "$backup_dir"
cd "$site_root"
wp db export "$backup_dir/database.sql" --quiet
# Enumerate files without volatile parent-directory mtimes. Keep all core,
# plugin, theme, upload and configuration files; omit only rebuildable caches.
find . \( -path './wp-content/cache' -o -path './wp-content/uploads/siteground-optimizer-assets' \) -prune -o \( -type f -o -type l \) -print0 > "$backup_dir/files.list0"
tar --null -czf "$backup_dir/files.tar.gz" -T "$backup_dir/files.list0"
tar -czf "$backup_dir/mail-and-staging-secrets.tar.gz" -C "$HOME" .cml-mail .cml-staging/auth.json .cml-staging/preview-access.json
cd "$backup_dir"
sha256sum database.sql files.tar.gz mail-and-staging-secrets.tar.gz > SHA256SUMS
printf '%s\n' "$backup_dir"
