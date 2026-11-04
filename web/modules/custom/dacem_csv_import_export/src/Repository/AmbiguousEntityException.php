<?php

namespace Drupal\dacem_csv_import_export\Repository;

/**
 * A catalogue identity matched multiple entities, so import cannot choose one.
 */
final class AmbiguousEntityException extends \RuntimeException {
}
