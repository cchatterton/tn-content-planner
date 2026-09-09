<?php
/** Disposable browser fixtures, invoked via wp eval-file. */
wp_set_current_user(1);
foreach (get_posts(array('post_type'=>'page','post_status'=>'any','numberposts'=>-1)) as $fixture) {
    if (str_starts_with($fixture->post_name, 'browser-review-')) { wp_delete_post($fixture->ID, true); }
}
$prefix = 'browser-review-' . time();
$posts = array();
foreach (array(
    array('Garden Planning', $prefix . '-plan'),
    array('Garden Design Services', $prefix . '-exact-title'),
    array('Garden Design Studio', $prefix . '-two-words'),
    array('Garden Garden Tips', $prefix . '-one-word'),
    array('Legacy Article', $prefix . '-legacy'),
) as $item) {
    $posts[] = wp_insert_post(array('post_type'=>'page','post_title'=>$item[0],'post_name'=>$item[1],'post_content'=>'Keep existing content','post_status'=>'publish'));
}
$rows = array();
foreach (array(array('review-one','Garden Design Services',$prefix.'-plan'),array('review-two','Entirely New Article',$prefix.'-new'),array('review-three','Legacy Redesign',$prefix.'-redesign')) as $item) {
    $rows[] = array('id'=>$item[0],'title'=>$item[1],'slug'=>$item[2],'parent'=>'','template'=>'single','flags'=>array(),'post_id'=>0,'confirmed'=>array('title'=>true,'slug'=>true,'parent'=>true));
}
$old=array('revision'=>0,'rows'=>array());
$rows=tncp_validate_rows($rows,'page',$old);
if(is_wp_error($rows)){throw new RuntimeException($rows->get_error_message());}
update_option('tncp_plan_page',array('revision'=>1,'rows'=>$rows),false);
echo wp_json_encode(array('prefix'=>$prefix,'posts'=>$posts));
