<?php

/**
 * @file
 * Adds narrow upstream deprecation allowances to Drupal's normal test bootstrap.
 */

// Preserve an explicit deprecation policy supplied by the developer or CI.
if (getenv('SYMFONY_DEPRECATIONS_HELPER') === FALSE) {
  $csv_core_root = dirname(__DIR__, 4) . '/core';
  $csv_ignore_file = tempnam(sys_get_temp_dir(), 'csv-deprecations-');
  file_put_contents($csv_ignore_file, file_get_contents($csv_core_root . '/.deprecation-ignore.txt') . "\n" . file_get_contents(__DIR__ . '/deprecation-ignore.txt'));
  putenv('SYMFONY_DEPRECATIONS_HELPER=ignoreFile=' . $csv_ignore_file);
  // PHPUnit's child processes inherit this path; only its owner removes it.
  register_shutdown_function(static function () use ($csv_ignore_file): void {
    // Defer deletion until Symfony has emitted its shutdown report.
    register_shutdown_function(static function () use ($csv_ignore_file): void {
      if (is_file($csv_ignore_file)) {
        unlink($csv_ignore_file);
      }
    });
  });
}

require dirname(__DIR__, 4) . '/core/tests/bootstrap.php';
