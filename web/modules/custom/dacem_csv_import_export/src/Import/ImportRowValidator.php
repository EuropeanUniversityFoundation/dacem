<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\Result\ImportError;
use Drupal\dacem_csv_import_export\Import\Result\ValidationErrorMapper;
use Drupal\dacem_csv_import_export\Repository\AmbiguousEntityException;
use Drupal\dacem_csv_import_export\Repository\CatalogueEntityRepository;
use Drupal\node\Entity\Node;

/**
 * Preflights every supported bundle using its definition and shared identity.
 */
final class ImportRowValidator {

  public function __construct(
    protected CatalogueEntityRepository $repository,
    protected EntityImportValidator $validator,
    protected BundleCsvDefinitions $definitions,
    protected ValidationErrorMapper $errorMapper,
  ) {}

  /**
   * @param \Drupal\dacem_csv_import_export\Import\ImportRow[] $rows
   *   Rows retaining their source metadata and resolved regular references.
   *
   * @return \Drupal\dacem_csv_import_export\Import\Result\ImportError[]
   *   Errors ready for the import form, without modifying the original rows.
   */
  public function validateMultiple(array $rows, Node $institution): array {
    $errors = [];
    foreach ($rows as $row) {
      $definition = $this->definitions->get($row->bundle);
      try {
        $entity = $this->repository->findImportTarget($row, $institution);
      }
      catch (AmbiguousEntityException $exception) {
        $errors[] = new ImportError(
          'Ambiguous identity', $exception->getMessage(), $row->rowNumber,
          $definition->codeField, [(string) $row->fieldValue($definition->codeField)],
        );
        continue;
      }
      $validation_row = $row->withoutFields($definition->validationSkippedFields);
      array_push($errors, ...$this->errorMapper->map(
        $row, $this->validator->validate($validation_row, $entity),
        'Invalid ' . $definition->label . ' data',
      ));
    }
    return $errors;
  }

}
