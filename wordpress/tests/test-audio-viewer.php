<?php
define('BACKYARD_TEST_BOOTSTRAP_ONLY',true);
require __DIR__.'/test-backyard.php';
function wp_verify_nonce($nonce,$action){return $nonce==='test-nonce';}
$id='12345678-1234-4234-8234-123456789abc';
$record=array('id'=>$id,'domain'=>'bat','status'=>'human_confirmed','audio_available'=>true);
$GLOBALS['http_handler']=function($url,$args)use(&$record){return array('response'=>array('code'=>200),'body'=>json_encode($record));};
$args=array('observation_id'=>$id,'domain'=>'bat','scope'=>'public','key'=>wp_hash('audio-view|bat|'.$id));
check(!is_wp_error(backyard_audio_view_record($args)),'Accepted public audio permitted');
$record['status']='pending_review';
check(is_wp_error(backyard_audio_view_record($args)),'Review audio never public');
$record['status']='human_rejected';
check(is_wp_error(backyard_audio_view_record($args)),'Rejection immediately revokes public access');
$args['scope']='admin';$args['key']='test-nonce';
check(!is_wp_error(backyard_audio_view_record($args)),'Authorized admin can inspect rejected audio');
$GLOBALS['allowed']=false;
check(is_wp_error(backyard_audio_view_record($args)),'Admin scope checks capability');
$GLOBALS['allowed']=true;
$record['audio_available']=false;
check(is_wp_error(backyard_audio_view_record($args)),'Removed evidence cannot be served');
$html=backyard_audio_shortcode(array('module'=>'bats','observation_id'=>$id));
check(strpos($html,'backyard-audio-viewer')!==false,'Shared shortcode viewer');
check(strpos($html,$GLOBALS['token'])===false,'Token not in markup');
check(backyard_audio_shortcode(array('observation_id'=>'../../secret'))==='','Invalid ID refused');
echo "Audio viewer authorization and shortcode checks passed.
";
