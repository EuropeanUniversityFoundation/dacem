<?php

namespace Drupal\euf_csv_import_export\Enum;

enum ImportTargetEntityType: string {
  case OUNIT = 'organizational_unit';
  case PROGRAMME = 'programme';
  case COURSE = 'individual_educational_component';
  case COURSE_INSTANCE = 'iec_instance';
}
