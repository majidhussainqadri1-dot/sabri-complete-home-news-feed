<?php
/** Lean WordPress stubs for deterministic Phase 5 contract tests. */
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'HOUR_IN_SECONDS' ) ) define( 'HOUR_IN_SECONDS', 3600 );
if ( ! defined( 'DAY_IN_SECONDS' ) ) define( 'DAY_IN_SECONDS', 86400 );
if ( ! defined( 'SABRI_HNF_VERSION' ) ) define( 'SABRI_HNF_VERSION', '1.0.0' );
if ( ! defined( 'SABRI_HNF_SCHEMA_VERSION' ) ) define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );
if ( ! defined( 'SABRI_HNF_PATH' ) ) define( 'SABRI_HNF_PATH', dirname( __DIR__ ) . '/' );
if ( ! defined( 'SABRI_HNF_URL' ) ) define( 'SABRI_HNF_URL', 'https://example.test/wp-content/plugins/sabri-complete-home-news-feed/' );
if ( ! function_exists( '__' ) ) { function __( $value ) { return $value; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $value ) { return $value; } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url_raw' ) ) { function esc_url_raw( $value, $schemes = null ) { unset( $schemes ); return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : ''; } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $value ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $value ); } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); } }
if ( ! function_exists( 'wp_salt' ) ) { function wp_salt() { return 'phase5-test-salt'; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return isset( $GLOBALS['phase5_user_id'] ) ? (int) $GLOBALS['phase5_user_id'] : 1; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $capability ) { return ! isset( $GLOBALS['phase5_caps'][ $capability ] ) || (bool) $GLOBALS['phase5_caps'][ $capability ]; } }
if ( ! function_exists( 'user_can' ) ) { function user_can( $user_id, $capability ) { unset( $user_id ); return current_user_can( $capability ); } }
if ( ! function_exists( 'is_user_logged_in' ) ) { function is_user_logged_in() { return get_current_user_id() > 0; } }
if ( ! function_exists( 'get_post_type' ) ) { function get_post_type() { return 'sabri_editorial_news'; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['phase5_options'] ?? array() ) ? $GLOBALS['phase5_options'][ $key ] : $default; } }
if ( ! function_exists( 'update_option' ) ) { function update_option( $key, $value ) { $GLOBALS['phase5_options'][ $key ] = $value; return true; } }
if ( ! function_exists( 'delete_option' ) ) { function delete_option( $key ) { unset( $GLOBALS['phase5_options'][ $key ] ); return true; } }
if ( ! function_exists( 'wp_generate_uuid4' ) ) { function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '' ) { return 'https://example.test' . $path; } }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_shortcode' ) ) { function add_shortcode() {} }
