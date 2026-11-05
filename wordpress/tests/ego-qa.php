<?php
if (!defined('CML_EGO_QA_ISOLATED')) throw new RuntimeException('Isolated fixtures only.');
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
$result=['checks'=>$checks,'passed'=>count(array_filter($checks,fn($r)=>$r['passed'])),'total'=>count($checks)];file_put_contents('/cml-qa/results.json',wp_json_encode($result));echo wp_json_encode($result);
