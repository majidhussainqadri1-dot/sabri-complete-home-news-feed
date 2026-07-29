<?php
/**
 * Backward-compatible File 22 social-publication adapter class name.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves the registered class name while the corrected implementation lives
 * in UniversalComposerWorkflowAdapter.
 */
final class UniversalComposerPublicationAdapter extends UniversalComposerWorkflowAdapter {
}
