<?php


namespace Drupal\euf_csv_import_export\Form;

use Drupal\euf_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\euf_csv_import_export\Form\ImportFormBase;
use Drupal\file\Entity\File;

class OunitImportForm extends ImportFormBase {

	public const ENTITY_TYPE = ImportTargetEntityType::OUNIT->value;
  public const ENTITY_LABEL = 'Organizational Units';

  public function getFormId() {

		return 'euf_csv_import_export.import_ounit_form';
  }

  protected function importCsv(File $file, string $entity_type) {
    return $this->csvImporter->import(file: $file, entityType: $entity_type, sort: TRUE);
  }

}
