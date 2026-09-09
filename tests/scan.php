<?php
/** Run only in disposable WordPress with WP CLI. */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
wp_set_current_user(1);
global $checks; $checks=0;$ids=array();
function scan_assert($ok,$message){global $checks;if(!$ok){throw new RuntimeException($message);}++$checks;}
function scan_call($route,$data=array(),$type='tncp_scan_test'){$r=new WP_REST_Request('POST','/tncp/v1/'.$route.'/'.$type);foreach($data as $k=>$v){$r->set_param($k,$v);}return rest_do_request($r);}
register_post_type('tncp_scan_test',array('public'=>true,'hierarchical'=>true));
register_post_type('tncp_admin_test',array('public'=>true,'publicly_queryable'=>false));
register_post_type('tncp_front_test',array('public'=>false,'publicly_queryable'=>true));
$old=get_option('tncp_plan_tncp_scan_test',null);$old_patterns=get_option('tncp_patterns',null);
try {
 delete_option('tncp_plan_tncp_scan_test');
 scan_assert(!isset(tncp_types()['tncp_admin_test']),'Public but not front-end queryable is excluded');
 scan_assert(isset(tncp_types()['tncp_front_test']),'Explicit front-end queryable type is included');
 scan_assert(isset(tncp_types()['post'],tncp_types()['page']),'Built-in front-end post types remain');
 scan_assert(403===scan_call('refresh',array('revision'=>0,'scan'=>true),'tncp_admin_test')->get_status(),'Hidden type REST rejected');
 foreach(array(array('Parent','parent',0,'publish'),array('Child','child',0,'publish'),array('','',0,'draft'),array('Gone','gone',0,'trash')) as $v){$ids[]=wp_insert_post(array('post_type'=>'tncp_scan_test','post_title'=>$v[0],'post_name'=>$v[1],'post_parent'=>$v[2],'post_status'=>$v[3]));}
 wp_update_post(array('ID'=>$ids[1],'post_parent'=>$ids[0]));
 wp_update_post(array('ID'=>$ids[1],'post_content'=>'Example body'));
 $image=wp_insert_post(array('post_type'=>'attachment','post_status'=>'inherit','post_title'=>'Test image','post_mime_type'=>'image/png'));$ids[]=$image;update_post_meta($ids[1],'_thumbnail_id',$image);
 $posts=array_column(tncp_catalog('tncp_scan_test'),null,'id');
 scan_assert($posts[$ids[1]]['has_content'] && $posts[$ids[1]]['has_featured_image'],'Mapped content/image flags both present');
 scan_assert(!$posts[$ids[0]]['has_content'] && !$posts[$ids[0]]['has_featured_image'],'Empty mapped content/image flags both absent');
 update_post_meta($ids[1],'_tncp_template','custom');update_post_meta($ids[1],'_tncp_flags',array('local'=>true));
 $result=scan_call('refresh',array('revision'=>0,'scan'=>true,'preserve_pending'=>true));
 scan_assert(200===$result->get_status(),'Scan completes');$plan=$result->get_data();
 scan_assert(3===count($plan['rows']),'All non-trashed existing posts added, including draft');
 scan_assert(array('mapped'=>3,'planned'=>3)===tncp_plan_counts('tncp_scan_test'),'Complete counts');
 $child=array_values(array_filter($plan['rows'],fn($r)=>$r['post_id']===$ids[1]))[0];
 scan_assert('post:'.$ids[0]===$child['parent'] && 'custom'===$child['template'] && $child['flags']['local'],'Scan carries hierarchy and planning metadata');
 $original_ids=array_column($plan['rows'],'id');
 $result=scan_call('save',array('revision'=>$plan['revision'],'rows'=>$plan['rows']));
 scan_assert(200===$result->get_status(),'Untitled draft can be retained when saving');$plan=$result->get_data();
 $plan=scan_call('refresh',array('revision'=>$plan['revision'],'scan'=>true,'preserve_pending'=>true))->get_data();
 scan_assert($original_ids===array_column($plan['rows'],'id'),'Repeated scan creates no duplicate rows');
 $plan['rows'][0]['title']='Planned rename';$plan['rows'][0]['confirmed']['title']=true;
 $plan=scan_call('save',array('revision'=>$plan['revision'],'rows'=>$plan['rows']))->get_data();
 $plan=scan_call('refresh',array('revision'=>$plan['revision'],'scan'=>true,'preserve_pending'=>true))->get_data();
 scan_assert('Planned rename'===$plan['rows'][0]['title'],'Scan preserves staged changes');
 scan_assert('Parent'===get_post($ids[0])->post_title,'Scan never mutates WordPress posts');
 // An existing newly discovered slug joins a planned item, preserving its intent.
 $row=array('id'=>'planned','title'=>'Planned extra','slug'=>'extra','parent'=>'','template'=>'single','flags'=>array(),'post_id'=>0,'confirmed'=>array());
 $plan['rows'][]=$row;$plan=scan_call('save',array('revision'=>$plan['revision'],'rows'=>$plan['rows']))->get_data();
 $extra=wp_insert_post(array('post_type'=>'tncp_scan_test','post_title'=>'Existing extra','post_name'=>'extra','post_status'=>'publish'));$ids[]=$extra;
 $plan=scan_call('refresh',array('revision'=>$plan['revision'],'scan'=>true,'preserve_pending'=>true))->get_data();
 $row=array_values(array_filter($plan['rows'],fn($r)=>$r['id']==='planned'))[0];
 scan_assert($extra===$row['post_id'] && 'Planned extra'===$row['title'] && 4===count($plan['rows']),'Unique slug maps existing row without overwriting intent');
 foreach($plan['rows'] as &$pending){if($pending['id']==='planned'){$pending['confirmed']['title']=true;}}unset($pending);
 // Matching a pristine scanned destination absorbs only its automatic row.
 $row=array('id'=>'proposal','title'=>'Child proposal','slug'=>'child-proposal','parent'=>'','template'=>'single','flags'=>array(),'post_id'=>0,'confirmed'=>array());
 $plan['rows'][]=$row;$plan=scan_call('save',array('revision'=>$plan['revision'],'rows'=>$plan['rows']))->get_data();
 $catalog=tncp_catalog('tncp_scan_test');$target=array_values(array_filter($catalog,fn($p)=>$p['id']===$ids[1]))[0];
 $result=scan_call('resolve',array('revision'=>$plan['revision'],'row_id'=>'proposal','decision'=>'destination','target_id'=>$ids[1],'target_snapshot'=>array_intersect_key($target,array_flip(array('title','slug','parent','planning')))));
 scan_assert(200===$result->get_status(),'Scan preserves reconciliation with existing candidates');$plan=$result->get_data()['plan'];
 scan_assert(4===count($plan['rows']) && 1===count(array_filter($plan['rows'],fn($r)=>$r['post_id']===$ids[1])),'Reconciliation keeps only one linked row');
 $patterns=tncp_patterns_data();
 $own=array_values(array_filter($patterns['rows'],fn($r)=>$r['type']==='tncp_scan_test'));
 scan_assert(4===array_sum(array_column($own,'count')),'Patterns count each saved content item once');
 $entry=array_values(array_filter($own,fn($r)=>$r['key']==='tncp_scan_test-1-custom-1'))[0];
 scan_assert(1===$entry['count'] && 'todo'===$entry['status'],'Pattern key and default status');
 $entry['description']='Layout <b>example</b>';$entry['status']='in-progress';$entry['post_id']=$ids[1];
 $request=new WP_REST_Request('POST','/tncp/v1/patterns');$request->set_param('revision',$patterns['revision']);$request->set_param('rows',array($entry));
 $saved_patterns=rest_do_request($request);
 scan_assert(200===$saved_patterns->get_status(),'Save XP pattern metadata');
 $entries=array_column($saved_patterns->get_data()['rows'],null,'key');$stored=$entries[$entry['key']];
 scan_assert('Layout example'===$stored['description'] && 'in-progress'===$stored['status'] && $ids[1]===$stored['post_id'],'Description, status and example persist');
 scan_assert(409===rest_do_request($request)->get_status(),'Pattern stale revision rejected');
 $request->set_param('revision',$saved_patterns->get_data()['revision']);$entry['status']='invalid';$request->set_param('rows',array($entry));
 scan_assert(400===rest_do_request($request)->get_status(),'Invalid pattern status rejected');
 $entry['status']='done';$entry['post_id']=$ids[3];$request->set_param('rows',array($entry));
 scan_assert(400===rest_do_request($request)->get_status(),'Trashed example rejected');
 wp_set_current_user(0);scan_assert(rest_do_request(new WP_REST_Request('GET','/tncp/v1/patterns'))->get_status()>=400,'Patterns require authentication');wp_set_current_user(1);
 $duplicate=wp_insert_post(array('post_type'=>'tncp_scan_test','post_title'=>'Another child','post_name'=>'child','post_status'=>'publish'));$ids[]=$duplicate;
 scan_assert('child'===get_post($duplicate)->post_name,'Native hierarchy permits matching slugs under different parents');
 $plan=scan_call('refresh',array('revision'=>$plan['revision'],'scan'=>true,'preserve_pending'=>true))->get_data();
 scan_assert(200===scan_call('save',array('revision'=>$plan['revision'],'rows'=>$plan['rows']))->get_status(),'Scanned native shared slugs remain saveable');
 echo "PASS: $checks scan and front-end visibility checks\n";
} finally {wp_set_current_user(1);if(null===$old_patterns){delete_option('tncp_patterns');}else{update_option('tncp_patterns',$old_patterns,false);}foreach(array_reverse($ids) as $id){wp_delete_post($id,true);}if(null===$old){delete_option('tncp_plan_tncp_scan_test');}else{update_option('tncp_plan_tncp_scan_test',$old,false);}}
