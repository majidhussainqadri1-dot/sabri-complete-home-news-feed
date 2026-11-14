import path from 'node:path';
import { runCLI } from '@wp-playground/cli';

// Isolated real WordPress core SQL via Playground; NEVER Hostinger Live.
let server;
const check = (truth, detail, source = '') => {
  if (!truth) throw new Error(detail + (source ? ' | ' + source : ''));
};
try {
  server = await runCLI({
    command: 'server',
    php: process.env.SABRI_PLAYGROUND_PHP || '8.3',
    wp: process.env.SABRI_PLAYGROUND_WP || 'latest',
    debug: true,
    login: false,
    mount: [{
      hostPath: path.resolve('.'),
      vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed',
    }],
    blueprint: { steps: [{
      step: 'activatePlugin',
      pluginPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed/sabri-complete-home-news-feed.php',
    }] },
  });
  const lines = [
    "$reader=wp_insert_user(array('user_login'=>'issue67_reader','user_pass'=>'isolated-fixture-only','user_email'=>'issue67@example.test','role'=>'subscriber'));",
    "if (is_wp_error($reader)){echo wp_json_encode(array('error'=>'reader_create_failed'));return;}",
    "$author=get_user_by('login','admin');$author_id=$author?(int)$author->ID:1;",
    "$mk=static function($status,$audience,$review,$title)use($author_id){",
    "  $id=wp_insert_post(array('post_type'=>'post','post_status'=>$status,'post_author'=>$author_id,'post_title'=>$title,'post_content'=>'Deterministic Feed read source'));",
    "  if(is_wp_error($id)||!$id)return 0;",
    "  update_post_meta($id,\\Sabri\\HomeNewsFeed\\PostMetadata::META_VISIBILITY,$audience);",
    "  update_post_meta($id,\\Sabri\\HomeNewsFeed\\PostMetadata::META_REVIEW_STATE,$review);",
    "  return (int)$id;",
    "};",
    "$pub=$mk('publish','public','approved','Public approved');",
    "$mem=$mk('publish','members','approved','Members approved');",
    "$draft=$mk('draft','public','approved','Never published');",
    "$pending=$mk('publish','public','pending','Unapproved');",
    "$private=$mk('publish','private','approved','Author-only private');",
    "$GLOBALS['attack67']='widen';",
    "add_filter('sabri_hnf_feed_query_args',static function($args){",
    "  if('invalid'===$GLOBALS['attack67'])return 'bad-filter-return';",
    "  return array('post_type'=>'any','post_status'=>'any','posts_per_page'=>10000,'paged'=>1,'suppress_filters'=>true,'has_password'=>true,'meta_query'=>array(), 'orderby'=>'rand');",
    "},999,1);",
    "$fetch=static function($uid){",
    "  wp_set_current_user($uid);",
    "  $query=\\Sabri\\HomeNewsFeed\\FeedQuery::wp_query_args('latest',1,20,$uid,\\Sabri\\HomeNewsFeed\\Settings::get());",
    "  $wpq=new \\WP_Query($query);",
    "  return array('args'=>$query,'ids'=>array_map(static function($post){return (int)$post->ID;},(array)$wpq->posts),'found'=>(int)$wpq->found_posts);",
    "};",
    "$anon=$fetch(0);$member=$fetch((int)$reader);",
    "update_user_meta($reader,\\Sabri\\HomeNewsFeed\\FeedUserAgency::META_KEY,array('hidden_posts'=>array($pub)));",
    "$muted=$fetch((int)$reader);",
    "$GLOBALS['attack67']='invalid';$malformed=$fetch((int)$reader);",
    "echo wp_json_encode(array('pub'=>$pub,'members'=>$mem,'draft'=>$draft,'pending'=>$pending,'private'=>$private,",
    "  'anon'=>$anon,'member'=>$member,'muted'=>$muted,'malformed'=>$malformed));",
  ];
  const result = await server.playground.run({ code: '<?php require "/wordpress/wp-load.php"; ' + lines.join('\n') });
  if (result.errors && String(result.errors).trim()) throw new Error('WordPress runtime: ' + result.errors);
  const raw = String(result.text || '').trim();
  const data = JSON.parse(raw);
  check(!data.error, 'WordPress source fixture', raw);
  for (const row of [data.anon, data.member]) {
    check(row.args.post_type === 'post' && JSON.stringify(row.args.post_status) === '["publish"]',
      'Native WP_Query must not accept attacker post type/status', raw);
    check(row.args.suppress_filters === false && row.args.has_password === false &&
      row.args.posts_per_page === 20, 'Native source filters, no-password and bounded SQL must survive attacker', raw);
    for (const id of [data.draft, data.pending, data.private]) {
      check(!row.ids.includes(id), 'Native WP_Query returned draft, unreviewed, or private ID ' + id, raw);
    }
  }
  check(data.anon.ids.includes(data.pub) && !data.anon.ids.includes(data.members),
    'Anonymous must see only the published reviewed public record', raw);
  check(data.member.ids.includes(data.pub) && data.member.ids.includes(data.members),
    'Logged-in viewer must retain authorized members-scope record', raw);
  check(!data.muted.ids.includes(data.pub) && data.muted.ids.includes(data.members),
    'Current-user private hidden-post preference must survive late extension', raw);
  check(JSON.stringify(data.malformed.args.post__in) === '[0]' && data.malformed.ids.length === 0,
    'Malformed late extension must become zero-result query, not all posts', raw);
  console.log('PASS: real ephemeral WordPress SQL enforces review, audience, status, hidden posts and fail-closed late filters.');
} finally {
  if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
