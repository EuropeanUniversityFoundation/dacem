<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\dacem_csv_import_export\Import\Conversion\FormattedTextConverter;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use PHPUnit\Framework\TestCase;

/**
 * Tests text conversion independently of CSV parsing and entity persistence.
 *
 * @group dacem_csv_import_export
 */
class FormattedTextConverterTest extends TestCase {

  /**
   * @dataProvider formattedFieldTypes
   */
  public function testFormattedTextPreservesContentAndSource(string $type): void {
    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getType')->willReturn($type);
    $manager = $this->createMock(EntityFieldManagerInterface::class);
    $manager->expects($this->once())->method('getFieldDefinitions')
      ->with('node', 'programme')->willReturn(['field_text' => $definition]);
    $row = new ImportRow('programme', 42, [
      'en' => ['field_text' => "<p>First</p>\nSecond", 'title' => "Plain\ntext"],
      'es' => ['field_text' => "Uno\r\nDos"],
    ]);
    $converted = (new FormattedTextConverter($manager))->convert($row);
    $this->assertSame(['value' => "<p>First</p><br />\nSecond", 'format' => 'basic_html'], $converted->fieldValue('field_text'));
    $this->assertSame(['value' => "Uno<br />\r\nDos", 'format' => 'basic_html'], $converted->fieldValue('field_text', 'es'));
    $this->assertSame("Plain\ntext", $converted->fieldValue('title'));
    $this->assertSame("<p>First</p>\nSecond", $row->fieldValue('field_text'));
    $this->assertSame('programme', $converted->bundle);
    $this->assertSame(42, $converted->rowNumber);
  }

  public static function formattedFieldTypes(): array {
    return [['text'], ['text_long'], ['text_with_summary']];
  }

  public function testEmptyZeroAndMissingFields(): void {
    $text = $this->createMock(FieldDefinitionInterface::class);
    $text->method('getType')->willReturn('text_long');
    $plain = $this->createMock(FieldDefinitionInterface::class);
    $plain->method('getType')->willReturn('string');
    $manager = $this->createMock(EntityFieldManagerInterface::class);
    $manager->method('getFieldDefinitions')->willReturn([
      'field_empty' => $text, 'field_zero' => $text,
      'field_missing' => $text, 'field_plain' => $plain,
    ]);
    $row = new ImportRow('programme', 2, ['en' => [
      'field_empty' => '', 'field_zero' => '0', 'field_plain' => "Plain\ntext",
    ]]);
    $converted = (new FormattedTextConverter($manager))->convert($row);
    $this->assertSame(['value' => '', 'format' => 'basic_html'], $converted->fieldValue('field_empty'));
    $this->assertSame(['value' => '0', 'format' => 'basic_html'], $converted->fieldValue('field_zero'));
    $this->assertSame("Plain\ntext", $converted->fieldValue('field_plain'));
    $this->assertArrayNotHasKey('field_missing', $converted->fieldsByLanguage['en']);
  }

}
