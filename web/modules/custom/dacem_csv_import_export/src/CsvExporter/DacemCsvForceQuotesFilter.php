<?php

namespace Drupal\dacem_csv_import_export\CsvExporter;

/**
 * A dedicated stream filter class to safely substitute deprecated EncloseField functionality in league/csv.
 */
class DacemCsvForceQuotesFilter extends \php_user_filter {

  public const FORCE_QUOTE_MARKER = "\x01";

  public function filter($in, $out, &$consumed, $closing): int {
    while ($bucket = stream_bucket_make_writeable($in)) {
      // Unescape our internal marker tokens directly into standard raw double quotes
      $bucket->data = str_replace(self::FORCE_QUOTE_MARKER, '"', $bucket->data);
      $consumed += $bucket->datalen;
      stream_bucket_append($out, $bucket);
    }

    return PSFS_PASS_ON;
  }
}