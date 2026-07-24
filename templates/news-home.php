<?php
/** Public Editorial News landing body. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$components = isset( $components ) && is_array( $components ) ? $components : array();
$component_titles = array(
	'featured'                       => __( 'Featured Story', 'sabri-complete-home-news-feed' ),
	'latest'                         => __( 'Latest News', 'sabri-complete-home-news-feed' ),
	'editors-picks'                  => __( 'Editor’s Picks', 'sabri-complete-home-news-feed' ),
	'research'                       => __( 'Research News', 'sabri-complete-home-news-feed' ),
	'classical-homeopathy'           => __( 'Classical Homeopathy', 'sabri-complete-home-news-feed' ),
	'public-health'                  => __( 'Public Health', 'sabri-complete-home-news-feed' ),
	'homeopathy-education'           => __( 'Homeopathy Education', 'sabri-complete-home-news-feed' ),
	'platform-news'                  => __( 'Platform News', 'sabri-complete-home-news-feed' ),
	'founder-updates'                => __( 'Founder Updates', 'sabri-complete-home-news-feed' ),
	'worldwide-health-developments'  => __( 'Worldwide Health Developments', 'sabri-complete-home-news-feed' ),
	'recently-updated'               => __( 'Recently Updated and Corrected News', 'sabri-complete-home-news-feed' ),
);
?>
<main id="main-content" class="sabri-news sabri-news-home">
	<header class="sabri-news-home__header">
		<p class="sabri-news-eyebrow"><?php echo esc_html__( 'Editorial News', 'sabri-complete-home-news-feed' ); ?></p>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html__( 'Verified public reporting, research, education, and worldwide developments.', 'sabri-complete-home-news-feed' ); ?></p>
	</header>
	<section class="sabri-news-home__search" aria-labelledby="sabri-news-search-title">
		<h2 id="sabri-news-search-title"><?php echo esc_html__( 'Search and filter News', 'sabri-complete-home-news-feed' ); ?></h2>
		<?php echo $filter_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
	<?php if ( ! $components ) : ?>
		<?php echo $empty_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<?php foreach ( $components as $component ) : ?>
			<?php
			$component_key = isset( $component['key'] ) ? sanitize_key( $component['key'] ) : '';
			if ( '' === $component_key || ! isset( $component_titles[ $component_key ] ) ) { continue; }
			$items = ! empty( $component['items'] ) && is_array( $component['items'] ) ? $component['items'] : array();
			if ( ! $items && ! in_array( $component_key, array( 'featured', 'latest' ), true ) ) { continue; }
			$section_id = 'sabri-news-section-' . sanitize_html_class( $component_key );
			?>
			<section class="sabri-news-home__section sabri-news-home__section--<?php echo esc_attr( sanitize_html_class( $component_key ) ); ?>" aria-labelledby="<?php echo esc_attr( $section_id ); ?>">
				<header class="sabri-news-home__section-header">
					<h2 id="<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $component_titles[ $component_key ] ); ?></h2>
					<?php if ( ! empty( $component['view_all_url'] ) ) : ?><a href="<?php echo esc_url( $component['view_all_url'] ); ?>"><?php echo esc_html__( 'View all', 'sabri-complete-home-news-feed' ); ?></a><?php endif; ?>
				</header>
				<?php if ( $items ) : ?>
					<div class="sabri-news-grid<?php echo 'featured' === $component_key ? ' sabri-news-grid--featured' : ''; ?>">
						<?php foreach ( $items as $item ) { echo \Sabri\HomeNewsFeed\NewsPublicRuntime::render_card( $item ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php else : ?>
					<?php echo $empty_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</main>
