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
        self::assertStringContainsString('* Version: 1.0.3.3', $bootstrap);
        self::assertStringContainsString("SABRI_HNF_PACKAGE_VERSION', '1.0.3.3'", $bootstrap);
        self::assertStringContainsString("SABRI_HNF_VERSION', '1.0.3'", $bootstrap);
    }

    public function testOneCanonicalReleaseBuilderOwnsPackaging(): void
    {
        $python = $this->source('tools/build-release.py');
        $php = $this->source('tools/build-release.php');
        $powershell = $this->source('tools/build-release.ps1');
        self::assertStringContainsString('Two clean deterministic builds were not byte-identical', $python);
        self::assertStringContainsString('MANIFEST.sha256', $python);
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

    public function testExactHeadProvenanceIsEnforcedByReleaseWorkflow(): void
    {
        $workflow = $this->source('.github/workflows/build-test-home-news-feed.yml');
        self::assertStringContainsString('git rev-parse HEAD', $workflow);
        self::assertStringContainsString('--source-sha "${TEST_SHA}"', $workflow);
        self::assertStringContainsString('- Exact source commit: ${TEST_SHA}', $workflow);
    }
}
