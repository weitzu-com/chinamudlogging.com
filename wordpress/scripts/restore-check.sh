#!/bin/sh
set -eu
umask 077
CML_WP_ROOT="$HOME/www/chinamudlogging.com/public_html"
CML_BACKUP_DIR="$HOME/cml-backups/rehearsal-20261104"
CML_RESTORE_DIR="$HOME/cml-restore-check-20261104"
CML_RESTORE_PREFIX="cml_restore_20261104_"
if test -e "$CML_BACKUP_DIR" || test -e "$CML_RESTORE_DIR"; then
  echo "Rehearsal paths already exist; inspect the existing result instead of overwriting."
  exit 1
fi
CML_PREFIX=$(wp config get table_prefix --type=variable --path="$CML_WP_ROOT")
mkdir -p "$CML_BACKUP_DIR" "$CML_RESTORE_DIR"
wp db export "$CML_BACKUP_DIR/database.sql" --path="$CML_WP_ROOT" --quiet
tar -czf "$CML_BACKUP_DIR/files.tar.gz" -C "$HOME/www/chinamudlogging.com" public_html
tar -czf "$CML_BACKUP_DIR/mail-and-staging-secrets.tar.gz" -C "$HOME" .cml-mail .cml-staging/auth.json .cml-staging/preview-access.json
tar -xzf "$CML_BACKUP_DIR/files.tar.gz" -C "$CML_RESTORE_DIR"
php -r '
$sql=file_get_contents($argv[1]);
if(preg_match("/(?:CREATE DATABASE|^USE[[:space:]])/mi",$sql)) throw new RuntimeException("Unexpected database switch in dump");
$sql=str_replace("`".$argv[3],"`".$argv[4],$sql);
file_put_contents($argv[2],$sql);
' "$CML_BACKUP_DIR/database.sql" "$CML_BACKUP_DIR/isolated-restore.sql" "$CML_PREFIX" "$CML_RESTORE_PREFIX"
wp db import "$CML_BACKUP_DIR/isolated-restore.sql" --path="$CML_WP_ROOT" --quiet
wp config set table_prefix "$CML_RESTORE_PREFIX" --type=variable --path="$CML_RESTORE_DIR/public_html" --quiet
wp config set DISABLE_WP_CRON true --raw --path="$CML_RESTORE_DIR/public_html" --quiet
wp db query "UPDATE ${CML_RESTORE_PREFIX}options SET option_name=REPLACE(option_name,'${CML_PREFIX}','${CML_RESTORE_PREFIX}') WHERE option_name LIKE '${CML_PREFIX}%'; UPDATE ${CML_RESTORE_PREFIX}usermeta SET meta_key=REPLACE(meta_key,'${CML_PREFIX}','${CML_RESTORE_PREFIX}') WHERE meta_key LIKE '${CML_PREFIX}%';" --path="$CML_RESTORE_DIR/public_html" --quiet
wp core is-installed --path="$CML_RESTORE_DIR/public_html"
wp eval '
global $wpdb;
$p=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"cml_product\" AND post_status=\"publish\"");
$a=get_posts(["post_type"=>"attachment","post_status"=>"inherit","numberposts"=>-1,"lang"=>"","meta_key"=>"_cml_asset_key"]);
$missing=[];foreach($a as $x)if(!is_file(get_attached_file($x->ID)))$missing[]=$x->ID;
$report=["publishedProducts"=>$p,"restoredMedia"=>count($a),"missingMedia"=>$missing,"administrators"=>count(get_users(["role"=>"administrator"])),"inquiries"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cml_inquiries")];
file_put_contents(getenv("HOME")."/cml-backups/rehearsal-20261104/restore-result.json",wp_json_encode($report,JSON_PRETTY_PRINT));
echo wp_json_encode($report).PHP_EOL;
if($p!==63 || count($a)!==85 || $missing || !$report["administrators"])throw new RuntimeException("Restore acceptance failed");
' --path="$CML_RESTORE_DIR/public_html"
