<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\Conversion\CoordinatesConverter;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use PHPUnit\Framework\TestCase;

/**
 * Tests WKT conversion and explicit coordinate clearing independently of Drupal.
 *
 * @group dacem_csv_import_export
 */
class CoordinatesConverterTest extends TestCase {

  /**
   * @dataProvider coordinates
   */
  public function testCoordinateValues(array $value, mixed $expected): void {
    $row = new ImportRow('organizational_unit', 12, ['en' => ['field_ou_map' => $value]]);
    $converter = new CoordinatesConverter(new BundleCsvDefinitions());
    $converted = $converter->convert($row);
    $this->assertSame($expected, $converted->fieldValue('field_ou_map'));
    $this->assertSame($value, $row->fieldValue('field_ou_map'));
    $this->assertSame('organizational_unit', $converted->bundle);
    $this->assertSame(12, $converted->rowNumber);
  }

  public static function coordinates(): array {
    return [
      'longitude first' => [['lat' => '47.5', 'lon' => '19'], 'POINT (19.000000 47.500000)'],
      'zero coordinates' => [['lat' => '0', 'lon' => '0'], 'POINT (0.000000 0.000000)'],
      'negative coordinates' => [['lat' => '-33.5', 'lon' => '-70.5'], 'POINT (-70.500000 -33.500000)'],
      'clear' => [['lat' => '', 'lon' => ''], []],
      'partial empty' => [['lat' => '', 'lon' => '19'], ['lat' => '', 'lon' => '19']],
      'partial missing' => [['lat' => '47.5'], ['lat' => '47.5']],
    ];
  }

  public function testAbsentFieldsAndUnconfiguredBundle(): void {
    $converter = new CoordinatesConverter(new BundleCsvDefinitions());
    $row = new ImportRow('organizational_unit', 2, ['en' => ['title' => 'Unit']]);
    $this->assertSame($row->fieldsByLanguage, $converter->convert($row)->fieldsByLanguage);
    $row = new ImportRow('programme', 3, ['en' => ['field_ou_map' => ['lat' => '1', 'lon' => '2']]]);
    $this->assertSame($row, $converter->convert($row));
  }

  public function testEveryLanguageIsConverted(): void {
    $row = new ImportRow('organizational_unit', 2, [
      'en' => ['field_ou_map' => ['lat' => '1', 'lon' => '2']],
      'es' => ['field_ou_map' => ['lat' => '', 'lon' => '']],
    ]);
    $converted = (new CoordinatesConverter(new BundleCsvDefinitions()))->convert($row);
    $this->assertSame('POINT (2.000000 1.000000)', $converted->fieldValue('field_ou_map'));
    $this->assertSame([], $converted->fieldValue('field_ou_map', 'es'));
  }

}
