<?php

namespace Drupal\dacem_csv_import_export\Import\Conversion;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\dacem_csv_import_export\Import\ImportRow;

/**
 * Converts CSV text into Drupal formatted-text values, preserving line breaks.
 */
final class FormattedTextConverter {

  public const DEFAULT_FORMAT = 'basic_html';

  private const FIELD_TYPES = ['text', 'text_long', 'text_with_summary'];

  public function __construct(protected EntityFieldManagerInterface $fieldManager) {}

  public function convert(ImportRow $row): ImportRow {
    $definitions = $this->fieldManager->getFieldDefinitions('node', $row->bundle);
    $fields = $row->fieldsByLanguage;
    foreach ($fields as $language => $translation) {
      foreach ($translation as $name => $value) {
        if (isset($definitions[$name]) && in_array($definitions[$name]->getType(), self::FIELD_TYPES, TRUE)) {
          $fields[$language][$name] = [
            'value' => nl2br($value),
            'format' => self::DEFAULT_FORMAT,
          ];
        }
      }
    }
    return $row->withFields($fields);
  }

}
