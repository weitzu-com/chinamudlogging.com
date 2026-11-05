#!/usr/bin/env bash
# Run only on the existing SiteGround account after review and checks.
set -euo pipefail
umask 077
stage="$HOME/cml-releases/product-body-spec-cleanup-20261105"
site="$HOME/www/chinamudlogging.com/public_html"
theme="$site/wp-content/themes/cml"
expected=347bc99a1cd2050a2897bf3218a708b72dab29a4c4a928e50276b8d6e416c61a
test "$(sha256sum "$theme/functions.php" | cut -d' ' -f1)" = "$expected" || { echo 'Theme entry changed: abort'; exit 1; }
cd "$site"
wp eval-file "$stage/wordpress/scripts/check-product-details.php"
php -l "$stage/wordpress/theme/cml/functions.php"
php -l "$stage/wordpress/theme/cml/inc/product-details.php"
cp -p "$theme/functions.php" "$stage/functions.before.php"
for file in assets/product-details.json assets/product-details.css inc/product-details.php; do
    install -m 644 "$stage/wordpress/theme/cml/$file" "$theme/$file.next"
    mv "$theme/$file.next" "$theme/$file"
done
install -m 644 "$stage/wordpress/theme/cml/functions.php" "$theme/functions.php.next"
mv "$theme/functions.php.next" "$theme/functions.php"
wp sg purge
sha256sum "$theme/functions.php" "$theme/inc/product-details.php" "$theme/assets/product-details.json" "$theme/assets/product-details.css"
echo 'Released reviewed product presentation'
