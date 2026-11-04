<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\node\Entity\Node;

/**
 * Persists a prepared batch atomically or throws a source-aware write failure.
 */
interface ImportWriterInterface {

  /**
   * @param \Drupal\dacem_csv_import_export\Import\ImportRow[] $rows
   *   Rows with regular references resolved, in dependency order.
   */
  public function writeMultiple(array $rows, Node $institution): void;

}
