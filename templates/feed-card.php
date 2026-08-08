<?php
/**
 * Feed card template.
 *
 * @package SabriCompleteHomeNewsFeed
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
$author_projection = class_exists( '\\Sabri\\HomeNewsFeed\\CanonicalIdentityAdapter' ) ? \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::public_projection( $author_id ) : array();
$author_profile_url = isset( $author_projection['profile_url'] ) ? (string) $author_projection['profile_url'] : '';
$author_specialty = isset( $author_projection['specialty'] ) ? (string) $author_projection['specialty'] : '';
$author_country = isset( $author_projection['country'] ) ? (string) $author_projection['country'] : '';
$author_clinic = isset( $author_projection['clinic_name'] ) ? (string) $author_projection['clinic_name'] : '';
$stored_type = class_exists( '\\Sabri\\HomeNewsFeed\\PostMetadata' ) ? \Sabri\HomeNewsFeed\PostMetadata::feed_type( $post_id ) : '';
$canonical_type = class_exists( '\\Sabri\\HomeNewsFeed\\Taxonomies' ) ? \Sabri\HomeNewsFeed\Taxonomies::canonical_feed_type( $stored_type ) : $stored_type;
$health_types = array( 'clinical-education', 'clinical-case', 'research', 'nutrition', 'public-health-education', 'pathology', 'anatomy', 'principles-of-hygiene', 'islamic-spiritual-healing' );
if ( '' === $disclaimer && in_array( $canonical_type, $health_types, true ) ) {
	$disclaimer = __( 'Educational content only; it is not an emergency service or a substitute for individualized professional care.', 'sabri-complete-home-news-feed' );
}
?>
<article class="sabri-hnf-card" id="sabri-hnf-post-<?php echo esc_attr( $post_id ); ?>">
	<header class="sabri-hnf-card__header">
		<div class="sabri-hnf-card__avatar">
			<?php if ( '' !== $author_profile_url ) : ?><a href="<?php echo esc_url( $author_profile_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s profile', 'sabri-complete-home-news-feed' ), $author_name ) ); ?>"><?php endif; ?>
			<?php echo $author_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( '' !== $author_profile_url ) : ?></a><?php endif; ?>
		</div>
		<div class="sabri-hnf-card__byline">
			<div class="sabri-hnf-card__author">
				<?php if ( '' !== $author_profile_url ) : ?><a href="<?php echo esc_url( $author_profile_url ); ?>"><?php echo esc_html( $author_name ); ?></a><?php else : ?><span><?php echo esc_html( $author_name ); ?></span><?php endif; ?>
				<?php foreach ( $badges as $badge ) : ?><span class="sabri-hnf-badge"><?php echo esc_html( $badge ); ?></span><?php endforeach; ?>
			</div>
			<div class="sabri-hnf-card__meta">
				<span><?php echo esc_html( $author_label ); ?></span>
				<?php if ( '' !== $author_specialty ) : ?><span><?php echo esc_html( $author_specialty ); ?></span><?php endif; ?>
				<?php if ( '' !== $author_country ) : ?><span><?php echo esc_html( $author_country ); ?></span><?php endif; ?>
				<?php if ( '' !== $author_clinic ) : ?><span><?php echo esc_html( $author_clinic ); ?></span><?php endif; ?>
				<?php if ( '' !== $feed_type ) : ?><span><?php echo esc_html( $feed_type ); ?></span><?php endif; ?>
				<time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( trim( $date . ' ' . $time ) ); ?></time>
				<?php if ( $edited ) : ?><span><?php esc_html_e( 'Edited', 'sabri-complete-home-news-feed' ); ?></span><?php endif; ?>
				<?php if ( 'public' !== $visibility ) : ?><span><?php echo esc_html( ucwords( str_replace( '-', ' ', $visibility ) ) ); ?></span><?php endif; ?>
			</div>
		</div>
	</header>
	<?php if ( '' !== $featured ) : ?><figure class="sabri-hnf-card__media"><?php echo $featured; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure><?php endif; ?>
	<div class="sabri-hnf-card__body">
		<?php if ( '' !== $title ) : ?><h3><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3><?php endif; ?>
		<?php if ( '' !== $excerpt ) : ?><p><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
	</div>
	<?php echo $gallery; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( ! empty( $topics ) || ! empty( $categories ) || ! empty( $hashtags ) ) : ?><footer class="sabri-hnf-card__terms"><?php foreach ( array_merge( $topics, $categories, $hashtags ) as $term_label ) : ?><span><?php echo esc_html( $term_label ); ?></span><?php endforeach; ?></footer><?php endif; ?>
	<?php if ( '' !== $disclaimer ) : ?><aside class="sabri-hnf-card__disclaimer"><?php echo esc_html( $disclaimer ); ?></aside><?php endif; ?>
	<?php if ( class_exists( '\\Sabri\\HomeNewsFeed\\FeedUserAgency' ) ) { echo \Sabri\HomeNewsFeed\FeedUserAgency::card_controls( $post_id ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( class_exists( '\\Sabri\\HomeNewsFeed\\NextGenerationFeed' ) ) { echo \Sabri\HomeNewsFeed\NextGenerationFeed::render_card_extensions( $post_id ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo \Sabri\HomeNewsFeed\PollRuntime::render_poll( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo \Sabri\HomeNewsFeed\SocialRuntime::render_action_bar( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<a class="sabri-hnf-card__read-more" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Read More', 'sabri-complete-home-news-feed' ); ?></a>
</article>