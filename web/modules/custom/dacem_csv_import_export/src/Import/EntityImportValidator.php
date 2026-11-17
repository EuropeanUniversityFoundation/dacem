<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use Drupal\node\NodeInterface;

/**
 * Validates import stages without saving or modifying cached entities.
 */
final class EntityImportValidator {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityImportPlanner $planner,
    protected EntityImportApplier $applier,
  ) {}

  /**
   * Simulates the baselines that persisted stages would establish.
   */
  public function validate(ImportRow $row, ?NodeInterface $entity = NULL): EntityConstraintViolationList {
    $entity = $entity ? clone $entity->getUntranslated() : $this->entityTypeManager->getStorage('node')->create([
      'type' => $row->bundle,
      'langcode' => CsvHeaderParser::DEFAULT_LANGUAGE,
    ]);
    $violations = new EntityConstraintViolationList($entity);
    foreach ($this->planner->plan($entity, $row) as $stage) {
      $baseline = clone $entity;
      unset($baseline->original);
      $entity = $this->applier->apply($entity, $stage);
      if (!$entity->isNew()) {
        // Model the baseline that loadUnchanged() supplies after a staged save.
        $entity->original = $baseline;
      }
      $violations->addAll($this->validateStage($entity, $stage));
    }
    return $violations;
  }

  /**
   * Validates the language affected by an already applied stage.
   *
   * Used both for preflight and immediately before each staged save. Other
   * translations are validated when their own stages are applied.
   */
  public function validateStage(NodeInterface $entity, ImportStage $stage): EntityConstraintViolationList {
    return $entity->getTranslation($stage->language)->validate();
  }

}
