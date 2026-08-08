<?php
/**
 * Main plugin coordinator.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Coordinates the plugin runtime. */
final class Plugin {
	private static $instance = null;
	private $registered = false;
	public static function instance(){if(null===self::$instance){self::$instance=new self();}return self::$instance;}
	public function register(){
		if($this->registered){return;}$this->registered=true;
		$modules=array(
			Settings::class,HarmonizedSettings::class,CanonicalIdentityAdapter::class,CompanionIntegrationRegistry::class,SearchProviderRegistry::class,
			Phase3FeatureSettings::class,NewsFeatureSettings::class,NewsCapabilities::class,NewsStatuses::class,
			EditorialNewsPostType::class,NewsTaxonomies::class,NewsPolicy::class,NewsPublicSnapshot::class,NewsWorkflow::class,
			NewsComposerValidator::class,NewsQueueService::class,NewsAudit::class,NewsSchedulingService::class,NewsroomDiagnostics::class,NewsService::class,
			NewsCache::class,NewsPublicProjector::class,NewsQueryService::class,NewsFeedIntegration::class,NewsRouting::class,NewsPublicRuntime::class,RestNews::class,
			Phase5FeatureSettings::class,Phase5Capabilities::class,Phase5Migrations::class,Phase5AuditIntegrity::class,SourceRegistry::class,ReviewLedger::class,PrivacyScanner::class,Phase5RateLimiter::class,PreviewTokenService::class,SubmissionService::class,BreakingNewsService::class,CorrectionLedger::class,TranslationService::class,SsrfGuard::class,PrivacyOperations::class,NewsDistribution::class,Phase5PublicationPolicy::class,Phase5Rest::class,Phase5Performance::class,Phase5Diagnostics::class,Phase5PublicRuntime::class,
			CorrectivePublicSettings::class,PublicSurfaceRecovery::class,CorrectivePublicMount::class,ProfileTimeline::class,RestProfileTimeline::class,
			PollComposerIntegration::class,Capabilities::class,PrivilegedPublishingPolicy::class,PostTypes::class,Taxonomies::class,RewriteRules::class,Integrations::class,HarmonizationDiagnostics::class,ReleaseReadiness::class,
			SafeMode::class,RestFoundation::class,DataRetention::class,NotificationBridge::class,Assets::class,PostMetadata::class,PublicContentIntegrity::class,PublicQueryGuard::class,
			FollowersVisibility::class,FollowersQueryGuard::class,MediaHandler::class,ViralRankingSignals::class,NetworkRelationshipBridge::class,FeedUserAgency::class,SavedCollectionService::class,NextGenerationIntegrations::class,NextGenerationHardening::class,NextGenerationFeed::class,FeedQuery::class,UniversalComposerBridge::class,File23PublishingDashboardBridge::class,PublicComposerSurface::class,HomeIntegration::class,HomeCompositionRegistry::class,CompanionHomeRowAdapters::class,LegacyInteractionMigrationAdapter::class,LegacyPublicationMigration::class,LegacyPublicationRollback::class,ViewRuntime::class,PollRuntime::class,
			SocialRuntime::class,CommentExperience::class,CommentRuntime::class,SavedPostsRuntime::class,FollowingRuntime::class,Shortcodes::class,Composer::class,RestFeed::class,
			RestComposer::class,RestInteractions::class,RestComments::class,RestFollows::class,RestReports::class,RestPolls::class,RestNextGeneration::class,
		);
		foreach($modules as$module){if(!SafeBoot::register_module($module)){return;}}
		if(function_exists('is_admin')&&is_admin()){foreach(array(Admin::class,CorrectiveAdmin::class,ReportAdmin::class,NewsComposerAccessRecovery::class,EditorialNewsPublicationBridge::class,NewsroomAdmin::class,Phase5NewsroomAdmin::class)as$module){if(!SafeBoot::register_module($module)){return;}}}
	}
	public static function identity(){return array('name'=>'Sabri Complete Home and News Feed','version'=>SABRI_HNF_VERSION,'slug'=>SABRI_HNF_SLUG,'text_domain'=>SABRI_HNF_TEXT_DOMAIN,'schema_version'=>SABRI_HNF_SCHEMA_VERSION);}
}
