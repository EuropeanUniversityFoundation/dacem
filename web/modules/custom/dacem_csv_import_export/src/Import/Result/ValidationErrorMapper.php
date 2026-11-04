<?php

namespace Drupal\dacem_csv_import_export\Import\Result;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Maps Drupal violations back to CSV headers for preflight and save failures.
 */
final class ValidationErrorMapper {

  /**
   * @return \Drupal\dacem_csv_import_export\Import\Result\ImportError[]
   *   One error per constraint violation, with the affected language if known.
   */
  public function map(ImportRow $row, ConstraintViolationListInterface $violations, string $type, ?string $language = NULL): array {
    $errors = [];
    foreach ($violations as $violation) {
      $root = $violation->getRoot();
      $entity = $root instanceof TypedDataInterface ? $root->getValue() : $root;
      $affected_language = $language ?? ($entity instanceof ContentEntityInterface ? $entity->language()->getId() : NULL);
      $path = $violation->getPropertyPath();
      $parts = explode('.', $path);
      $field = $parts[0];
      $delta = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : NULL;
      $property = $parts[2] ?? NULL;
      $column = $row->findColumn($field, $affected_language, $delta, $property);
      $errors[] = new ImportError(
        $type,
        (string) $violation->getMessage(),
        $row->rowNumber,
        $column?->header ?? $field,
        $this->values($violation->getInvalidValue()),
        $affected_language,
        $path,
      );
    }
    return $errors;
  }

  private function values(mixed $value): array {
    if ($value === NULL) {
      return [''];
    }
    if (is_scalar($value)) {
      return [is_bool($value) ? (string) (int) $value : (string) $value];
    }
    if ($value instanceof ContentEntityInterface) {
      return [(string) ($value->label() ?? $value->id())];
    }
    if ($value instanceof TypedDataInterface) {
      return $this->values($value->getValue());
    }
    if (is_array($value)) {
      if (!$value) {
        return [''];
      }
      $values = [];
      foreach ($value as $item) {
        array_push($values, ...$this->values($item));
      }
      return $values;
    }
    return [];
  }

}
