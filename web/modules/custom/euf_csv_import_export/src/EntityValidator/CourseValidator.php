<?php

namespace Drupal\euf_csv_import_export\EntityValidator;

use Drupal\euf_csv_import_export\EntityValidator\EntityValidatorBase;
use Drupal\node\Entity\Node;

class CourseValidator extends EntityValidatorBase {

  public const SKIPPED_FIELDS = [];
  public const INSTITUTION_FIELD = 'field_iec_programme.entity.field_programme_institution.entity';
  public const CODE_FIELD = 'field_iec_code';
  public const ENTITY_LABEL = 'Course';

  protected function findEntity(string $entity_type, string $code_field, string $code, string $institution_field, Node $institution)
  {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery();
    $query->condition($code_field, $code);
    $query->condition('field_iec_programme.entity.field_programme_institution', $institution->id());
    $nids = $query->accessCheck(TRUE)->execute();

    return $nids ? $storage->loadMultiple($nids) : [];
  }

}