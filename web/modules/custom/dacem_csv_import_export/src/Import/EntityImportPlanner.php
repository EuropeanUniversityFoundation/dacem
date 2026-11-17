<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\node\NodeInterface;

/**
 * Orders shared and translated field changes without modifying the entity.
 */
final class EntityImportPlanner {

  /**
   * Builds stages with the actual original language first.
   *
   * @return \Drupal\dacem_csv_import_export\Import\ImportStage[]
   *   Ordered stages shared by preflight validation and persistence.
   */
  public function plan(NodeInterface $entity, ImportRow $row): array {
    if ($entity->bundle() !== $row->bundle) {
      throw new \InvalidArgumentException('The import row and entity must use the same bundle.');
    }
    $original_language = $entity->getUntranslated()->language()->getId();
    $fields_by_language = [$original_language => []];
    foreach ($row->fieldsByLanguage as $language => $fields) {
      foreach ($fields as $field_name => $value) {
        // Protected Drupal fields cannot be changed through a CSV row.
        if (in_array($field_name, ['type', 'langcode', 'csv_row'], TRUE) || !$entity->hasField($field_name)) {
          continue;
        }
        $definition = $entity->getFieldDefinition($field_name);
        if ($definition->isComputed()) {
          continue;
        }
        $target_language = $definition->isTranslatable() ? $language : $original_language;
        $fields_by_language[$target_language][$field_name] = $value;
      }
    }

    $stages = [];
    foreach ($fields_by_language as $language => $fields) {
      $stages[] = new ImportStage($language, $fields);
    }
    return $stages;
  }

}
