<?php

namespace Drupal\dacem_csv_import_export\Definition;

/**
 * CSV rules for one bundle, independent of Drupal's field configuration.
 *
 * Drupal field definitions still determine cardinality and translatability.
 * Reference resolution and file validation remain distinct: for example, term
 * references are resolved but are not currently checked by the file validator.
 */
final class BundleCsvDefinition {

  /**
   * @param string|null $institutionColumn
   *   A CSV column name, possibly a virtual path through programme references.
   * @param string[] $requiredSharedColumns
   *   Required headers without language suffixes, including configured deltas.
   * @param string[] $requiredTranslatedColumns
   *   Required headers to expand for each language present in the CSV.
   * @param string[]|null $exportFields
   *   Ordered fields or virtual paths; NULL uses field-definition order.
   * @param array[] $referenceResolution
   *   Lookup rules consumed by ReferenceResolver, including deferred lookups.
   * @param array[] $referenceValidation
   *   Node code checks consumed by FileValidator, including in-file parents.
   * @param string[] $validationSkippedFields
   *   Fields excluded from preflight until their references can be resolved.
   * @param array $coordinates
   *   Optional latitude/longitude to WKT mapping consumed by CsvDecoder.
   */
  public function __construct(
    public readonly string $bundle,
    public readonly string $label,
    public readonly ?string $codeField = NULL,
    public readonly ?string $institutionColumn = NULL,
    public readonly array $requiredSharedColumns = [],
    public readonly array $requiredTranslatedColumns = [],
    public readonly ?array $exportFields = NULL,
    public readonly array $referenceResolution = [],
    public readonly array $referenceValidation = [],
    public readonly ?string $parentField = NULL,
    public readonly array $validationSkippedFields = [],
    public readonly array $coordinates = [],
    public readonly bool $importable = FALSE,
  ) {}

}
