<?php
/**
 * Static-analysis-only declarations for the File 22 adapter contract.
 *
 * Runtime loads these interfaces from Sabri Universal Post Composer. This file
 * is scanned by PHPStan only and is never included by the plugin.
 */

namespace Sabri\UniversalComposer\Contracts;

interface Adapter {
	public function api_version(): string;
	public function key(): string;
	public function label(): string;
	public function description(): string;
	public function group(): string;
	public function icon(): string;
	public function priority(): int;
	public function native_module(): string;
	public function minimum_native_version(): string;
	public function required_capability(): string;
	public function privacy_classification(): string;
	public function is_available(): bool;
	public function can_create( int $user_id ): bool;
	public function start_url( int $user_id ): string;
}

interface Diagnostic_Adapter extends Adapter {
	/** @return array<string,mixed> */
	public function health_report(): array;
}

interface Workflow_Adapter extends Adapter {
	public function workflow_api_version(): string;
	public function schema_version(): string;
	public function supports_native_drafts(): bool;
	/** @return array<string,mixed> */
	public function schema(): array;
	public function create_draft( int $user_id, ?string $native_reference, array $payload );
	public function validate( int $user_id, array $payload );
	public function preview( int $user_id, array $payload );
	public function submit( int $user_id, string $idempotency_key, array $payload );
	public function status( int $user_id, string $native_reference );
	public function canonical_url( int $user_id, string $native_reference ): string;
}
