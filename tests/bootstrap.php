<?php
/**
 * PHPUnit bootstrap file for AI JSON Cleanroom tests.
 */

declare(strict_types=1);

// Define test root directory
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));
define('FIXTURES_DIR', TEST_ROOT . '/fixtures');

// Require the main library file
require_once PROJECT_ROOT . '/ai_json_cleanroom.php';

// Helper function to load fixtures
function loadFixture(string $filename): string
{
    $path = FIXTURES_DIR . '/' . $filename;

    if (!file_exists($path)) {
        throw new RuntimeException("Fixture file not found: {$path}");
    }

    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException("Failed to read fixture file: {$path}");
    }

    return $content;
}

// Helper function to load and parse JSON fixture
function loadJsonFixture(string $filename): mixed
{
    $content = loadFixture($filename);
    return json_decode($content, true);
}

echo "AI JSON Cleanroom Test Suite Bootstrap\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHPUnit loaded successfully\n";
echo "Fixtures directory: " . FIXTURES_DIR . "\n";
echo str_repeat('-', 70) . "\n\n";
