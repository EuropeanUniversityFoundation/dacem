<?php

namespace Drupal\dacem_csv_import_export\EntityValidator;

use Drupal\dacem_csv_import_export\EntityValidator\EntityValidatorBase;

class ProgrammeValidator extends EntityValidatorBase {

  public const SKIPPED_FIELDS = [];
  public const INSTITUTION_FIELD = 'field_programme_institution';
  public const CODE_FIELD = 'field_programme_code';
  public const ENTITY_LABEL = 'Programme';

}