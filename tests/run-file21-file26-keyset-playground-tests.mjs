import path from 'node:path';
import { runCLI } from '@wp-playground/cli';

// Genuine ephemeral WordPress runtime (Playground/SQLite), not Hostinger Live.
// Executes the real File21 source connector after plugin activation.
let server;
const assert = (v, message) => { if (!v) throw new Error(message); };
const ids = (rows) => (rows || []).map((x) => Number(x.object_id));

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
  const phpCode = [
    "$ids = array();",
    "for ($i = 1; $i <= 4; $i++) {",
    "  $id = wp_insert_post( array( 'post_type'=>'post', 'post_status'=>'publish', 'post_author'=>1, 'post_title'=>'File21 Keyset Test '.$i, 'post_content'=>'Verified clinical education sample '.$i ) );",
    "  if ( is_wp_error($id) || !$id ) { echo wp_json_encode(array('error'=>'insert_failed')); return; }",
    "  update_post_meta( $id, \\Sabri\\HomeNewsFeed\\PostMetadata::META_REVIEW_STATE, 'approved' );",
    "  update_post_meta( $id, \\Sabri\\HomeNewsFeed\\PostMetadata::META_VISIBILITY, 'public' );",
    "  $ids[] = (int) $id;",
    "}",
    "$prior_id = min($ids) - 1;",
    "$cursor = $prior_id > 0 ? 'v1:id:' . $prior_id : '';",
    "$first = \\Sabri\\HomeNewsFeed\\SearchProviderRegistry::file26_list_batch($cursor, 2);",
    "wp_update_post( array( 'ID'=>$ids[0], 'post_status'=>'draft' ) );",
    "$second = \\Sabri\\HomeNewsFeed\\SearchProviderRegistry::file26_list_batch($first['next_cursor'], 2);",
    "$retry = \\Sabri\\HomeNewsFeed\\SearchProviderRegistry::file26_list_batch($first['next_cursor'], 2);",
    "$legacy = \\Sabri\\HomeNewsFeed\\SearchProviderRegistry::file26_list_batch('2', 2);",
    "$partial = \\Sabri\\HomeNewsFeed\\SearchProviderRegistry::file26_list_batch('', 2, array('connector'=>'file21-publication','object_id'=>(string)$ids[1]));",
    "$out = array(",
    "  'ids'=>$ids,",
    "  'first'=>array('items'=>array_map(static function($x){return (int)$x['object_id'];},$first['items']), 'cursor'=>$first['next_cursor']),",
    "  'second'=>array('items'=>array_map(static function($x){return (int)$x['object_id'];},$second['items']), 'cursor'=>$second['next_cursor']),",
    "  'retry'=>array_map(static function($x){return (int)$x['object_id'];},$retry['items']),",
    "  'legacy_error'=>is_wp_error($legacy)?$legacy->get_error_code():'missing',",
    "  'partial_error'=>is_wp_error($partial)?$partial->get_error_code():'missing',",
    ");",
    "echo wp_json_encode($out);",
  ].join('\n');
  const response = await server.playground.run({ code: '<?php require "/wordpress/wp-load.php"; ' + phpCode });
  if (response.errors && String(response.errors).trim()) throw new Error('WordPress PHP failed: ' + response.errors);
  const raw = String(response.text || '').trim();
  const v = JSON.parse(raw);
  assert(!v.error && Array.isArray(v.ids) && v.ids.length === 4, 'WordPress source sample creation failed: ' + raw);
  assert(JSON.stringify(v.first.items) === JSON.stringify(v.ids.slice(0, 2)), 'First WordPress batch IDs incorrect: ' + raw);
  assert(JSON.stringify(v.second.items) === JSON.stringify(v.ids.slice(2)), 'Draft transition skipped a surviving WordPress ID: ' + raw);
  assert(JSON.stringify(v.second.items) === JSON.stringify(v.retry), 'WordPress retry not idempotent: ' + raw);
  assert(v.first.cursor === 'v1:id:' + v.ids[1], 'First WordPress cursor incorrect: ' + raw);
  assert(v.second.cursor === 'v1:id:' + v.ids[3], 'Second WordPress cursor incorrect: ' + raw);
  assert(v.legacy_error === 'file21_file26_legacy_cursor_rejected', 'WordPress legacy offset was accepted: ' + raw);
  assert(v.partial_error === 'file21_file26_unsupported_scope', 'WordPress partial scope was broadened: ' + raw);
  console.log('PASS: File 21 keyset source query on real ephemeral WordPress Playground.');
} finally {
  if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
