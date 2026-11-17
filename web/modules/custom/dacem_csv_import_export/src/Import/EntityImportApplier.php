<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\node\NodeInterface;

/**
 * Applies a planned stage in memory, without validating or saving it.
 */
final class EntityImportApplier {

  public function apply(NodeInterface $entity, ImportStage $stage): NodeInterface {
    $translation = $entity->hasTranslation($stage->language)
      ? $entity->getTranslation($stage->language)
      : $entity->addTranslation($stage->language);
    foreach ($stage->fields as $field_name => $value) {
      $translation->set($field_name, $value);
    }
    return $entity->getUntranslated();
  }

}
