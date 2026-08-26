<?php
declare(strict_types=1);

/**
 * Test runner cho UrlShortM.
 *   php tests/run-tests.php        -> focused (unit + integration)
 *   php tests/run-tests.php --all  -> focused + integration + http smoke
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/support/bootstrap.php';

$all = in_array('--all', $argv, true);

$suite = new TestSuite();

$files = array_merge(
    glob(__DIR__ . '/unit/*Test.php'),
    glob(__DIR__ . '/integration/*Test.php')
);

if ($all) {
    $files = array_merge($files, glob(__DIR__ . '/http/*Test.php'));
}

sort($files);

foreach ($files as $file) {
    echo "== " . basename($file) . "\n";
    $test = require $file;
    $test($suite);
}

echo "\nSuite: " . ($all ? 'unit + integration + http smoke' : 'unit + integration') . "\n";

exit($suite->run());
