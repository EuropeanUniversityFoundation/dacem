<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests CSV header addresses without booting Drupal or accessing a database.
 *
 * @group dacem_csv_import_export
 */
class CsvHeaderParserTest extends TestCase {

  /**
   * @dataProvider validHeaders
   */
  public function testHeaderRoundTrip(string $header, string $field, ?string $language, ?int $delta, ?string $property): void {
    $parser = new CsvHeaderParser();
    $column = $parser->parse($header);
    $this->assertSame($header, $column->header);
    $this->assertSame($field, $column->fieldName);
    $this->assertSame($language, $column->language);
    $this->assertSame($delta, $column->delta);
    $this->assertSame($property, $column->property);
    $this->assertEquals($column, $parser->create($field, $language, $delta, $property));
  }

  public static function validHeaders(): array {
    return [
      'scalar' => ['field_code', 'field_code', NULL, NULL, NULL],
      'language' => ['title|en', 'title', 'en', NULL, NULL],
      'regional language' => ['title|pt-pt', 'title', 'pt-pt', NULL, NULL],
      'delta zero' => ['field_codes____0', 'field_codes', NULL, 0, NULL],
      'delta' => ['field_codes____12', 'field_codes', NULL, 12, NULL],
      'property' => ['field_web___uri', 'field_web', NULL, NULL, 'uri'],
      'delta property' => ['field_web____1___title', 'field_web', NULL, 1, 'title'],
      'delta language' => ['field_codes____0|es', 'field_codes', 'es', 0, NULL],
      'property language' => ['field_web___uri|es', 'field_web', 'es', NULL, 'uri'],
      'all suffixes' => ['field_web____12___target_id|pt-pt', 'field_web', 'pt-pt', 12, 'target_id'],
      'virtual reference' => ['field_iec_programme.entity.field_programme_institution', 'field_iec_programme.entity.field_programme_institution', NULL, NULL, NULL],
    ];
  }

  /**
   * @dataProvider invalidHeaders
   */
  public function testMalformedSuffixesAreRejected(string $header): void {
    $this->expectException(\InvalidArgumentException::class);
    (new CsvHeaderParser())->parse($header);
  }

  public static function invalidHeaders(): array {
    return [
      [''], ['|en'], ['title|'], ['title|en|es'], ['field_web___'],
      ['field_codes____'], ['field_codes____x'], ['field_codes____-1'],
      ['field_codes____0___'], ['field_codes____0____1'],
      ['field_web___uri___title'], ['field_web___uri____0'],
    ];
  }

  public function testLanguageGroupingRetainsFieldSuffixes(): void {
    $parser = new CsvHeaderParser();
    $headers = ['field_code', 'field_web____0___uri|pt-pt', 'title|en', 'title|pt-pt'];
    $this->assertSame([
      'non-translatable' => ['field_code'],
      'translatable' => [
        'pt-pt' => ['field_web____0___uri', 'title'],
        'en' => ['title'],
      ],
    ], $parser->groupByLanguage($headers));
    $this->assertSame(['pt-pt', 'en'], $parser->languages($headers));
    $this->assertSame(['non-translatable' => [], 'translatable' => []], $parser->groupByLanguage([]));
  }

  public function testVirtualReferencePath(): void {
    $column = (new CsvHeaderParser())->parse('field_iec_programme.entity.field_programme_institution');
    $this->assertTrue($column->isVirtualReference());
    $this->assertSame(['field_iec_programme', 'field_programme_institution'], $column->referencePath());
  }

}
