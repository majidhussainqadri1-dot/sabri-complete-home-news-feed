import path from 'node:path';
import { runCLI } from '@wp-playground/cli';

// Ephemeral WordPress: NOT the user's actual Hostinger live/staging site.
let server;
const check = (value, label, evidence = '') => {
  if (!value) throw new Error(label + (evidence ? ': ' + evidence : ''));
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

  const statements = [
    "$author = get_user_by('login', 'admin');",
    "$author_id = $author ? (int) $author->ID : 1;",
    "$reader_id = wp_insert_user(array('user_login'=>'issue65_member_reader','user_pass'=>'isolated-test-password','user_email'=>'issue65-reader@example.test','role'=>'subscriber'));",
    "if (is_wp_error($reader_id)) { echo wp_json_encode(array('error'=>'member_creation_failed','code'=>$reader_id->get_error_code())); return; }",
    "$mk = static function ($visibility, $review, $status, $title) use ($author_id) {",
    "  $id = wp_insert_post(array('post_type'=>'post','post_status'=>$status,'post_author'=>$author_id,'post_title'=>$title,'post_content'=>'Restricted reader experience fixture.'));",
    "  if (is_wp_error($id) || !$id) { return 0; }",
    "  update_post_meta($id,\\Sabri\\HomeNewsFeed\\PostMetadata::META_VISIBILITY,$visibility);",
    "  update_post_meta($id,\\Sabri\\HomeNewsFeed\\PostMetadata::META_REVIEW_STATE,$review);",
    "  return (int)$id;",
    "};",
    "$public = $mk('public','approved','publish','PUBLIC ISSUE 65 CARD');",
    "$member = $mk('members','approved','publish','MEMBERS ISSUE 65 CARD');",
    "$private = $mk('private','approved','publish','PRIVATE ISSUE 65 CARD');",
    "$pending = $mk('public','pending','publish','PENDING ISSUE 65 CARD');",
    "$draft = $mk('public','approved','draft','DRAFT ISSUE 65 CARD');",
    "$settings = array('feed'=>array('show_author_details'=>false,'show_post_type'=>false,'show_media'=>false,'show_disclaimer'=>false));",
    "$render = static function($id) use ($settings) { return \\Sabri\\HomeNewsFeed\\FeedRenderer::render_card($id,$settings); };",
    "$allowed = static function($id){return \\Sabri\\HomeNewsFeed\\FeedRenderer::viewer_can_render_post($id);};",
    "wp_set_current_user(0);",
    "$anon = array('public'=>$allowed($public),'members'=>$allowed($member),'member_card'=>$render($member));",
    "wp_set_current_user($reader_id);",
    "$reader = array('members'=>$allowed($member),'member_card'=>$render($member),'public'=>$allowed($public),",
    "  'private'=>$allowed($private),'pending'=>$allowed($pending),'draft'=>$allowed($draft),",
    "  'share_members'=>\\Sabri\\HomeNewsFeed\\NextGenerationFeed::strict_public_item($member),",
    "  'share_public'=>\\Sabri\\HomeNewsFeed\\NextGenerationFeed::strict_public_item($public));",
    "add_filter('sabri_hnf_network_author_allowed', static function($allowed,$viewer,$author) use($reader_id,$author_id) {return $viewer===$reader_id && $author===$author_id ? false : $allowed;},50,3);",
    "$reader['blocked']=$allowed($public);",
    "wp_set_current_user($author_id);",
    "$owner = array('private'=>$allowed($private),'draft'=>$allowed($draft));",
    "wp_set_current_user(0);",
    "$again = array('member'=>$allowed($member),'member_card'=>$render($member));",
    "echo wp_json_encode(array('anon'=>$anon,'reader'=>$reader,'owner'=>$owner,'again'=>$again));",
  ];
  const response = await server.playground.run({ code: '<?php require "/wordpress/wp-load.php"; ' + statements.join('\n') });
  if (response.errors && String(response.errors).trim()) throw new Error('WordPress PHP failure: ' + response.errors);
  const raw = String(response.text || '').trim();
  const value = JSON.parse(raw);
  check(!value.error, 'Test fixture creation', raw);
  check(value.anon.public === true && value.anon.members === false && value.anon.member_card === '', 'Anonymous may see only public content', raw);
  check(value.reader.members === true && value.reader.public === true &&
    value.reader.member_card.includes('MEMBERS ISSUE 65 CARD'), 'Authorized member must render limited-audience card', raw);
  check(value.reader.private === false && value.reader.pending === false && value.reader.draft === false,
    'Member cannot see private, pending or draft', raw);
  check(value.reader.share_members === false && value.reader.share_public === true,
    'Global public share rule remains strict while Feed card uses viewer scope', raw);
  check(value.reader.blocked === false, 'Current author block disallows viewer Feed card', raw);
  check(value.owner.private === false && value.owner.draft === false,
    'Owner/moderator cannot silently inject private or draft into Home Feed', raw);
  check(value.again.member === false && value.again.member_card === '',
    'Viewer switch restores anonymous privacy, no cross-viewer cached card', raw);

  console.log('PASS: actual ephemeral WordPress scoped Feed card; public share unchanged; status, blocks, viewer switching.');
} finally {
  if (server && typeof server[Symbol.asyncDispose] === 'function') {
    await server[Symbol.asyncDispose]();
  }
}
