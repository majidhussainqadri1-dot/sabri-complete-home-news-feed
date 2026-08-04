<?php
/**
 * Native File 21 read/review/calendar adapter for File 23.
 *
 * File 21 remains the canonical owner of social posts and Editorial News.
 * File 23 receives bounded projections and same-origin native destinations;
 * it does not receive publication bodies, private evidence, or direct table
 * access. Mutating operations remain unavailable until a separately accepted
 * provider release implements and passes the write contracts.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File23PublishingDashboardAdapterRuntime implements \SPDB_Provider_Adapter, \SPDB_Workspace_Provider_Adapter, \SPDB_Review_Calendar_Provider_Adapter {
	private const PROVIDER_KEY = 'sabri_home_news_feed';
	private const CONTRACT     = '2.0.0';
	private const MAX_ITEMS    = 100;

	public function get_provider_key(): string {
		return self::PROVIDER_KEY;
	}

	public function get_provider_name(): string {
		return 'Sabri Complete Home and News Feed';
	}

	public function get_provider_version(): string {
		return defined( 'SABRI_HNF_PACKAGE_VERSION' ) && preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) SABRI_HNF_PACKAGE_VERSION )
			? (string) SABRI_HNF_PACKAGE_VERSION
			: '1.0.3.3';
	}

	public function get_minimum_contract_version(): string {
		return self::CONTRACT;
	}

	public function get_maximum_contract_version(): string {
		return self::CONTRACT;
	}

	public function get_declared_capability_state(): string {
		return 'review_capable';
	}

	public function get_object_types(): array {
		return array( 'social_post', 'editorial_news' );
	}

	public function get_privacy_classifications(): array {
		return array( 'public', 'restricted' );
	}

	public function get_supported_capabilities(): array {
		return array(
			'spdb_view_own_content',
			'spdb_manage_own_content',
			'spdb_view_review_queue',
			'spdb_review_assigned_content',
			'spdb_manage_schedule',
		);
	}

	/** Read projections are implemented; write operations remain fail-closed. */
	public function get_operation_definitions(): array {
		return array();
	}

	public function health_check(): array {
		$ready = defined( 'SABRI_HNF_VERSION' ) && class_exists( __NAMESPACE__ . '\\PostMetadata' );
		return array(
			'status'              => $ready ? 'healthy' : 'unavailable',
			'runtime_version'     => $this->get_provider_version(),
			'package_version'     => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? (string) SABRI_HNF_PACKAGE_VERSION : $this->get_provider_version(),
			'read_projection'     => $ready,
			'review_projection'   => $ready,
			'calendar_projection' => $ready,
			'write_operations'    => false,
		);
	}

	/**
	 * @param array<string,mixed> $query File 23 normalized inventory query.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function list_items( array $query ) {
		$authorization = $this->authorize_read_scope( $query );
		if ( is_wp_error( $authorization ) ) {
			return $authorization;
		}

		$page     = min( 1000, max( 1, (int) ( $query['page'] ?? 1 ) ) );
		$per_page = min( self::MAX_ITEMS, max( 1, (int) ( $query['per_page'] ?? 25 ) ) );
		$args     = $this->base_query_args( $query, $authorization, $page, $per_page );
		$wp_query = new \WP_Query( $args );
		$items    = array();

		foreach ( $wp_query->posts as $post ) {
			$projection = $this->project_post( $post );
			if ( is_array( $projection ) ) {
				$items[] = $projection;
			}
		}

		return array(
			'items'    => $items,
			'total'    => max( 0, (int) $wp_query->found_posts ),
			'has_more' => $page < max( 1, (int) $wp_query->max_num_pages ),
		);
	}

	public function get_item( string $object_type, string $object_id ) {
		if ( ! in_array( $object_type, $this->get_object_types(), true ) || ! preg_match( '/^[1-9][0-9]*$/', $object_id ) ) {
			return new \WP_Error( 'file21_spdb_reference_invalid', __( 'The File 21 object reference is invalid.', 'sabri-complete-home-news-feed' ) );
		}
		$post = get_post( (int) $object_id );
		if ( ! $post instanceof \WP_Post || $this->object_type( $post ) !== $object_type || ! $this->viewer_can_read_post( $post ) ) {
			return new \WP_Error( 'file21_spdb_object_unavailable', __( 'The requested File 21 object is unavailable.', 'sabri-complete-home-news-feed' ) );
		}
		return $this->project_post( $post );
	}

	public function get_allowed_operations( string $object_type, string $object_id ): array {
		unset( $object_type, $object_id );
		return array();
	}

	public function execute_operation( string $operation_key, string $object_type, string $object_id, array $payload ) {
		unset( $operation_key, $object_type, $object_id, $payload );
		return new \WP_Error( 'file21_spdb_write_not_accepted', __( 'File 21 dashboard writes remain disabled until a separately reviewed and accepted write adapter is installed.', 'sabri-complete-home-news-feed' ), array( 'status' => 409 ) );
	}

	/**
	 * @param array<string,mixed> $context Server-derived File 23 context.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_workspace_projection( array $context ) {
		$user_id = (int) ( $context['user_id'] ?? 0 );
		if ( $user_id < 1 || $user_id !== get_current_user_id() ) {
			return new \WP_Error( 'file21_spdb_workspace_forbidden', __( 'The File 21 workspace subject is invalid.', 'sabri-complete-home-news-feed' ) );
		}

		$own_counts       = max( 0, (int) count_user_posts( $user_id, 'post', false ) );
		$news_counts      = post_type_exists( 'sabri_news' ) ? max( 0, (int) count_user_posts( $user_id, 'sabri_news', false ) ) : null;
		$source_timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		$cards = array(
			array(
				'key'              => 'file21_social_posts',
				'label'            => __( 'My Social Posts', 'sabri-complete-home-news-feed' ),
				'value'            => $own_counts,
				'note'             => __( 'Canonical WordPress posts owned by File 21.', 'sabri-complete-home-news-feed' ),
				'priority'         => 'information',
				'data_status'      => 'measured',
				'source_timestamp' => $source_timestamp,
				'scope'            => 'own',
				'owner_user_id'    => $user_id,
			),
		);
		if ( null !== $news_counts ) {
			$cards[] = array(
				'key'              => 'file21_editorial_news',
				'label'            => __( 'My Editorial News', 'sabri-complete-home-news-feed' ),
				'value'            => $news_counts,
				'note'             => __( 'Canonical Editorial News records owned by File 21.', 'sabri-complete-home-news-feed' ),
				'priority'         => 'information',
				'data_status'      => 'measured',
				'source_timestamp' => $source_timestamp,
				'scope'            => 'own',
				'owner_user_id'    => $user_id,
			);
		}

		return array(
			'cards'     => $cards,
			'actions'   => array(),
			'activity'  => array(),
			'alerts'    => array(),
			'profile'   => null,
			'knowledge' => null,
		);
	}

	/**
	 * @param array<string,mixed> $context Server-derived File 23 authority.
	 * @param array<string,mixed> $query   Normalized review filters.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_review_queue( array $context, array $query ) {
		if ( empty( $context['can_review'] ) || (int) ( $context['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new \WP_Error( 'file21_spdb_review_forbidden', __( 'The File 21 review queue is not authorized.', 'sabri-complete-home-news-feed' ) );
		}

		$page      = min( 1000, max( 1, (int) ( $query['page'] ?? 1 ) ) );
		$per_page  = min( self::MAX_ITEMS, max( 1, (int) ( $query['per_page'] ?? 25 ) ) );
		$args      = array(
			'post_type'              => array( 'post', 'sabri_news' ),
			'post_status'            => array( 'draft', 'pending' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'suppress_filters'       => false,
			'update_post_term_cache' => false,
		);
		$wp_query  = new \WP_Query( $args );
		$items     = array();
		foreach ( $wp_query->posts as $post ) {
			$item = $this->review_item( $post, $context );
			if ( is_array( $item ) ) {
				$items[] = $item;
			}
		}
		return array(
			'items'    => $items,
			'total'    => max( 0, (int) $wp_query->found_posts ),
			'has_more' => $page < max( 1, (int) $wp_query->max_num_pages ),
		);
	}

	/**
	 * @param array<string,mixed> $context Server-derived File 23 authority.
	 * @param array<string,mixed> $query   Normalized calendar filters.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_calendar_entries( array $context, array $query ) {
		if ( empty( $context['can_view_calendar'] ) || (int) ( $context['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return new \WP_Error( 'file21_spdb_calendar_forbidden', __( 'The File 21 publishing calendar is not authorized.', 'sabri-complete-home-news-feed' ) );
		}
		$page      = min( 1000, max( 1, (int) ( $query['page'] ?? 1 ) ) );
		$per_page  = min( self::MAX_ITEMS, max( 1, (int) ( $query['per_page'] ?? 25 ) ) );
		$args      = array(
			'post_type'              => array( 'post', 'sabri_news' ),
			'post_status'            => array( 'future' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'suppress_filters'       => false,
			'update_post_term_cache' => false,
		);
		if ( empty( $context['is_founder'] ) ) {
			$args['author'] = (int) $context['user_id'];
		}
		$wp_query  = new \WP_Query( $args );
		$items     = array();
		foreach ( $wp_query->posts as $post ) {
			$item = $this->calendar_item( $post, $context );
			if ( is_array( $item ) ) {
				$items[] = $item;
			}
		}
		return array(
			'items'    => $items,
			'total'    => max( 0, (int) $wp_query->found_posts ),
			'has_more' => $page < max( 1, (int) $wp_query->max_num_pages ),
		);
	}

	/** @return array<string,mixed>|\WP_Error */
	private function authorize_read_scope( array $query ) {
		$user_id = get_current_user_id();
		if ( $user_id < 1 || ! current_user_can( 'spdb_view_own_content' ) ) {
			return new \WP_Error( 'file21_spdb_inventory_forbidden', __( 'The File 21 inventory is not authorized.', 'sabri-complete-home-news-feed' ) );
		}
		$scope = sanitize_key( (string) ( $query['scope'] ?? 'own' ) );
		if ( 'institution' === $scope && ! current_user_can( 'spdb_view_global_analytics' ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'file21_spdb_institution_forbidden', __( 'Institution-wide File 21 inventory is not authorized.', 'sabri-complete-home-news-feed' ) );
		}
		return array( 'user_id' => $user_id, 'scope' => 'institution' === $scope ? 'institution' : 'own' );
	}

	/** @param array<string,mixed> $query @param array<string,mixed> $authorization */
	private function base_query_args( array $query, array $authorization, int $page, int $per_page ): array {
		$types = array( 'post', 'sabri_news' );
		if ( ! empty( $query['object_types'] ) && is_array( $query['object_types'] ) ) {
			$types = array();
			foreach ( $query['object_types'] as $type ) {
				if ( 'social_post' === $type ) {
					$types[] = 'post';
				} elseif ( 'editorial_news' === $type ) {
					$types[] = 'sabri_news';
				}
			}
			if ( empty( $types ) ) {
				$types = array( 'post', 'sabri_news' );
			}
		}
		$args = array(
			'post_type'              => array_values( array_unique( $types ) ),
			'post_status'            => array( 'draft', 'pending', 'future', 'publish', 'private' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'suppress_filters'       => false,
			'update_post_term_cache' => true,
		);
		if ( 'own' === $authorization['scope'] ) {
			$args['author'] = (int) $authorization['user_id'];
		}
		$search = isset( $query['search'] ) && is_scalar( $query['search'] ) ? trim( sanitize_text_field( (string) $query['search'] ) ) : '';
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		return $args;
	}

	/** @return array<string,mixed>|null */
	private function project_post( $post ): ?array {
		if ( ! $post instanceof \WP_Post || ! $this->viewer_can_read_post( $post ) ) {
			return null;
		}
		$post_id     = (int) $post->ID;
		$review      = $this->review_state( $post );
		$visibility  = $this->visibility_state( $post );
		$language    = 'sabri_news' === $post->post_type ? get_post_meta( $post_id, '_sabri_news_language', true ) : get_post_meta( $post_id, PostMetadata::META_LANGUAGE, true );
		$topic       = $this->first_term_slug( $post_id, 'sabri_news' === $post->post_type ? 'sabri_news_topic' : 'sabri_feed_topic' );
		$published   = 'publish' === $post->post_status ? $this->utc( $post->post_date_gmt ) : '';
		$scheduled   = 'future' === $post->post_status ? $this->utc( $post->post_date_gmt ) : '';
		$author      = get_userdata( (int) $post->post_author );
		$permalink   = 'publish' === $post->post_status ? get_permalink( $post_id ) : '';
		$edit_url    = get_edit_post_link( $post_id, 'raw' );
		$preview_url = 'publish' === $post->post_status ? '' : get_preview_post_link( $post_id );

		return array(
			'object_type'       => $this->object_type( $post ),
			'object_id'         => (string) $post_id,
			'object_version'    => $this->native_version( $post ),
			'title'             => '' !== trim( wp_strip_all_tags( (string) $post->post_title ) ) ? wp_strip_all_tags( (string) $post->post_title ) : __( '(Untitled)', 'sabri-complete-home-news-feed' ),
			'summary'           => wp_trim_words( wp_strip_all_tags( (string) $post->post_excerpt ), 55, '…' ),
			'language'          => $this->canonical_optional_key( $language ),
			'topic'             => $this->canonical_optional_key( $topic ),
			'privacy_class'     => 'public' === $visibility ? 'public' : 'restricted',
			'owner_user_id'     => (int) $post->post_author,
			'author'            => array( 'id' => (int) $post->post_author, 'display_name' => $author ? (string) $author->display_name : __( 'Unknown author', 'sabri-complete-home-news-feed' ) ),
			'created_at'        => $this->utc( $post->post_date_gmt ),
			'modified_at'       => $this->utc( $post->post_modified_gmt ),
			'scheduled_at'      => $scheduled,
			'published_at'      => $published,
			'canonical_url'     => is_string( $permalink ) ? $permalink : '',
			'thumbnail_url'     => '',
			'destinations'      => array_filter( array( 'edit' => is_string( $edit_url ) ? $edit_url : '', 'preview' => is_string( $preview_url ) ? $preview_url : '', 'public' => is_string( $permalink ) ? $permalink : '' ) ),
			'compliance_alerts' => array(),
			'lifecycle_state'   => $this->lifecycle_state( $post ),
			'review_state'      => $review,
			'visibility_state'  => $visibility,
			'operational_state' => 'future' === $post->post_status ? 'scheduled' : ( 'publish' === $post->post_status ? 'healthy' : 'processing' ),
		);
	}

	/** @return array<string,mixed>|null */
	private function review_item( $post, array $context ): ?array {
		if ( ! $post instanceof \WP_Post || ! $this->viewer_can_read_post( $post, true ) ) {
			return null;
		}
		$post_id     = (int) $post->ID;
		$reviewer_id = 'sabri_news' === $post->post_type ? (int) get_post_meta( $post_id, '_sabri_news_reviewing_editor_id', true ) : 0;
		if ( empty( $context['is_founder'] ) && $reviewer_id > 0 && $reviewer_id !== (int) $context['user_id'] ) {
			return null;
		}
		$reviewer    = $reviewer_id > 0 ? get_userdata( $reviewer_id ) : null;
		$author      = get_userdata( (int) $post->post_author );
		return array(
			'object_type'            => $this->object_type( $post ),
			'object_id'              => (string) $post_id,
			'title'                  => '' !== trim( wp_strip_all_tags( (string) $post->post_title ) ) ? wp_strip_all_tags( (string) $post->post_title ) : __( '(Untitled)', 'sabri-complete-home-news-feed' ),
			'author_id'              => (int) $post->post_author,
			'author_name'            => $author ? (string) $author->display_name : __( 'Unknown author', 'sabri-complete-home-news-feed' ),
			'scope'                  => ! empty( $context['is_founder'] ) ? 'institution' : 'own',
			'native_version'         => $this->native_version( $post ),
			'last_synced_at'         => $this->utc( $post->post_modified_gmt ),
			'review_state'           => $this->review_state( $post ),
			'assigned_reviewer_id'   => $reviewer_id,
			'assigned_reviewer_name' => $reviewer ? (string) $reviewer->display_name : '',
			'due_at'                 => '',
			'privacy_flags'          => array(),
			'safety_flags'           => array(),
			'source_flags'           => array(),
			'copyright_flags'        => array(),
			'native_review_url'      => (string) get_edit_post_link( $post_id, 'raw' ),
			'allowed_operations'     => array(),
			'separation_required'    => true,
		);
	}

	/** @return array<string,mixed>|null */
	private function calendar_item( $post, array $context ): ?array {
		if ( ! $post instanceof \WP_Post || ! $this->viewer_can_read_post( $post, true ) ) {
			return null;
		}
		$author = get_userdata( (int) $post->post_author );
		return array(
			'object_type'        => $this->object_type( $post ),
			'object_id'          => (string) $post->ID,
			'title'              => '' !== trim( wp_strip_all_tags( (string) $post->post_title ) ) ? wp_strip_all_tags( (string) $post->post_title ) : __( '(Untitled)', 'sabri-complete-home-news-feed' ),
			'author_id'          => (int) $post->post_author,
			'author_name'        => $author ? (string) $author->display_name : __( 'Unknown author', 'sabri-complete-home-news-feed' ),
			'scope'              => ! empty( $context['is_founder'] ) ? 'institution' : 'own',
			'native_version'     => $this->native_version( $post ),
			'last_synced_at'     => $this->utc( $post->post_modified_gmt ),
			'status'             => 'scheduled',
			'scheduled_at_utc'   => $this->utc( $post->post_date_gmt ),
			'native_timezone'    => $this->native_timezone(),
			'conflicts'          => array(),
			'native_edit_url'    => (string) get_edit_post_link( (int) $post->ID, 'raw' ),
			'allowed_operations' => array(),
		);
	}

	private function viewer_can_read_post( $post, bool $operator = false ): bool {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return false;
		}
		if ( (int) $post->post_author === $user_id && current_user_can( 'edit_post', (int) $post->ID ) ) {
			return true;
		}
		if ( ( $operator && current_user_can( 'spdb_view_review_queue' ) ) || current_user_can( 'spdb_view_global_analytics' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return 'publish' === $post->post_status && PostMetadata::user_can_view( (int) $post->ID, $user_id );
	}

	private function object_type( $post ): string {
		return 'sabri_news' === $post->post_type ? 'editorial_news' : 'social_post';
	}

	private function lifecycle_state( $post ): string {
		if ( 'publish' === $post->post_status ) {
			return 'published';
		}
		if ( 'private' === $post->post_status ) {
			$workflow = 'sabri_news' === $post->post_type ? sanitize_key( (string) get_post_meta( (int) $post->ID, '_sabri_news_workflow_state', true ) ) : '';
			return 'retracted' === $workflow ? 'retracted' : 'archived';
		}
		return 'draft' === $post->post_status ? 'draft' : 'submitted';
	}

	private function review_state( $post ): string {
		$raw = 'sabri_news' === $post->post_type
			? sanitize_key( (string) get_post_meta( (int) $post->ID, '_sabri_news_workflow_state', true ) )
			: sanitize_key( (string) get_post_meta( (int) $post->ID, PostMetadata::META_REVIEW_STATE, true ) );
		$map = array(
			''                     => 'not_required',
			'draft'                => 'not_required',
			'approved'             => 'approved',
			'published'            => 'approved',
			'updated'              => 'approved',
			'pending'              => 'awaiting_review',
			'editorial-review'     => 'under_review',
			'fact-check'           => 'under_review',
			'medical-review'       => 'under_review',
			'needs-sources'        => 'changes_requested',
			'changes-requested'    => 'changes_requested',
			'rejected'             => 'rejected',
			'correction-pending'   => 'on_hold',
			'archived'             => 'on_hold',
			'retracted'            => 'rejected',
			'ready-for-publication'=> 'approved',
			'scheduled'            => 'approved',
		);
		return $map[ $raw ] ?? 'unknown';
	}

	private function visibility_state( $post ): string {
		if ( 'publish' === $post->post_status ) {
			$visibility = 'sabri_news' === $post->post_type ? 'public' : PostMetadata::visibility( (int) $post->ID );
			return 'public' === $visibility ? 'public' : 'restricted';
		}
		return 'private';
	}

	private function native_version( $post ): string {
		$modified = '' !== (string) $post->post_modified_gmt ? (string) $post->post_modified_gmt : (string) $post->post_modified;
		return 'post-' . (int) $post->ID . '-' . preg_replace( '/[^0-9]/', '', $modified );
	}

	private function utc( $mysql ): string {
		$timestamp = is_string( $mysql ) && '' !== $mysql ? strtotime( $mysql . ' UTC' ) : false;
		return false === $timestamp ? gmdate( 'Y-m-d\TH:i:s\Z' ) : gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	private function first_term_slug( int $post_id, string $taxonomy ): string {
		$terms = get_the_terms( $post_id, $taxonomy );
		return is_array( $terms ) && ! empty( $terms ) && isset( $terms[0]->slug ) ? (string) $terms[0]->slug : '';
	}

	private function canonical_optional_key( $value ): string {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		return preg_match( '/^[a-z0-9][a-z0-9_-]{1,63}$/', $value ) ? $value : '';
	}

	private function native_timezone(): string {
		$timezone = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '';
		return in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : 'UTC';
	}
}
