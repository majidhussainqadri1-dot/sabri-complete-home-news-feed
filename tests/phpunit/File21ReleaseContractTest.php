<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class File21ReleaseContractTest extends TestCase
{
    private function source(string $relative): string
    {
        $path = FILE21_TEST_ROOT . '/' . ltrim($relative, '/');
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        return $contents;
    }

    public function testReleaseIdentityIsSeparatedFromStableRuntime(): void
    {
        $bootstrap = $this->source('sabri-complete-home-news-feed.php');
        self::assertStringContainsString('* Version: 1.0.5', $bootstrap);
        self::assertStringContainsString("SABRI_HNF_PACKAGE_VERSION', '1.0.5'", $bootstrap);
        self::assertStringContainsString("SABRI_HNF_VERSION', '1.0.3'", $bootstrap);
        self::assertStringContainsString("SABRI_HNF_SCHEMA_VERSION', '1.0.0'", $bootstrap);
    }

    public function testOneCanonicalReleaseBuilderOwnsPackaging(): void
    {
        $python = $this->source('tools/build-release.py');
        $php = $this->source('tools/build-release.php');
        $powershell = $this->source('tools/build-release.ps1');
        self::assertStringContainsString('PACKAGE_VERSION = "1.0.5"', $python);
        self::assertStringContainsString('Two clean deterministic builds were not byte-identical', $python);
        self::assertStringContainsString('MANIFEST.sha256', $python);
        self::assertStringContainsString('Hostinger staging accepted: NO', $python);
        self::assertStringContainsString('Live deployed: NO', $python);
        self::assertStringContainsString('Use: python3 tools/build-release.py', $php);
        self::assertStringContainsString('build-release.py', $powershell);
    }

    public function testActorAuthorizationAndAuthorPolicyAreDistinct(): void
    {
        $permissions = $this->source('includes/class-composer-permissions.php');
        $policy = $this->source('includes/class-privileged-publishing-policy.php');
        $migration = $this->source('includes/class-legacy-founder-post-migration.php');
        self::assertStringContainsString('current_actor_matches', $permissions);
        self::assertStringContainsString('subject_can_publish_immediately', $permissions);
        self::assertStringContainsString('subject_is_institutional_publisher', $permissions);
        self::assertStringContainsString('subject_can_publish_immediately( $author )', $policy);
        self::assertStringContainsString('subject_is_institutional_publisher( $author_id )', $migration);
    }

    public function testFile22RegistryGateCannotPreemptNativeFounderAuthorization(): void
    {
        $wrapper = $this->source('includes/class-universal-composer-subject-schema-adapter.php');
        self::assertStringContainsString("required_capability(): string { return 'read'; }", $wrapper);
        self::assertStringContainsString('return $this->delegate->can_create( $user_id );', $wrapper);
    }

    public function testFile23ProviderIsProjectionOnlyAndWritesFailClosed(): void
    {
        $bridge = $this->source('includes/class-file23-publishing-dashboard-bridge.php');
        $runtime = $this->source('includes/class-file23-publishing-dashboard-adapter-runtime.php');
        self::assertStringContainsString('spdb/register_adapters', $bridge);
        self::assertStringContainsString('public function get_operation_definitions(): array', $runtime);
        self::assertStringContainsString('file21_spdb_write_not_accepted', $runtime);
    }

    public function testFile26IsCanonicalGlobalSearchOwnerAndConnectorStartsFailClosed(): void
    {
        $registry = $this->source('includes/class-search-provider-registry.php');
        self::assertStringContainsString("FILE26_CONNECTOR_SLUG = 'file21-publication'", $registry);
        self::assertStringContainsString("'owner_file' => '21'", $registry);
        self::assertStringContainsString("'status' => 'proposed'", $registry);
        self::assertStringNotContainsString("'status' => 'active'", $registry);
        self::assertStringContainsString("'global_search_owner' => '26'", $registry);
        self::assertStringContainsString('sabri_file26_tombstone_document', $registry);
    }

    public function testExactHeadProvenanceIsEnforcedByReleaseWorkflow(): void
    {
        $workflow = $this->source('.github/workflows/build-test-home-news-feed.yml');
        self::assertStringContainsString('git rev-parse HEAD', $workflow);
        self::assertStringContainsString('--source-sha "${TEST_SHA}"', $workflow);
        self::assertStringContainsString('- Exact source commit: ${TEST_SHA}', $workflow);
    }
}
