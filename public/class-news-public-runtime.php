<?php
/**
 * Public Editorial News rendering runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns public rendering context and escaped template boundaries. */
final class NewsPublicRuntime {
	/** @var array<string,mixed> */
	private static $context = array();

	/** Register public helpers. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		}
	}

	/** Replace the current request context. */
	public static function set_context( array $context ) {
		self::$context = $context;
	}

	/** Read the current request context. */
	public static function context() {
		return self::$context;
	}

	/** Render the current archive template body. */
	public static function render_archive() {
		$context = self::$context;
		$result = isset( $context['result'] ) && is_array( $context['result'] ) ? $context['result'] : array();
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		Assets::enqueue_news();

		return self::template(
			'news-archive',
			array(
				'title'      => isset( $context['title'] ) ? $context['title'] : __( 'News', 'sabri-complete-home-news-feed' ),
				'description'=> isset( $context['description'] ) ? $context['description'] : '',
				'items'      => isset( $data['items'] ) ? $data['items'] : array(),
				'filters'    => isset( $data['filters'] ) ? $data['filters'] : array(),
				'pagination' => self::pagination( $data ),
				'filter_form'=> self::filter_form( isset( $data['filters'] ) ? $data['filters'] : array() ),
				'empty_html' => self::template( 'news-empty-state', array() ),
			)
		);
	}

	/** Render the current single template body. */
	public static function render_single() {
		$context = self::$context;
		$article = isset( $context['article'] ) && is_array( $context['article'] ) ? $context['article'] : array();
		Assets::enqueue_news();
		if ( 'retraction' === ( isset( $article['projection'] ) ? $article['projection'] : '' ) ) {
			return self::template( 'news-retraction-notice', array( 'article' => $article ) );
		}
		return self::template(
			'news-single',
			array(
				'article' => $article,
				'related' => ! empty( $article['id'] ) ? NewsQueryService::related( $article['id'], 4 ) : array(),
			)
		);
	}

	/** Render one normalized News card. */
	public static function render_card( array $item ) {
		if ( 'editorial_news' !== ( isset( $item['item_type'] ) ? $item['item_type'] : '' ) || empty( $item['global_key'] ) ) {
			return '';
		}
		return self::template( 'news-card', array( 'item' => $item ) );
	}

	/** Load a plugin-owned public template. */
	public static function template( $template, array $vars = array() ) {
		$file = SABRI_HNF_PATH . 'templates/' . sanitize_key( $template ) . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		ob_start();
		extract( $vars, EXTR_SKIP );
		include $file;
		return (string) ob_get_clean();
	}

	/** Add stable body classes only on controlled News requests. */
	public static function body_class( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( ! empty( self::$context['route'] ) ) {
			$classes[] = 'sabri-news-route';
			$classes[] = 'sabri-news-route--' . sanitize_html_class( self::$context['route'] );
		}
		return array_values( array_unique( $classes ) );
	}

	/** Accessible pagination. */
	private static function pagination( array $data ) {
		$page = isset( $data['page'] ) ? (int) $data['page'] : 1;
		$max_pages = isset( $data['max_pages'] ) ? (int) $data['max_pages'] : 0;
		if ( $max_pages <= 1 ) {
			return '';
		}
		if ( function_exists( 'paginate_links' ) ) {
			$links = paginate_links(
				array(
					'base'      => self::page_url( '%#%' ),
					'format'    => '',
					'current'   => $page,
					'total'     => $max_pages,
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'sabri-complete-home-news-feed' ),
					'next_text' => __( 'Next', 'sabri-complete-home-news-feed' ),
				)
			);
			return is_string( $links ) ? $links : '';
		}
		return '';
	}

	/** Build a progressive, server-rendered filter form. */
	private static function filter_form( array $filters ) {
		$sections = Phase4Contracts::sections();
		$types = Phase4Contracts::article_types();
		ob_start();
		?>
		<form class="sabri-news-filter" method="get" action="">
			<div class="sabri-news-filter__field">
				<label for="sabri-news-keyword"><?php echo esc_html__( 'Search News', 'sabri-complete-home-news-feed' ); ?></label>
				<input id="sabri-news-keyword" type="search" name="q" maxlength="100" value="<?php echo esc_attr( isset( $filters['keyword'] ) ? $filters['keyword'] : '' ); ?>" />
			</div>
			<div class="sabri-news-filter__field">
				<label for="sabri-news-section"><?php echo esc_html__( 'Section', 'sabri-complete-home-news-feed' ); ?></label>
				<select id="sabri-news-section" name="section">
					<option value=""><?php echo esc_html__( 'All sections', 'sabri-complete-home-news-feed' ); ?></option>
					<?php foreach ( $sections as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"<?php echo isset( $filters['section'] ) && $filters['section'] === $slug ? ' selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="sabri-news-filter__field">
				<label for="sabri-news-type"><?php echo esc_html__( 'Article type', 'sabri-complete-home-news-feed' ); ?></label>
				<select id="sabri-news-type" name="type">
					<option value=""><?php echo esc_html__( 'All types', 'sabri-complete-home-news-feed' ); ?></option>
					<?php foreach ( $types as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"<?php echo isset( $filters['type'] ) && $filters['type'] === $slug ? ' selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<button type="submit"><?php echo esc_html__( 'Apply filters', 'sabri-complete-home-news-feed' ); ?></button>
			<a class="sabri-news-filter__clear" href="<?php echo esc_url( self::page_url( 1, true ) ); ?>"><?php echo esc_html__( 'Clear', 'sabri-complete-home-news-feed' ); ?></a>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/** Build a public page URL while preserving only allow-listed filters. */
	private static function page_url( $page, $clear = false ) {
		$base = isset( self::$context['canonical_base'] ) ? self::$context['canonical_base'] : ( function_exists( 'home_url' ) ? home_url( '/news/' ) : '/news/' );
		if ( $clear ) {
			return $base;
		}
		$args = array();
		$filters = isset( self::$context['result']['data']['filters'] ) ? self::$context['result']['data']['filters'] : array();
		foreach ( array( 'keyword', 'section', 'topic', 'country', 'region', 'type', 'date_from', 'date_to', 'author', 'research', 'corrected', 'retracted' ) as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$args[ 'keyword' === $key ? 'q' : $key ] = $filters[ $key ];
			}
		}
		$args['page'] = $page;
		return function_exists( 'add_query_arg' ) ? add_query_arg( $args, $base ) : $base;
	}
}
