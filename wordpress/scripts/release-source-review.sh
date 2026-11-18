#!/usr/bin/env bash
set -euo pipefail
umask 077
stage="$HOME/cml-releases/product-source-verification-20261111"
site="$HOME/www/chinamudlogging.com/public_html"
theme="$site/wp-content/themes/cml"
test "$(sha256sum "$theme/functions.php" | cut -d' ' -f1)" = 866eeada1dc557f0ef27607602e6446ffb1dde53cefe25487f75d65a753856a6
test "$(sha256sum "$theme/inc/product-details.php" | cut -d' ' -f1)" = 5a99fe30839a07844b924873d65d524a5cc4ff0e157eb89820f0396e059fb14b
test "$(sha256sum "$theme/assets/product-details.json" | cut -d' ' -f1)" = 29d3da559cc9e9be15b128d4c4be71c1258117748378d487d7c37d482a526cbb
test "$(sha256sum "$theme/assets/product-details.css" | cut -d' ' -f1)" = 247edc04d61d509b8b761c116e613a73496ceb742713b19c64fc6c1fb82793db
cd "$site"
wp eval-file "$stage/wordpress/scripts/check-source-review.php"
php -l "$stage/wordpress/theme/cml/inc/product-details.php"
mkdir -p "$stage/before/inc" "$stage/before/assets"
for file in assets/product-details.json assets/product-details.css inc/product-details.php;do
    cp -p "$theme/$file" "$stage/before/$file"
    install -m 644 "$stage/wordpress/theme/cml/$file" "$theme/$file.next"
    mv "$theme/$file.next" "$theme/$file"
done
wp sg purge
sha256sum "$theme/inc/product-details.php" "$theme/assets/product-details.json" "$theme/assets/product-details.css"
