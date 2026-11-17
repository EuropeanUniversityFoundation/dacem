<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dacem_csv_import_export\AccessManager\UserAccessManager;
use Drupal\dacem_csv_import_export\CsvConverter\ReferenceResolver;
use Drupal\dacem_csv_import_export\Repository\CatalogueEntityRepository;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use Drupal\node\Entity\Node;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Saves staged imports and group membership in one batch transaction.
 */
final class EntityImportWriter implements ImportWriterInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CatalogueEntityRepository $repository,
    protected ReferenceResolver $referenceResolver,
    protected UserAccessManager $userAccessManager,
    protected EntityImportPlanner $planner,
    protected EntityImportApplier $applier,
    protected EntityImportValidator $validator,
    protected Connection $database,
  ) {}

  /**
   * Resolves deferred references and saves each entity in dependency order.
   *
   * The supplied rows must already have their regular references resolved.
   * Deferred references can point to entities created earlier in this batch.
   * Database failures roll back the entire batch, including earlier rows.
   *
   * @param \Drupal\dacem_csv_import_export\Import\ImportRow[] $rows
   *   Rows already ordered by their reference dependencies.
   *
   * @throws \Drupal\dacem_csv_import_export\Import\ImportWriteException
   *   After rollback, with the failing row, language, and original exception.
   */
  public function writeMultiple(array $rows, Node $institution): void {
    $transaction = $this->database->startTransaction();
    $storage = $this->entityTypeManager->getStorage('node');
    $current_row = NULL;
    $language = NULL;
    try {
      foreach ($rows as $row) {
        $current_row = $row;
        $language = NULL;
        $row = $this->referenceResolver->resolveReferencesOnSave($row, $institution);
        $entity = $this->repository->findImportTarget($row, $institution);
        $entity = $entity ? $entity->getUntranslated() : $storage->create([
          'type' => $row->bundle,
          'langcode' => CsvHeaderParser::DEFAULT_LANGUAGE,
        ]);

        foreach ($this->planner->plan($entity, $row) as $stage) {
          $language = $stage->language;
          $entity = $this->applier->apply($entity, $stage);
          $violations = $this->validator->validateStage($entity, $stage);
          if ($violations->count()) {
            throw new ValidationFailedException($entity, $violations);
          }
          $entity->save();
          // Each translation must compare against the preceding saved stage.
          $entity = $storage->loadUnchanged($entity->id())->getUntranslated();
        }
        $language = NULL;
        $this->userAccessManager->addEntityToInstitutionGroup($entity, $institution);
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $storage->resetCache();
      if ($current_row) {
        throw new ImportWriteException($current_row, $language, $exception);
      }
      throw $exception;
    }
  }

}
