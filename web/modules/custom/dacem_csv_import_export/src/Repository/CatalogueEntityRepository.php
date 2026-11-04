<?php

namespace Drupal\dacem_csv_import_export\Repository;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use Drupal\node\Entity\Node;

/**
 * Loads catalogue entities with explicit bundle and institution identity scopes.
 */
final class CatalogueEntityRepository {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected BundleCsvDefinitions $definitions,
  ) {}

  /**
   * Returns one existing import target or NULL, never an arbitrary first match.
   *
   * Institution paths can cross references (course -> programme -> institution).
   * Access is checked by the import's institution authorization before lookup;
   * preflight and persistence must identify the same entity.
   */
  public function findImportTarget(ImportRow $row, Node $institution): ?Node {
    $definition = $this->definitions->get($row->bundle);
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', $row->bundle)
      ->condition($definition->codeField, $row->fieldValue($definition->codeField))
      ->condition($definition->institutionColumn, $institution->id())
      ->execute();
    if (count($ids) > 1) {
      throw new AmbiguousEntityException('Multiple ' . $definition->label . ' entities have this code within the institution. Resolve the duplicate codes before importing.');
    }
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

  public function findInstitutionsBySchacCodes(array $codes): array {
    if (!$codes) {
      return [];
    }
    return $this->findByConditions('node', [
      ['field' => 'type', 'value' => 'institution'],
      ['field' => 'field_shac_code', 'value' => $codes, 'operator' => 'IN'],
    ]);
  }

  public function findInstitutionBySchacCode(string $code): ?Node {
    $institutions = $this->findInstitutionsBySchacCodes([$code]);
    if (count($institutions) > 1) {
      throw new AmbiguousEntityException('Multiple institutions have this SCHAC code. Resolve the duplicate institution codes before importing.');
    }
    return $institutions ? reset($institutions) : NULL;
  }

  /**
   * Loads reference candidates using the configured entity type and conditions.
   */
  public function findByConditions(string $entity_type, array $conditions, bool $access_check = FALSE): array {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $query = $storage->getQuery()->accessCheck($access_check);
    foreach ($conditions as $condition) {
      $query->condition($condition['field'], $condition['value'], $condition['operator'] ?? '=');
    }
    return $storage->loadMultiple($query->execute());
  }

  public function loadByIds(string $entity_type, array $ids): array {
    return $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
  }

}
