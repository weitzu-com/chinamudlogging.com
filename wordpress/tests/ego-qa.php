<?php
if (!defined('CML_EGO_QA_ISOLATED')) throw new RuntimeException('Isolated fixtures only.');
define('WP_DEBUG',true);
define('WP_DISABLE_FATAL_ERROR_HANDLER',true);
require '/wordpress/wp-load.php';
update_option('cml_mail_enabled',false);
$checks=[];
$check=function($condition,$name)use(&$checks){$checks[]=['name'=>$name,'passed'=>(bool)$condition];};
$base=['nonce'=>wp_create_nonce('cml_inquiry'),'request_id'=>wp_generate_uuid4(),'language'=>'zh','name'=>str_repeat('测',60),'email'=>'qa@example.invalid','company'=>'TEST ONLY','country'=>'Test','product'=>'Test','message'=>'TEST ONLY. No sales follow-up.','source'=>'/zh/contact-us-zh/','consent'=>true,'website'=>''];
$_SERVER['REMOTE_ADDR']='192.0.2.31';
$submit=function($data){$request=new WP_REST_Request('POST','/cml/v1/inquiries');$request->set_header('content-type','application/json');$request->set_body(wp_json_encode($data));return cml_receive_inquiry($request);};
$response=$submit($base);$check($response instanceof WP_REST_Response&&$response->get_status()===201,'A valid sixty-character Chinese name is accepted');
if ($response instanceof WP_REST_Response){$again=$submit($base);$check($again instanceof WP_REST_Response&&$response->get_data()['receipt']===$again->get_data()['receipt'],'Repeated Unicode inquiry returns the same stored receipt');}
$arabic=array_merge($base,['request_id'=>wp_generate_uuid4(),'language'=>'ar','name'=>str_repeat('م',100)]);$check($submit($arabic) instanceof WP_REST_Response,'A valid Arabic name is counted in characters');
$too_long=array_merge($base,['request_id'=>wp_generate_uuid4(),'name'=>str_repeat('测',161)]);$error=$submit($too_long);$check(is_wp_error($error)&&$error->get_error_code()==='invalid_name','Character limits still reject genuinely overlong names');
$html=cml_render('inquiry');$check(str_contains($html,'name="country" type="text" autocomplete="country-name" maxlength="120"'),'Country field uses its actual server limit');
$check(str_contains($html,'name="product" type="text" autocomplete="off" maxlength="300"'),'Product field retains long technical product names');
// Simulate a transport failure only in this disposable database: the saved lead must survive.
update_option('cml_mail_enabled',true);
update_option('cml_recipient','qa@example.invalid');
$fail_mail=fn()=>false;add_filter('pre_wp_mail',$fail_mail);
$failed=$submit(array_merge($base,['request_id'=>wp_generate_uuid4(),'name'=>'TEST ONLY failed transport']));
remove_filter('pre_wp_mail',$fail_mail);
$stored=$failed instanceof WP_REST_Response?$wpdb->get_row($wpdb->prepare("SELECT id, mail_status FROM {$wpdb->prefix}cml_inquiries WHERE receipt=%s",$failed->get_data()['receipt'])):null;
$check($stored&&$stored->mail_status==='failed','Mail failure preserves the saved inquiry and flags it failed');
$accept_mail=fn()=>true;add_filter('pre_wp_mail',$accept_mail);
$retried=$stored?cml_dispatch_inquiry((int)$stored->id):null;remove_filter('pre_wp_mail',$accept_mail);
$check($retried==='transport_accepted','An explicit retry can notify a previously saved inquiry');
update_option('cml_mail_enabled',false);
$featured=wp_insert_post(['post_type'=>'cml_product','post_status'=>'publish','post_title'=>'QA curated product','meta_input'=>['_cml_source_id'=>'product:251']]);
$unreviewed=wp_insert_post(['post_type'=>'cml_product','post_status'=>'publish','post_title'=>'QA newest catalogue addition','meta_input'=>['_cml_source_id'=>'product:999999']]);
$home=cml_render('home');
$check(str_contains($home,'QA curated product')&&!str_contains($home,'QA newest catalogue addition'),'Homepage recommendations remain limited to the ten reviewed multilingual products');
$technical='CITY 4HS+ 100℃<42V (in&out)';
$wpdb->update($wpdb->posts,['post_title'=>$technical],['ID'=>$featured]);clean_post_cache($featured);
$GLOBALS['post']=get_post($featured);setup_postdata($GLOBALS['post']);$product_html=cml_render('product');wp_reset_postdata();
preg_match('/href="([^"]*product=[^"]*)"/',$product_html,$match);parse_str(wp_parse_url(html_entity_decode($match[1]??'',ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY)??'',$quote_query);
$check(($quote_query['product']??null)===$technical,'Quote links preserve plus, ampersand and less-than signs');
$edge=$submit(array_merge($base,['request_id'=>wp_generate_uuid4(),'name'=>'TEST ONLY technical symbols','product'=>$technical,'message'=>'TEST ONLY: '.$technical]));
$edge_payload=$edge instanceof WP_REST_Response?$wpdb->get_var($wpdb->prepare("SELECT payload FROM {$wpdb->prefix}cml_inquiries WHERE receipt=%s",$edge->get_data()['receipt'])):null;
$check($edge_payload&&json_decode($edge_payload,true)['product']===$technical&&json_decode($edge_payload,true)['message']==='TEST ONLY: '.$technical,'Saved product and requirements preserve exact technical comparison symbols');
if (!defined('WP_CLI')) define('WP_CLI',true);
if (!function_exists('pll_get_post_language')) { function pll_get_post_language($id) { return 'en'; } }
$copy_plan=json_decode(file_get_contents('/cml/corrections/english-catalogue-names-20261105.json'),true);
foreach ($copy_plan['records'] as $record) {
    wp_insert_post(['import_id'=>$record['id'],'post_type'=>'cml_product','post_status'=>'publish','post_name'=>$record['slug'],'post_title'=>'Source fixture','post_content'=>'<p>Protected source table &lt;42V 4-20mA</p>']);
    $wpdb->update($wpdb->posts,['post_title'=>$record['before']['title'],'post_excerpt'=>$record['before']['excerpt']],['ID'=>$record['id']]);clean_post_cache($record['id']);
}
$run_copy=function($operation){$args=['/cml/corrections/english-catalogue-names-20261105.json',$operation];ob_start();try{require '/cml/corrections/apply-english-catalogue-names.php';return json_decode(ob_get_contents(),true);}catch(Throwable $e){file_put_contents('/cml-qa/copy-error.json',wp_json_encode(['message'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]));throw $e;}finally{ob_end_clean();}};
$copy_total=count($copy_plan['records']);
$first=$copy_plan['records'][0];$last=end($copy_plan['records']);
wp_update_post(['ID'=>$last['id'],'post_title'=>'Concurrent editor change']);
$conflict=false;try{$run_copy('apply');}catch(RuntimeException $error){$conflict=str_contains($error->getMessage(),'Concurrent edit');}
$check($conflict&&get_post($first['id'])->post_title===$first['before']['title'],'Catalogue name changes preflight every record before writing');
wp_update_post(['ID'=>$last['id'],'post_title'=>$last['before']['title']]);
$applied=$run_copy('apply');$check($applied['protected_field_checks_passed']===$copy_total,'All catalogue copy fixes preserve bodies, units, URLs, dates and technical fields');
$again=$run_copy('apply');$check($again['already_at_target']===$copy_total&&$again['pending']===0,'Catalogue name correction is idempotent');
$rolled_back=$run_copy('rollback');$check($rolled_back['protected_field_checks_passed']===$copy_total&&get_post($first['id'])->post_title===$first['before']['title'],'Catalogue name rollback restores the original fields');
$result=['checks'=>$checks,'passed'=>count(array_filter($checks,fn($r)=>$r['passed'])),'total'=>count($checks)];file_put_contents('/cml-qa/results.json',wp_json_encode($result));echo wp_json_encode($result);
