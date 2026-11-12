import path from 'node:path';
import { runCLI } from '@wp-playground/cli';

// An actual isolated WordPress plugin activation, NOT Hostinger staging or Live.
let server;
const check = (truth, label) => { if (!truth) throw new Error(label); };
try {
  server = await runCLI({
    command: 'server',
    php: process.env.SABRI_PLAYGROUND_PHP || '8.3',
    wp: process.env.SABRI_PLAYGROUND_WP || 'latest',
    debug: true,
    login: false,
    mount: [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }],
    blueprint: { steps: [{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed/sabri-complete-home-news-feed.php' }] },
  });
  const lines = [
    "$ids = array();",
    "for ($n=1;$n<=65;$n++) {",
    "  $id = wp_insert_post(array('post_type'=>'post','post_status'=>'publish','post_author'=>1,'post_date'=>'2026-10-10 10:00:00','post_date_gmt'=>'2026-10-10 10:00:00','post_title'=>'File21 Profile Cursor Sample '.$n,'post_content'=>'Safe educational example'));",
    "  if (is_wp_error($id) || !$id) { echo wp_json_encode(array('error'=>'insert_failed','n'=>$n));return; }",
    "  update_post_meta($id, \\Sabri\\HomeNewsFeed\\PostMetadata::META_REVIEW_STATE, 'approved');",
    "  update_post_meta($id, \\Sabri\\HomeNewsFeed\\PostMetadata::META_VISIBILITY, 'public');",
    "  $ids[] = (int)$id;",
    "}",
    "$sort=$ids;usort($sort,static function($a,$b){return strcmp((string)$b,(string)$a);});",
    "$to_ids=static function($items){return array_map(static function($x){return (int)substr($x['canonical_id'],strlen('file21-post:'));},$items);};",
    "$cursor=static function($x){return array('t'=>gmdate('Y-m-d H:i:s',strtotime($x['published_at'])),'i'=>'file21:'.$x['canonical_id']);};",
    "$first=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('limit'=>21,'viewer_id'=>0));",
    "if (is_wp_error($first) || count($first)<20) { echo wp_json_encode(array('error'=>'first_failed','code'=>is_wp_error($first)?$first->get_error_code():count($first)));return; }",
    "$second=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('limit'=>21,'viewer_id'=>0,'cursor'=>$cursor($first[19])));",
    "if (is_wp_error($second) || count($second)<20) { echo wp_json_encode(array('error'=>'second_failed','code'=>is_wp_error($second)?$second->get_error_code():count($second)));return; }",
    "$third=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('limit'=>21,'viewer_id'=>0,'cursor'=>$cursor($second[19])));",
    "if (is_wp_error($third)) { echo wp_json_encode(array('error'=>'third_failed','code'=>$third->get_error_code()));return; }",
    "$retry=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('limit'=>21,'viewer_id'=>0,'cursor'=>$cursor($first[19])));",
    "update_post_meta($sort[0],\\Sabri\\HomeNewsFeed\\PostMetadata::META_VISIBILITY,'private');",
    "$private=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('limit'=>21,'viewer_id'=>0));",
    "wp_update_post(array('ID'=>$sort[1],'post_status'=>'draft'));",
    "$draft=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('limit'=>21,'viewer_id'=>0));",
    "$bad=\\Sabri\\HomeNewsFeed\\ProfileTimeline::file03_provider_items(array(),1,array('cursor'=>array('t'=>'invalid','i'=>'file21:file21-post:1')));",
    "echo wp_json_encode(array('first'=>$to_ids($first),'second'=>$to_ids($second),'third'=>$to_ids($third),'retry'=>$to_ids($retry),'sort'=>$sort,'private'=>$to_ids($private),'draft'=>$to_ids($draft),'private_id'=>$sort[0],'draft_id'=>$sort[1],'bad'=>is_wp_error($bad)?$bad->get_error_code():'none'));",
  ];
  const response = await server.playground.run({ code: '<?php require "/wordpress/wp-load.php"; ' + lines.join('\n') });
  if (response.errors && String(response.errors).trim()) throw new Error('WordPress PHP: ' + response.errors);
  const raw = String(response.text || '').trim();
  const x = JSON.parse(raw);
  check(!x.error, 'WordPress owner query error: ' + raw);
  check(JSON.stringify(x.first) === JSON.stringify(x.sort.slice(0,21)), 'First same-time WordPress page not sorted: ' + raw);
  check(JSON.stringify(x.second) === JSON.stringify(x.sort.slice(20,41)), 'Second WordPress page lost authorized items: ' + raw);
  check(JSON.stringify(x.third) === JSON.stringify(x.sort.slice(40,61)), 'Third WordPress page lost authorized items: ' + raw);
  check(JSON.stringify(x.retry) === JSON.stringify(x.second), 'Cursor retry differs from same source snapshot.');
  check(!x.private.includes(x.private_id) && !x.draft.includes(x.draft_id), 'Private or draft post crossed File03 projection boundary.');
  check(x.bad === 'file21_file03_cursor_invalid', 'Malformed provider cursor was not rejected.');
  console.log('PASS: WordPress Playground source cursor, 3 pages, deterministic retry, privacy and draft.');
} finally {
  if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
