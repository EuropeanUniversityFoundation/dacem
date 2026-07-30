<?php


namespace Drupal\dacem_csv_import_export\Form;

use Drupal\dacem_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\dacem_csv_import_export\Form\ImportFormBase;
use Drupal\file\Entity\File;

class ProgrammeImportForm extends ImportFormBase {

	public const ENTITY_TYPE = ImportTargetEntityType::PROGRAMME->value;
  public const ENTITY_LABEL = 'Programmes';

  public function getFormId() {

		return 'dacem_csv_import_export.import_programme_form';
  }

  protected function importCsv(File $file, string $entity_type) {
    return $this->csvImporter->import(file: $file, entityType: $entity_type);
  }

}
