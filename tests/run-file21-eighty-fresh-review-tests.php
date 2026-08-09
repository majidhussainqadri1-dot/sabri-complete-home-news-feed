<?php
/**
 * Stable entry point for the File 21 eighty-round review gate.
 *
 * The underlying source-contract fixture intentionally matches the literal
 * PHP source token `$mode`. Define that token value before loading the fixture
 * so PHP string interpolation cannot weaken or corrupt the assertion.
 */
$mode = '$mode';
require __DIR__ . '/run-file21-eighty-fresh-review-v3-tests.php';
