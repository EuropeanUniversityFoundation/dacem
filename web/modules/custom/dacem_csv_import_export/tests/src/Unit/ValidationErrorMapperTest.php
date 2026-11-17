<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\dacem_csv_import_export\Import\ImportRow;
use Drupal\dacem_csv_import_export\Import\Result\ImportResult;
use Drupal\dacem_csv_import_export\Import\Result\ValidationErrorMapper;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Tests source header selection and consistent success/failure outcomes.
 *
 * @group dacem_csv_import_export
 */
class ValidationErrorMapperTest extends TestCase {

  public function testMapsPropertyDeltaAndLanguageWithoutLosingZero(): void {
    $parser = new CsvHeaderParser();
    $headers = ['field_links____0___uri|en', 'field_links____1___uri|en', 'field_links____1___uri|es'];
    $columns = [];
    foreach ($headers as $header) {
      $columns[$header] = $parser->parse($header);
    }
    $row = new ImportRow('programme', 14, [], $columns);
    $violations = new ConstraintViolationList([
      new ConstraintViolation('Invalid URL', NULL, [], NULL, 'field_links.1.uri', '0'),
    ]);
    $errors = (new ValidationErrorMapper())->map($row, $violations, 'Validation', 'es');
    $this->assertSame('field_links____1___uri|es', $errors[0]->column);
    $this->assertSame(14, $errors[0]->rowNumber);
    $this->assertSame('es', $errors[0]->language);
    $this->assertSame(['0'], $errors[0]->values);
    $this->assertSame('field_links.1.uri', $errors[0]->fieldPath);
    $result = ImportResult::failure($errors);
    $this->assertFalse($result->isSuccessful());
    $this->assertSame(0, $result->importedCount);
    $this->assertTrue(ImportResult::success(2)->isSuccessful());
    $this->assertSame(2, ImportResult::success(2)->importedCount);
  }

  public function testEmptyValuesAndWholeRowConstraints(): void {
    $violations = new ConstraintViolationList([
      new ConstraintViolation('A title is required.', NULL, [], NULL, 'title', []),
      new ConstraintViolation('This combination is invalid.', NULL, [], NULL, '', NULL),
    ]);
    $errors = (new ValidationErrorMapper())->map(new ImportRow('programme', 3, []), $violations, 'Validation');
    $this->assertSame([''], $errors[0]->values);
    $this->assertSame('title', $errors[0]->column);
    $this->assertSame('', $errors[1]->column);
    $this->assertSame(3, $errors[1]->rowNumber);
  }

}
