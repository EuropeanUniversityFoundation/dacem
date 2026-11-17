<?php


namespace Drupal\dacem_csv_import_export\Form;

use Drupal\dacem_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\dacem_csv_import_export\Form\ImportFormBase;

class CourseImportForm extends ImportFormBase {

	public const ENTITY_TYPE = ImportTargetEntityType::COURSE->value;
  public const ENTITY_LABEL = 'Courses';

  public function getFormId() {

		return 'dacem_csv_import_export.import_course_form';
  }

}
