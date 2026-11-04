<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Functional;

use Drupal\ewp_institutions\Entity\InstitutionEntity;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;

/**
 * Exercises CSV upload, real group assignment, and user-visible error rendering.
 *
 * @group dacem_csv_import_export
 * @group csv_browser
 */
class CsvImportFormTest extends BrowserTestBase {

  protected static $modules = ['dacem_csv_import_export', 'taxonomy'];

  protected $defaultTheme = 'stable9';

  public function testUploadSuccessAndErrors(): void {
    foreach (['institution', 'programme'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
    }
    $this->addField('node', 'institution', 'field_shac_code', 'string');
    $this->addField('node', 'programme', 'field_programme_code', 'string');
    $this->addField('node', 'programme', 'field_programme_institution', 'entity_reference', ['target_type' => 'node']);
    $this->addField('node', 'programme', 'field_programme_language_of_inst', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
    foreach (['field_programme_description', 'field_programme_learn_outcomes'] as $name) {
      $this->addField('node', 'programme', $name, 'text_long');
    }
    if (!FilterFormat::load('basic_html')) {
      FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML'])->save();
    }
    $account = $this->drupalCreateUser(['access content', 'bypass node access', 'use text format basic_html']);
    Vocabulary::create(['vid' => 'languages', 'name' => 'Languages'])->save();
    Term::create(['vid' => 'languages', 'name' => 'English'])->save();
    $hei = InstitutionEntity::create(['label' => 'Browser HEI', 'hei_id' => 'browser-test.example']);
    $hei->save();
    Node::create(['type' => 'institution', 'title' => 'Browser institution', 'field_shac_code' => 'browser-test.example'])->save();
    $type = GroupType::create(['id' => 'institutions', 'label' => 'Institutions', 'creator_membership' => FALSE]);
    $type->save();
    $this->addField('group', 'institutions', 'field_institution_profile', 'entity_reference', ['target_type' => 'hei']);
    $this->container->get('entity_type.manager')->getStorage('group_relationship_type')
      ->createFromPlugin($type, 'group_node:programme')->save();
    $group = Group::create(['type' => 'institutions', 'label' => 'Browser group', 'field_institution_profile' => $hei->id()]);
    $group->save();
    $group->addMember($account);
    $definition = $this->container->get('dacem_csv_import_export.bundle_csv_definitions')->get('programme');
    $record = array_fill_keys($definition->requiredSharedColumns, '1');
    foreach ($definition->requiredTranslatedColumns as $name) {
      $record[$name . '|en'] = 'Browser programme';
    }
    $record['field_programme_institution'] = 'browser-test.example';
    $record['field_programme_code'] = 'browser-programme';
    $record['field_programme_language_of_inst____0'] = 'English';
    $temporary = tempnam(sys_get_temp_dir(), 'csv-browser-');
    $path = $temporary . '.csv';
    rename($temporary, $path);
    try {
      $stream = fopen($path, 'w');
      fputcsv($stream, array_keys($record));
      fputcsv($stream, array_values($record));
      fclose($stream);
      $this->drupalLogin($account);
      $this->drupalGet('/admin/import/programme');
      $this->submitForm(['files[csv_file]' => $path], 'Start Import');
      $this->assertSession()->pageTextContains('Imported 1 row for Programmes.');
      $this->assertSession()->elementNotExists('css', '.csv-error-table');
      $nodes = $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(['field_programme_code' => 'browser-programme']);
      $this->assertCount(1, $nodes);
      $this->assertCount(1, $group->getRelationshipsByEntity(reset($nodes), 'group_node:programme'));
      file_put_contents($path, "field_credits____invalid\n1\n");
      $this->drupalGet('/admin/import/programme');
      $this->submitForm(['files[csv_file]' => $path], 'Start Import');
      $this->assertSession()->pageTextContains('Import failed. No rows were imported.');
      $this->assertSession()->pageTextContains('Invalid CSV header');
      $this->assertSession()->pageTextContains('field_credits____invalid');
      $this->assertSession()->elementExists('css', '.csv-error-table');
      $this->assertSession()->pageTextNotContains('Imported 1 row');
    }
    finally {
      unlink($path);
    }
  }

  private function addField(string $entity_type, string $bundle, string $name, string $type, array $settings = [], int $cardinality = 1): void {
    FieldStorageConfig::create(['entity_type' => $entity_type, 'field_name' => $name, 'type' => $type, 'settings' => $settings, 'cardinality' => $cardinality])->save();
    FieldConfig::create(['entity_type' => $entity_type, 'bundle' => $bundle, 'field_name' => $name, 'label' => $name])->save();
  }

}
