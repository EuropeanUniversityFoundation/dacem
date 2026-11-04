<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\dacem_csv_import_export\Import\ImportRow;
use PHPUnit\Framework\TestCase;

/**
 * Tests row transformations and the distinction between absent and empty values.
 *
 * @group dacem_csv_import_export
 */
class ImportRowTest extends TestCase {

  public function testFieldsAndSourceMetadataAreSeparate(): void {
    $row = new ImportRow('programme', 12, [
      'en' => ['field_credits' => '0', 'field_programme_start_date' => ''],
      'es' => ['title' => 'Spanish title'],
    ]);
    $this->assertSame('programme', $row->bundle);
    $this->assertSame(12, $row->rowNumber);
    $this->assertSame('0', $row->fieldValue('field_credits'));
    $this->assertSame('', $row->fieldValue('field_programme_start_date'));
    $this->assertNull($row->fieldValue('missing_field'));
    $this->assertArrayNotHasKey('missing_field', $row->fieldsByLanguage['en']);
    $this->assertSame('Spanish title', $row->fieldValue('title', 'es'));
    $this->assertArrayNotHasKey('csv_row', $row->fieldsByLanguage['en']);
  }

  public function testTransformationsPreserveMetadataAndLeaveInputUntouched(): void {
    $row = new ImportRow('organizational_unit', 42, [
      'en' => ['title' => 'Unit', 'field_parent_organizational_unit' => 'parent'],
      'es' => ['title' => 'Unidad', 'field_parent_organizational_unit' => 'parent'],
    ]);
    $filtered = $row->withoutFields(['field_parent_organizational_unit']);
    $this->assertSame('parent', $row->fieldValue('field_parent_organizational_unit'));
    $this->assertSame(['en' => ['title' => 'Unit'], 'es' => ['title' => 'Unidad']], $filtered->fieldsByLanguage);
    $resolved = $filtered->withFields(['en' => ['title' => 'Updated']]);
    $this->assertSame('Unit', $filtered->fieldValue('title'));
    $this->assertSame('Updated', $resolved->fieldValue('title'));
    $this->assertSame('organizational_unit', $resolved->bundle);
    $this->assertSame(42, $resolved->rowNumber);
  }

  public function testRowNumberMustBePositive(): void {
    $this->expectException(\InvalidArgumentException::class);
    new ImportRow('programme', 0, []);
  }

}
