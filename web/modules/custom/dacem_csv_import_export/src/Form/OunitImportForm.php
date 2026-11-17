<?php


namespace Drupal\dacem_csv_import_export\Form;

use Drupal\dacem_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\dacem_csv_import_export\Form\ImportFormBase;

class OunitImportForm extends ImportFormBase {

	public const ENTITY_TYPE = ImportTargetEntityType::OUNIT->value;
  protected const SORT = TRUE;

  public const ENTITY_LABEL = 'Organizational Units';

  public function getFormId() {

		return 'dacem_csv_import_export.import_ounit_form';
  }

}
