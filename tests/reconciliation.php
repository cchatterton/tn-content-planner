<?php
/** Run only in disposable WordPress: wp eval-file tests/reconciliation.php. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
global $tncp_review_checks; $tncp_review_checks = 0;
function tncp_review_assert($ok, $message) { global $tncp_review_checks; if (!$ok) { throw new RuntimeException($message); } ++$tncp_review_checks; }
function tncp_review_call($route, $data) {
    $request = new WP_REST_Request('POST', '/tncp/v1/' . $route . '/page');
    foreach ($data as $key => $value) { $request->set_param($key, $value); }
    return rest_do_request($request);
}
function tncp_review_row($id, $title, $slug) { return array('id' => $id, 'title' => $title, 'slug' => $slug, 'parent' => '', 'template' => 'single', 'flags' => array(), 'post_id' => 0, 'confirmed' => array()); }
function tncp_review_snapshot($id) {
    foreach (tncp_catalog('page') as $post) { if ($post['id'] === $id) { return array_intersect_key($post, array_flip(array('title','slug','parent','planning'))); } }
}
$old = get_option('tncp_plan_page', null);
$ids = array(); $prefix = 'reconcile-' . strtolower(wp_generate_password(8, false, false));
try {
    delete_option('tncp_plan_page');
    $a = wp_insert_post(array('post_type'=>'page','post_title'=>'Destination A','post_name'=>$prefix.'-a-existing','post_content'=>'Retain destination A','post_status'=>'publish')); $ids[]=$a;
    $b = wp_insert_post(array('post_type'=>'page','post_title'=>'Destination B','post_name'=>$prefix.'-b-existing','post_content'=>'Retain destination B','post_status'=>'draft')); $ids[]=$b;
    $rows = array(tncp_review_row('a','Source A',$prefix.'-a-plan'), tncp_review_row('b','Source B',$prefix.'-b-plan'));
    $plan = tncp_review_call('save',array('revision'=>0,'rows'=>$rows))->get_data();
    $before = tncp_snapshot(get_post($a));
    $result=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'a','decision'=>'destination','target_id'=>$a,'target_snapshot'=>tncp_review_snapshot($a)));
    tncp_review_assert(200 === $result->get_status(), 'Accept destination: '.wp_json_encode($result->get_data())); $data=$result->get_data();$plan=$data['plan'];
    tncp_review_assert(array('a')===$data['completed'], 'Exactly current item completed');
    tncp_review_assert($a===$plan['rows'][0]['post_id'] && 'Destination A'===$plan['rows'][0]['title'], 'Destination values adopted');
    tncp_review_assert($before===tncp_snapshot(get_post($a)) && 'Retain destination A'===get_post($a)->post_content, 'Destination post unchanged');
    tncp_review_assert(0===$plan['rows'][1]['post_id'], 'Next item not applied');
    $snapshot=tncp_review_snapshot($b);
    wp_update_post(array('ID'=>$b,'post_title'=>'Destination B edited'));
    $stale=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'b','decision'=>'source','target_id'=>$b,'target_snapshot'=>$snapshot));
    tncp_review_assert(409===$stale->get_status(), 'Reject changed candidate');
    $result=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'b','decision'=>'source','target_id'=>$b,'target_snapshot'=>tncp_review_snapshot($b)));
    tncp_review_assert(200===$result->get_status(),'Accept source: '.wp_json_encode($result->get_data()));$plan=$result->get_data()['plan'];
    tncp_review_assert('Source B'===get_post($b)->post_title && $prefix.'-b-plan'===get_post($b)->post_name,'Source title/slug applied');
    tncp_review_assert('Retain destination B'===get_post($b)->post_content && 'draft'===get_post_status($b),'Source keeps destination content/status');
    $collision=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'a','decision'=>'new','new_slug'=>get_post($a)->post_name));
    tncp_review_assert(400===$collision->get_status(),'Create new rejects existing slug');
    $result=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'a','decision'=>'new','new_slug'=>$prefix.'-new','creation_status'=>'draft'));
    tncp_review_assert(200===$result->get_status(),'Create distinct post: '.wp_json_encode($result->get_data()));$plan=$result->get_data()['plan'];$new=$plan['rows'][0]['post_id'];$ids[]=$new;
    tncp_review_assert($new!==$a && 'draft'===get_post_status($new),'Create new creates separate draft');
    tncp_review_assert($before===tncp_snapshot(get_post($a)),'Creating separate post leaves original untouched');
    tncp_review_assert('a'!==$plan['rows'][0]['id'],'Separate creation uses distinct durable row identity');
    $duplicate=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>$plan['rows'][0]['id'],'decision'=>'destination','target_id'=>$b,'target_snapshot'=>tncp_review_snapshot($b)));
    tncp_review_assert(400===$duplicate->get_status(),'Reject post already mapped to another row');
    // Review a replacement parent, then explicitly move its mapped child in the next item.
    wp_update_post(array('ID'=>$b,'post_parent'=>$a));
    delete_option('tncp_plan_page');
    $parent=tncp_review_row('parent',get_post($a)->post_title,get_post($a)->post_name);$parent['post_id']=$a;
    $child=tncp_review_row('child',get_post($b)->post_title,get_post($b)->post_name);$child['post_id']=$b;$child['parent']='row:parent';
    $plan=tncp_review_call('save',array('revision'=>0,'rows'=>array($parent,$child)))->get_data();
    $result=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'parent','decision'=>'new','new_slug'=>$prefix.'-replacement','creation_status'=>'draft'));
    tncp_review_assert(200===$result->get_status(),'Replacement parent can be reviewed before mapped child: '.wp_json_encode($result->get_data()));$plan=$result->get_data()['plan'];$replacement=$plan['rows'][0]['post_id'];$ids[]=$replacement;
    tncp_review_assert($a===(int)get_post($b)->post_parent,'Unreviewed child remains under original parent');
    tncp_review_assert('row:'.$plan['rows'][0]['id']===$plan['rows'][1]['parent'],'Planned child follows replacement parent reference');
    $result=tncp_review_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'child','decision'=>'source','target_id'=>$b,'target_snapshot'=>tncp_review_snapshot($b)));
    tncp_review_assert(200===$result->get_status(),'Apply mapped child after replacement parent');
    tncp_review_assert($replacement===(int)get_post($b)->post_parent,'Child move applied only on its review action');
    $plan=$result->get_data()['plan'];
    tncp_review_assert(array('mapped'=>2,'planned'=>2)===tncp_plan_counts('page'),'Mapped tab totals');
    $blocked=tncp_review_call('bin',array('revision'=>$plan['revision'],'row_id'=>$plan['rows'][0]['id'],'confirmed'=>true));
    tncp_review_assert(400===$blocked->get_status(),'Cannot bin planned parent with children');
    $blocked=tncp_review_call('bin',array('revision'=>$plan['revision'],'row_id'=>'child','confirmed'=>false));
    tncp_review_assert(400===$blocked->get_status(),'Bin requires explicit choice');
    wp_update_post(array('ID'=>$b,'post_title'=>'Updated externally'));
    $blocked=tncp_review_call('bin',array('revision'=>$plan['revision'],'row_id'=>'child','confirmed'=>true));
    tncp_review_assert(409===$blocked->get_status(),'Bin rejects stale post');
    $refreshed=tncp_review_call('refresh',array('revision'=>$plan['revision'],'preserve_pending'=>true));
    tncp_review_assert(200===$refreshed->get_status(),'Automatic refresh succeeds');$plan=$refreshed->get_data();
    tncp_review_assert('Updated externally'===$plan['rows'][1]['title'],'Automatic refresh adopts external title on unchanged row');
    $plan['rows'][1]['title']='Pending title';$plan['rows'][1]['confirmed']['title']=true;
    $plan=tncp_review_call('save',array('revision'=>$plan['revision'],'rows'=>$plan['rows']))->get_data();
    $plan=tncp_review_call('refresh',array('revision'=>$plan['revision'],'preserve_pending'=>true))->get_data();
    tncp_review_assert('Pending title'===$plan['rows'][1]['title'],'Tab refresh preserves pending changes');
    $binned=tncp_review_call('bin',array('revision'=>$plan['revision'],'row_id'=>'child','confirmed'=>true));
    tncp_review_assert(200===$binned->get_status(),'Bin linked child: '.wp_json_encode($binned->get_data()));
    tncp_review_assert('trash'===get_post_status($b) && get_post($b),'Bin keeps recoverable post');
    tncp_review_assert('draft'===get_post_status($replacement),'Bin leaves other posts unchanged');
    tncp_review_assert(array('mapped'=>1,'planned'=>1)===tncp_plan_counts('page'),'Bin updates plan counts');
    echo "PASS: $tncp_review_checks reconciliation integration checks\n";
} finally {
    foreach(array_reverse($ids) as $id){wp_delete_post($id,true);}
    if(null===$old){delete_option('tncp_plan_page');}else{update_option('tncp_plan_page',$old,false);}
}
