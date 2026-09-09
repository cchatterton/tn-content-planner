<?php
/** Immediate linked edits, run in disposable WordPress only. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
global $checks;
$old = get_option('tncp_plan_page', null); $ids = array(); $checks = 0;
function change_assert($ok, $message) { global $checks; if (!$ok) { throw new RuntimeException($message); } ++$checks; }
function change_call($data) { $r = new WP_REST_Request('POST', '/tncp/v1/change/page'); foreach ($data as $k=>$v) { $r->set_param($k,$v); } return rest_do_request($r); }
try {
    delete_option('tncp_plan_page');
    $id = wp_insert_post(array('post_type'=>'page','post_title'=>'Immediate original','post_name'=>'immediate-'.time(),'post_content'=>'Preserve body','post_status'=>'draft')); $ids[]=$id;
    $parent = wp_insert_post(array('post_type'=>'page','post_title'=>'Immediate parent','post_status'=>'publish')); $ids[]=$parent;
    $plan = tncp_scan_rows(array('revision'=>0,'rows'=>array()), 'page');
    if (is_wp_error($plan)) { throw new RuntimeException($plan->get_error_message()); }
    $plan = tncp_store('page', $plan);
    $index = array_search($id, array_column($plan['rows'],'post_id'), true); $rowid=$plan['rows'][$index]['id'];
    $call = function($field,$value,$extra=array()) use (&$plan,$id,$rowid) {
        $data=array('revision'=>$plan['revision'],'row_id'=>$rowid,'post_id'=>$id,'baseline'=>tncp_snapshot(get_post($id)),'planning'=>tncp_post_planning($id),'field'=>$field,'value'=>$value,'confirmed'=>true);
        $r=change_call(array_merge($data,$extra)); if(200===$r->get_status()) { $plan=$r->get_data()['plan']; } return $r;
    };
    foreach(array('title'=>'Immediate approved','slug'=>'immediate-approved-'.time(),'parent'=>$parent,'template'=>'archive','local'=>true,'related'=>true,'children'=>true,'siblings'=>true,'parents'=>true) as $field=>$value) {
        $r=$call($field,$value); change_assert(200===$r->get_status(),$field.': '.wp_json_encode($r->get_data()));
        $row=$plan['rows'][$index];
        $actual=in_array($field,array('title','slug','parent'),true)?tncp_snapshot(get_post($id))[$field]:('template'===$field?tncp_post_planning($id)['template']:tncp_post_planning($id)['flags'][$field]);
        change_assert($actual===$value,$field.' immediately applied');
        change_assert($row['baseline']===tncp_snapshot(get_post($id)), $field.' baseline refreshed');
    }
    change_assert('page-1-archive-5'===get_post_meta($id,'_tncp_pattern',true),'Pattern metadata reflects hierarchy/template/flags');
    change_assert('Preserve body'===get_post($id)->post_content && 'draft'===get_post_status($id),'Body and publication status preserved');
    change_assert(''===get_post_meta($id,'_wp_page_template',true),'Theme template untouched');
    change_assert(409===$call('title','Stale',array('baseline'=>array()))->get_status(),'Reject stale linked post');
    change_assert(409===$call('template','custom',array('planning'=>array()))->get_status(),'Reject stale metadata');
    change_assert(400===$call('parent',$id)->get_status(),'Reject self-parent');
    change_assert(400===$call('parent',-1)->get_status(),'Reject uncreated parent');
    change_assert(400===$call('title','Unapproved',array('confirmed'=>false))->get_status(),'Reject unapproved change');
    change_assert(400===$call('template','invalid')->get_status(),'Reject invalid template');
    $plan['rows'][$index]['title']='Unapproved planned title';
    $plan['rows'][$index]['confirmed']['title']=false;
    $plan=tncp_store('page',$plan);
    $r=$call('slug','immediate-only-slug-'.time());
    change_assert(200===$r->get_status(),'Apply slug beside unrelated planned title');
    change_assert('Immediate approved'===get_post($id)->post_title && 'Unapproved planned title'===$plan['rows'][$index]['title'],'Only chosen field applies');
    $plan['rows'][$index]['confirmed']['title']=true;
    $plan['rows'][$index]['template']='custom';
    $plan=tncp_store('page',$plan);
    $plan=tncp_apply_saved_approvals($plan,'page');
    change_assert(!is_wp_error($plan),'Earlier saved approvals apply');
    change_assert('Unapproved planned title'===get_post($id)->post_title && 'custom'===tncp_post_planning($id)['template'],'Saved approved title and metadata applied');
    change_assert(!$plan['rows'][$index]['confirmed']['title'],'Approval cleared after application');
    $plan['rows'][$index]['title']='Saved conflicting title'; $plan['rows'][$index]['confirmed']['title']=true;
    wp_update_post(array('ID'=>$id,'post_title'=>'External title'));
    $result=tncp_apply_saved_approvals($plan,'page');
    change_assert(!is_wp_error($result) && 'External title'===get_post($id)->post_title,'Scan preserves external conflict for review');
    echo "PASS: $checks immediate-change checks\n";
} finally {
    foreach($ids as $id) { wp_delete_post($id,true); }
    if(null===$old) { delete_option('tncp_plan_page'); } else { update_option('tncp_plan_page',$old,false); }
}
