<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Kernel;

use Drupal\dacem_csv_import_export\CsvImporter\CsvImporter;
use Drupal\dacem_csv_import_export\Form\ProgrammeImportForm;
use Drupal\dacem_csv_import_export\Import\Result\ImportError;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests validation formatting and escaping with Drupal's actual renderer.
 *
 * @group dacem_csv_import_export
 */
class ImportErrorRenderingTest extends KernelTestBase {

  protected static $modules = ['system'];

  public function testMessageFormattingAndUnsafeHtml(): void {
    $renderer = $this->container->get('renderer');
    $form = new class($this->createMock(CsvImporter::class), $renderer) extends ProgrammeImportForm {

      public function errorsTable(array $errors): array {
        return $this->createErrorsTable($errors);
      }

    };
    $table = $form->errorsTable([
      new ImportError(
        'Validation',
        '<em class="placeholder">1</em> is not a valid ISCED-F field of study. <script>alert(1)</script><a href="javascript:alert(1)">Details</a>',
        2,
        '<strong>column</strong>',
        ['<img src=x onerror="alert(1)">'],
      ),
    ]);
    $html = (string) $renderer->renderInIsolation($table);
    $this->assertStringContainsString('<em class="placeholder">1</em> is not a valid ISCED-F field of study.', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('href="javascript:', $html);
    $this->assertStringContainsString('&lt;strong&gt;column&lt;/strong&gt;', $html);
    $this->assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $html);
  }

}
