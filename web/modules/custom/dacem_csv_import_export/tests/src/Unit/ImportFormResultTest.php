<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Unit;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\dacem_csv_import_export\CsvImporter\CsvImporter;
use Drupal\dacem_csv_import_export\Form\ProgrammeImportForm;
use Drupal\dacem_csv_import_export\Import\Result\ImportError;
use Drupal\dacem_csv_import_export\Import\Result\ImportResult;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that import failures reach the form without a misleading success notice.
 *
 * @group dacem_csv_import_export
 */
class ImportFormResultTest extends UnitTestCase {

  public function testFailureDisplaysSourceContextAndEscapesCsvValues(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->once())->method('renderInIsolation')
      ->with($this->callback(function (array $table): bool {
        $cells = array_column($table['#rows'][0], 'data');
        $this->assertSame(['#plain_text' => '12'], $cells[0]);
        $this->assertSame([
          '#markup' => '<script>bad message</script>',
          '#allowed_tags' => ['em', 'strong', 'code', 'a', 'br'],
        ], $cells[2]);
        $this->assertSame(['#plain_text' => 'title|es'], $cells[3]);
        $this->assertSame(['#plain_text' => 'es'], $cells[4]);
        $this->assertSame(['#plain_text' => '<script>bad value</script>'], $cells[5]);
        return TRUE;
      }))->willReturn('<table>Rendered errors</table>');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->exactly(2))->method('addError');
    $messenger->expects($this->never())->method('addStatus');
    $form = $this->form($renderer, $messenger);
    $form->show(ImportResult::failure([
      new ImportError('Validation', '<script>bad message</script>', 12, 'title|es', ['<script>bad value</script>'], 'es'),
    ]));
  }

  public function testSuccessReportsCompletedCount(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->never())->method('renderInIsolation');
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('addError');
    $messenger->expects($this->once())->method('addStatus')
      ->with($this->callback(fn ($message): bool => str_contains((string) $message, '3') && !str_contains((string) $message, 'started')));
    $this->form($renderer, $messenger)->show(ImportResult::success(3));
  }

  private function form(RendererInterface $renderer, MessengerInterface $messenger): ResultTestForm {
    $form = new ResultTestForm($this->createMock(CsvImporter::class), $renderer);
    $form->setMessenger($messenger);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

}

/**
 * Exposes result presentation independently of managed-file form submission.
 */
class ResultTestForm extends ProgrammeImportForm {

  public function show(ImportResult $result): void {
    $this->displayResult($result);
  }

}
