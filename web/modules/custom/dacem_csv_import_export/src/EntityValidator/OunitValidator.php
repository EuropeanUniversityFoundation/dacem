<?php

namespace Drupal\dacem_csv_import_export\EntityValidator;

use Drupal\dacem_csv_import_export\EntityValidator\EntityValidatorBase;

class OunitValidator extends EntityValidatorBase {

  public const SKIPPED_FIELDS = ['field_parent_organizational_unit'];
  public const INSTITUTION_FIELD = 'field_ou_institution';
  public const CODE_FIELD = 'field_ou_code';
  public const ENTITY_LABEL = 'Organizational Unit';

}