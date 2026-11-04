<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use Drupal\dacem_csv_import_export\Schema\CsvSchemaCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Tests config-driven suffixes and export order without entity storage.
 *
 * @group dacem_csv_import_export
 */
class CsvSchemaCompilerTest extends TestCase {

  public function testMultiValuePropertyAndLanguageExpansion(): void {
    $compiler = new CsvSchemaCompiler(new BundleCsvDefinitions(), new CsvHeaderParser());
    $schema = $compiler->compile('other_bundle', [
      'languages' => ['en' => TRUE, 'pt-pt' => TRUE],
      'fields' => [
        'field_web' => [
          'multi-cardinality' => TRUE, 'max_delta' => 2,
          'translatable' => TRUE, 'properties' => ['uri', 'title'],
          'has_multiple_properties' => TRUE, 'is_reference' => FALSE,
        ],
      ],
    ]);
    $this->assertSame([
      'field_web____0___uri|en', 'field_web____0___title|en',
      'field_web____0___uri|pt-pt', 'field_web____0___title|pt-pt',
      'field_web____1___uri|en', 'field_web____1___title|en',
      'field_web____1___uri|pt-pt', 'field_web____1___title|pt-pt',
    ], $schema->headers());
    $this->assertSame('uri', $schema->columns[0]->valueProperty);
    $this->assertSame('title', $schema->columns[1]->valueProperty);
  }

  public function testSinglePropertyAndEmptyMultiValueField(): void {
    $compiler = new CsvSchemaCompiler(new BundleCsvDefinitions(), new CsvHeaderParser());
    $schema = $compiler->compile('other_bundle', ['fields' => [
      'field_reference' => [
        'properties' => ['target_id'], 'is_reference' => TRUE,
      ],
      'field_codes' => [
        'properties' => [], 'multi-cardinality' => TRUE, 'max_delta' => 0,
        'is_reference' => FALSE,
      ],
    ]]);
    $this->assertSame(['field_reference', 'field_codes____0'], $schema->headers());
    $this->assertNull($schema->columns[0]->column->property);
    $this->assertSame('target_id', $schema->columns[0]->valueProperty);
    $this->assertTrue($schema->columns[0]->isReference);
    $this->assertSame('value', $schema->columns[1]->valueProperty);
  }

  public function testVirtualColumnDoesNotRequireLocalFieldMetadata(): void {
    $compiler = new CsvSchemaCompiler(new BundleCsvDefinitions(), new CsvHeaderParser());
    $schema = $compiler->compile('individual_educational_component', []);
    $this->assertSame(['field_iec_programme.entity.field_programme_institution'], $schema->headers());
    $this->assertTrue($schema->columns[0]->column->isVirtualReference());
  }

}
