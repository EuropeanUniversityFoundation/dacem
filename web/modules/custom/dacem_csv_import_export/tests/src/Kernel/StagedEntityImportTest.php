<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dacem_csv_import_export\AccessManager\UserAccessManager;
use Drupal\dacem_csv_import_export\CsvConverter\CsvDecoder;
use Drupal\dacem_csv_import_export\CsvImporter\CsvSorter;
use Drupal\dacem_csv_import_export\Import\ImportWriterInterface;
use Drupal\dacem_csv_import_export\Import\EntityImportValidator;
use Drupal\dacem_csv_import_export\Import\EntityImportWriter;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use Drupal\dacem_csv_import_export\Import\ImportWriteException;
use Drupal\dacem_csv_import_export\Repository\AmbiguousEntityException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use League\Csv\Reader;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Tests staged CSV updates against an isolated Drupal database.
 *
 * @group dacem_csv_import_export
 */
class StagedEntityImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'text', 'filter', 'node', 'language',
    'content_translation', 'datetime', 'link', 'taxonomy', 'dacem_csv_import_export',
  ];

  protected EntityStorageInterface $storage;
  protected CsvDecoder $decoder;
  protected EntityImportValidator $validator;
  protected EntityImportWriter $writer;
  protected UserAccessManager $accessManager;
  protected Node $institution;
  protected Node $programme;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // Group integration is outside this suite; entity persistence remains real.
    $container->register('dacem_csv_import_export.user_access_manager', UserAccessManager::class)
      ->setSynthetic(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'filter', 'node']);

    foreach (['en', 'es', 'pt-pt'] as $language) {
      if (!ConfigurableLanguage::load($language)) {
        ConfigurableLanguage::createFromLangcode($language)->save();
      }
    }
    foreach (['institution', 'organizational_unit', 'programme'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
    }
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'programme')
      ->setDefaultLangcode('en')
      ->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->setThirdPartySetting('content_translation', 'bundle_settings', ['untranslatable_fields_hide' => TRUE])
      ->save();

    $this->addField('institution', 'field_shac_code', 'string');
    $this->addField('programme', 'field_programme_code', 'string');
    $this->addField('programme', 'field_programme_institution', 'entity_reference', FALSE, ['target_type' => 'node']);
    $this->addField('programme', 'field_programme_ou', 'entity_reference', FALSE, ['target_type' => 'node']);
    $this->addField('programme', 'field_programme_start_date', 'datetime', FALSE, ['datetime_type' => 'date']);
    $this->addField('programme', 'field_programme_end_date', 'datetime', FALSE, ['datetime_type' => 'date']);
    $this->addField('programme', 'field_credits', 'integer');
    $this->addField('programme', 'field_programme_learn_outcomes', 'text_long', TRUE);

    FilterFormat::create(['format' => 'basic_html', 'name' => 'Test HTML'])->save();
    Role::load('authenticated')->grantPermission('use text format basic_html')->save();

    // An explicit user is owned by this test, not an existing site's user 1.
    $user = User::create(['name' => 'csv-test-user', 'status' => 1]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);
    $this->accessManager = $this->createMock(UserAccessManager::class);
    $this->container->set('dacem_csv_import_export.user_access_manager', $this->accessManager);

    $this->storage = $this->container->get('entity_type.manager')->getStorage('node');
    $this->decoder = $this->container->get('dacem_csv_import_export.csv_decoder');
    $this->validator = $this->container->get('dacem_csv_import_export.entity_import_validator');
    $this->writer = $this->container->get('dacem_csv_import_export.entity_import_writer');
    $this->institution = Node::create([
      'type' => 'institution', 'title' => 'Test institution',
      'field_shac_code' => 'csv-test.example',
    ]);
    $this->institution->save();
    $unit = Node::create(['type' => 'organizational_unit', 'title' => 'Test faculty']);
    $unit->save();
    $this->programme = Node::create([
      'type' => 'programme', 'langcode' => 'en', 'title' => 'Test programme',
      'field_programme_code' => 'csv-test-programme',
      'field_programme_institution' => $this->institution->id(),
      'field_programme_ou' => $unit->id(),
      'field_programme_start_date' => '2026-01-01',
      'field_programme_end_date' => '2027-12-31',
      'field_credits' => 60,
    ]);
    foreach (['es', 'pt-pt'] as $language) {
      $this->programme->addTranslation($language, ['title' => 'Test programme ' . $language]);
    }
    $this->programme->save();
    $this->programme = $this->storage->loadUnchanged($this->programme->id());
  }

  /**
   * Creates only the field configuration needed by these tests.
   */
  protected function addField(string $bundle, string $name, string $type, bool $translatable = FALSE, array $settings = [], int $cardinality = 1): void {
    FieldStorageConfig::create([
      'entity_type' => 'node', 'field_name' => $name, 'type' => $type,
      'settings' => $settings, 'cardinality' => $cardinality,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node', 'bundle' => $bundle, 'field_name' => $name,
      'label' => $name, 'translatable' => $translatable,
    ])->save();
  }

  /**
   * Decodes CSV cells and resolves references using the real import services.
   */
  protected function decode(array $cells = [], int $row_number = 2): ImportRow {
    $row = $this->decoder->decode('programme', $cells + [
      'field_programme_code' => 'csv-test-programme',
      'field_programme_institution' => 'csv-test.example',
    ], $row_number);
    return $this->container->get('dacem_csv_import_export.reference_resolver')
      ->resolveReferences($row, $this->institution);
  }

  /**
   * Reproduces clearing shared fields while updating two translations.
   */
  public function testSharedClearsAndTranslationUpdates(): void {
    $row = $this->decode([
      'field_programme_start_date' => '',
      'field_programme_end_date' => NULL,
      'field_programme_ou' => '',
      'field_programme_learn_outcomes|es' => 'Spanish outcomes',
      'field_programme_learn_outcomes|pt-pt' => 'Portuguese outcomes',
    ]);
    $before = $this->programme->toArray();
    $violations = $this->validator->validate($row, $this->programme);
    $this->assertCount(0, $violations, (string) $violations);
    $this->assertSame($before, $this->programme->toArray());
    $this->assertSame($before, $this->storage->loadUnchanged($this->programme->id())->toArray());

    // Confirm the fixture enables the restriction that caused the regression.
    $combined = clone $this->programme;
    $planner = $this->container->get('dacem_csv_import_export.entity_import_planner');
    $applier = $this->container->get('dacem_csv_import_export.entity_import_applier');
    foreach ($planner->plan($combined, $row) as $stage) {
      $combined = $applier->apply($combined, $stage);
    }
    $this->assertStringContainsString(
      'Non-translatable fields can only be changed when updating the original language.',
      (string) $combined->validate(),
    );

    $batch = [$row];
    $this->writer->writeMultiple($batch, $this->institution);
    $saved = $this->storage->loadUnchanged($this->programme->id());
    foreach (['field_programme_start_date', 'field_programme_end_date', 'field_programme_ou'] as $field) {
      $this->assertTrue($saved->get($field)->isEmpty(), $field);
    }
    $this->assertSame('Spanish outcomes', $saved->getTranslation('es')->get('field_programme_learn_outcomes')->value);
    $this->assertSame('Portuguese outcomes', $saved->getTranslation('pt-pt')->get('field_programme_learn_outcomes')->value);
    $this->assertSame('60', (string) $saved->get('field_credits')->value);
    $this->assertTrue($saved->isDefaultTranslationAffectedOnly());
  }

  /**
   * Empty cells clear, missing columns preserve, and zero remains a value.
   */
  public function testEmptyMissingAndZeroValues(): void {
    $row = $this->decode(['field_credits' => '0', 'field_programme_start_date' => '']);
    $batch = [$row];
    $this->writer->writeMultiple($batch, $this->institution);
    $saved = $this->storage->loadUnchanged($this->programme->id());
    $this->assertSame('0', (string) $saved->get('field_credits')->value);
    $this->assertTrue($saved->get('field_programme_start_date')->isEmpty());
    $this->assertSame('2027-12-31', $saved->get('field_programme_end_date')->value);
    $this->assertSame($this->programme->get('field_programme_ou')->target_id, $saved->get('field_programme_ou')->target_id);
  }

  /**
   * Clearing a required translated field is still rejected during preflight.
   */
  public function testRequiredFieldValidation(): void {
    $row = $this->decode(['title|es' => '']);
    $violations = $this->validator->validate($row, $this->programme);
    $this->assertGreaterThan(0, $violations->count());
    $this->assertSame('Test programme es', $this->storage->loadUnchanged($this->programme->id())->getTranslation('es')->label());
  }

  /**
   * A later invalid translation rolls back earlier rows and saved stages.
   */
  public function testBatchRollbackOnInvalidTranslation(): void {
    $before = $this->storage->loadUnchanged($this->programme->id())->toArray();
    $batch = [
      $this->decode(['field_credits' => '90']),
      $this->decode(['field_programme_start_date' => '', 'title|es' => '']),
    ];
    try {
      $this->writer->writeMultiple($batch, $this->institution);
      $this->fail('The invalid translation should fail validation.');
    }
    catch (ImportWriteException $exception) {
      $this->assertInstanceOf(ValidationFailedException::class, $exception->getPrevious());
      $this->assertGreaterThan(0, $exception->getPrevious()->getViolations()->count());
    }
    $this->assertSame($before, $this->storage->loadUnchanged($this->programme->id())->toArray());
  }

  /**
   * A group assignment failure also rolls back the saved entity stages.
   */
  public function testRollbackOnGroupAssignmentFailure(): void {
    $before = $this->storage->loadUnchanged($this->programme->id())->toArray();
    $this->accessManager->expects($this->once())
      ->method('addEntityToInstitutionGroup')
      ->willThrowException(new \RuntimeException('Simulated group assignment failure'));
    $batch = [$this->decode(['field_credits' => '90'])];
    try {
      $this->writer->writeMultiple($batch, $this->institution);
      $this->fail('The simulated group assignment should fail.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Simulated group assignment failure', $exception->getMessage());
    }
    $this->assertSame($before, $this->storage->loadUnchanged($this->programme->id())->toArray());
  }

  /**
   * Planning respects the real original language and ignores row metadata.
   */
  public function testOriginalLanguagePlanningAndApplication(): void {
    $node = Node::create(['type' => 'programme', 'langcode' => 'es', 'title' => 'Original title']);
    $before = $node->toArray();
    $planner = $this->container->get('dacem_csv_import_export.entity_import_planner');
    $stages = $planner->plan($node, new ImportRow('programme', 11, ['en' => [
      'title' => 'English title', 'field_credits' => '0',
      'field_programme_start_date' => '', 'type' => 'wrong-bundle',
      'langcode' => 'en', 'unknown_field' => 'ignored',
    ]]));
    $this->assertSame($before, $node->toArray());
    $this->assertSame('es', $stages[0]->language);
    $this->assertSame(['field_credits' => '0', 'field_programme_start_date' => ''], $stages[0]->fields);
    $this->assertSame('en', $stages[1]->language);
    $this->assertSame(['title' => 'English title'], $stages[1]->fields);
    foreach ($stages as $stage) {
      $node = $this->container->get('dacem_csv_import_export.entity_import_applier')->apply($node, $stage);
    }
    $this->assertTrue($node->isNew());
    $this->assertSame('es', $node->language()->getId());
    $this->assertSame('English title', $node->getTranslation('en')->label());
  }

  /**
   * The writer creates new entities and their translations from decoded rows.
   */
  public function testCreateProgramme(): void {
    $row = $this->decode([
      'field_programme_code' => 'new-programme',
      'title|en' => 'New programme', 'title|es' => 'New Spanish programme',
    ]);
    $this->assertCount(0, $this->validator->validate($row));
    $batch = [$row];
    $this->writer->writeMultiple($batch, $this->institution);
    $nodes = $this->storage->loadByProperties(['field_programme_code' => 'new-programme']);
    $this->assertCount(1, $nodes);
    $created = reset($nodes);
    $this->assertSame('New programme', $created->label());
    $this->assertSame('New Spanish programme', $created->getTranslation('es')->label());
    $this->assertSame($this->institution->id(), $created->get('field_programme_institution')->target_id);
  }

  /**
   * Bundle definitions preserve export order, date values, and reference codes.
   */
  public function testExportUsesBundleDefinition(): void {
    $mapping = $this->container->get('dacem_csv_import_export.field_mapping_service');
    $metadata = $mapping->generateFieldMetadata('node', 'programme', [$this->programme]);
    // Column language order follows metadata, not entity storage order.
    $metadata['languages'] = ['en' => TRUE, 'es' => TRUE, 'pt-pt' => TRUE];
    $schema = $this->container->get('dacem_csv_import_export.csv_schema_compiler')->compile('programme', $metadata);
    $this->assertSame([
      'field_programme_institution', 'field_programme_ou',
      'title|en', 'title|es', 'title|pt-pt', 'field_programme_code',
      'field_credits', 'field_programme_start_date', 'field_programme_end_date',
      'field_programme_learn_outcomes|en', 'field_programme_learn_outcomes|es',
      'field_programme_learn_outcomes|pt-pt',
    ], $schema->headers());
    $row = array_combine($schema->headers(), $mapping->exportEntityRow($this->programme, $schema));
    $this->assertSame('csv-test.example', $row['field_programme_institution']);
    $this->assertSame('2026-01-01', $row['field_programme_start_date']);
    $this->assertSame('2027-12-31', $row['field_programme_end_date']);

    // Bundles without a custom layout still use field-definition order.
    $fallback = $this->container->get('dacem_csv_import_export.csv_schema_compiler')->compile('other_bundle', $metadata);
    $this->assertContains('field_programme_start_date', $fallback->headers());
    $definitions = $this->container->get('dacem_csv_import_export.bundle_csv_definitions');
    $this->assertNull($definitions->find('other_bundle'));
  }

  /**
   * Required headers, values, and identity checks use the bundle definition.
   */
  public function testFileValidationUsesBundleDefinition(): void {
    $definition = $this->container->get('dacem_csv_import_export.bundle_csv_definitions')->get('programme');
    $record = array_fill_keys($definition->requiredSharedColumns, '1');
    foreach ($definition->requiredTranslatedColumns as $column) {
      $record[$column . '|en'] = 'Required text';
    }
    $record['field_programme_institution'] = 'csv-test.example';
    $record['field_programme_code'] = 'csv-test-programme';
    $record['field_credits'] = '0';
    $this->accessManager->method('getSchacCodesFromInstitutions')->willReturn(['csv-test.example']);
    $this->accessManager->method('getSchacCodeFromInstitution')->willReturn('csv-test.example');
    $validator = $this->container->get('dacem_csv_import_export.file_validator');
    $this->assertSame([], $validator->validateFileData([$record], array_keys($record), 'programme', [$this->institution]));

    // Retain the cell to independently check header and required-value errors.
    $record['field_eqf_level'] = '';
    $headers = array_values(array_diff(array_keys($record), ['field_eqf_level']));
    $errors = $validator->validateFileData([$record], $headers, 'programme', [$this->institution]);
    $this->assertSame('field_eqf_level', $errors[0]->column);
    $errors = $validator->validateFileData([$record], array_keys($record), 'programme', [$this->institution]);
    $this->assertSame('field_eqf_level', $errors[0]->column);
    $this->assertSame('Required value is missing', $errors[0]->type);
  }

  /**
   * Organizational-unit dependency ordering and coordinate rules are preserved.
   */
  public function testOrganizationalUnitRules(): void {
    $records = [
      ['field_ou_code' => 'child', 'field_parent_organizational_unit' => 'parent'],
      ['field_ou_code' => 'parent', 'field_parent_organizational_unit' => ''],
    ];
    $sorter = $this->container->get('dacem_csv_import_export.csv_sorter');
    $sorted = $sorter->sortData($records, 'organizational_unit');
    $this->assertSame(['parent', 'child'], array_column($sorted, 'field_ou_code'));
    $this->assertSame([1, 0], array_keys($sorted));
    $this->assertArrayNotHasKey('csv_row', $sorted[1]);
    $map = $this->decoder->decode('organizational_unit', ['field_ou_map___lat' => '', 'field_ou_map___lon' => '']);
    $this->assertSame([], $map->fieldsByLanguage['en']['field_ou_map']);
    $map = $this->decoder->decode('organizational_unit', ['field_ou_map___lat' => '47.5', 'field_ou_map___lon' => '19.0']);
    $this->assertSame('POINT (19.000000 47.500000)', $map->fieldsByLanguage['en']['field_ou_map']);

    $definition = $this->container->get('dacem_csv_import_export.bundle_csv_definitions')->get('organizational_unit');
    $this->assertSame(['field_parent_organizational_unit'], $definition->validationSkippedFields);
    $this->assertSame('on_save', $definition->referenceResolution[1]['runs']);
  }

  /**
   * Entity identity and error labels come from the same bundle definition.
   */
  public function testBundleEntityValidation(): void {
    $row = $this->decode(['title|es' => ''], 1);
    $validator = $this->container->get('dacem_csv_import_export.import_row_validator');
    $errors = $validator->validateMultiple([$row], $this->institution);
    $this->assertSame('Invalid Programme data', $errors[0]->type);
    $this->assertSame('title|es', $errors[0]->column);
    $this->assertSame('es', $errors[0]->language);
    $this->assertSame(1, $errors[0]->rowNumber);
  }

  /**
   * Export-only definitions do not accidentally enable additional import types.
   */
  public function testUnsupportedImportBundle(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->container->get('dacem_csv_import_export.file_validator')
      ->validateFileData([], [], 'institution', []);
  }

  /**
   * Parsed column addresses preserve each row's actual cells and languages.
   */
  public function testDecodeMultipleRowsWithPropertyAndDeltaSuffixes(): void {
    $rows = $this->decoder->decodeMultiple('programme', [
      [
        'title|en' => 'First',
        'field_tags____0' => 'A',
        'field_web___uri|es' => 'https://example.com',
        'field_web____0___uri|pt-pt' => '',
        'field_web____0___title|pt-pt' => '0',
      ],
      [
        'title|en' => 'Second',
        'field_web____0___title|pt-pt' => 'Another link',
        'field_credits' => '0',
      ],
    ]);
    $this->assertSame('A', $rows[0]->fieldsByLanguage['en']['field_tags'][0]);
    $this->assertSame(['uri' => 'https://example.com'], $rows[0]->fieldsByLanguage['es']['field_web']);
    $this->assertSame(['uri' => '', 'title' => '0'], $rows[0]->fieldsByLanguage['pt-pt']['field_web'][0]);
    $this->assertSame(['title' => 'Another link'], $rows[1]->fieldsByLanguage['pt-pt']['field_web'][0]);
    $this->assertArrayNotHasKey('field_credits', $rows[0]->fieldsByLanguage['en']);
    $this->assertSame('0', $rows[1]->fieldsByLanguage['en']['field_credits']);
  }

  /**
   * Bad suffixes produce actionable file errors before any data is processed.
   */
  public function testMalformedHeaderIsReportedAsFileError(): void {
    $errors = $this->container->get('dacem_csv_import_export.file_validator')
      ->validateFileData([], ['field_credits____invalid'], 'programme', []);
    $this->assertSame('Invalid CSV header', $errors[0]->type);
    $this->assertSame('field_credits____invalid', $errors[0]->column);
    $this->assertSame('CSV Headers', $errors[0]->rowNumber);
  }

  /**
   * Real multi-value link properties survive export-schema and decoder mapping.
   */
  public function testLinkColumnRoundTrip(): void {
    $this->addField('programme', 'field_programme_web', 'link', TRUE, [], 2);
    $node = $this->storage->loadUnchanged($this->programme->id());
    $node->set('field_programme_web', [
      ['uri' => 'https://example.com/first', 'title' => 'First'],
      ['uri' => 'https://example.com/second', 'title' => 'Second'],
    ]);
    $node->getTranslation('es')->set('field_programme_web', [
      ['uri' => 'https://example.com/es', 'title' => 'Uno'],
    ]);
    $mapping = $this->container->get('dacem_csv_import_export.field_mapping_service');
    $metadata = $mapping->generateFieldMetadata('node', 'programme', [$node]);
    $metadata['languages'] = ['en' => TRUE, 'es' => TRUE];
    $schema = $this->container->get('dacem_csv_import_export.csv_schema_compiler')->compile('programme', $metadata);
    $record = array_combine($schema->headers(), $mapping->exportEntityRow($node, $schema));
    $this->assertSame('Second', $record['field_programme_web____1___title|en']);
    $this->assertSame('', $record['field_programme_web____1___uri|es']);
    $decoded = $this->decoder->decode('programme', $record);
    $this->assertSame('https://example.com/first', $decoded->fieldsByLanguage['en']['field_programme_web'][0]['uri']);
    $this->assertSame('Second', $decoded->fieldsByLanguage['en']['field_programme_web'][1]['title']);
    $this->assertSame('Uno', $decoded->fieldsByLanguage['es']['field_programme_web'][0]['title']);
    $this->assertSame(['uri' => '', 'title' => ''], $decoded->fieldsByLanguage['es']['field_programme_web'][1]);
  }

  /**
   * Virtual course institution columns still traverse programme references.
   */
  public function testVirtualReferenceColumnExport(): void {
    $bundle = 'individual_educational_component';
    NodeType::create(['type' => $bundle, 'name' => 'Course'])->save();
    $this->addField($bundle, 'field_iec_code', 'string');
    $this->addField($bundle, 'field_iec_programme', 'entity_reference', FALSE, ['target_type' => 'node']);
    $course = Node::create([
      'type' => $bundle, 'langcode' => 'en', 'title' => 'Test course',
      'field_iec_code' => 'course-001', 'field_iec_programme' => $this->programme->id(),
    ]);
    $mapping = $this->container->get('dacem_csv_import_export.field_mapping_service');
    $metadata = $mapping->generateFieldMetadata('node', $bundle, [$course]);
    $schema = $this->container->get('dacem_csv_import_export.csv_schema_compiler')->compile($bundle, $metadata);
    $record = array_combine($schema->headers(), $mapping->exportEntityRow($course, $schema));
    $this->assertSame('csv-test.example', $record['field_iec_programme.entity.field_programme_institution']);
    $this->assertSame('csv-test-programme', $record['field_iec_programme']);
    $this->assertSame('course-001', $record['field_iec_code']);
  }

  /**
   * Reference resolution keeps source metadata and never mutates the input row.
   */
  public function testRowMetadataAndReferenceResolution(): void {
    $row = $this->decoder->decode('programme', [
      'field_programme_institution' => 'csv-test.example',
      'field_credits' => '0', 'title|es' => 'Spanish title',
      'type' => 'institution', 'langcode' => 'es', 'csv_row' => 999,
    ], 42);
    $this->assertInstanceOf(ImportRow::class, $row);
    $this->assertSame('programme', $row->bundle);
    $this->assertSame(42, $row->rowNumber);
    foreach ($row->fieldsByLanguage as $fields) {
      foreach (['type', 'langcode', 'csv_row'] as $metadata) {
        $this->assertArrayNotHasKey($metadata, $fields);
      }
    }
    $resolved = $this->container->get('dacem_csv_import_export.reference_resolver')
      ->resolveReferences($row, $this->institution);
    $this->assertSame('csv-test.example', $row->fieldValue('field_programme_institution'));
    $this->assertSame($this->institution->id(), $resolved->fieldValue('field_programme_institution')->id());
    $this->assertSame('0', $resolved->fieldValue('field_credits'));
    $this->assertSame($row->bundle, $resolved->bundle);
    $this->assertSame(42, $resolved->rowNumber);
  }

  /**
   * Sorted rows retain their original CSV record numbers in validation errors.
   */
  public function testSortedRowsRetainSourceNumbers(): void {
    $this->addOrganizationalUnitIdentityFields();
    $reader = Reader::fromString("field_ou_institution,field_ou_code,field_parent_organizational_unit,title|en\ncsv-test.example,child,parent,Child\ncsv-test.example,parent,,\n");
    $reader->setHeaderOffset(0);
    $records = iterator_to_array($reader->getRecords());
    $this->assertSame([1, 2], array_keys($records));
    $sorted = $this->container->get('dacem_csv_import_export.csv_sorter')->sortData($records, 'organizational_unit');
    $rows = $this->decoder->decodeMultiple('organizational_unit', $sorted);
    $rows = $this->container->get('dacem_csv_import_export.reference_resolver')
      ->resolveReferencesMultiple($rows, $this->institution);
    $this->assertSame([3, 2], array_column($rows, 'rowNumber'));
    $this->assertSame('parent', $rows[0]->fieldValue('field_ou_code'));
    $this->assertSame('child', $rows[1]->fieldValue('field_ou_code'));
    $errors = $this->container->get('dacem_csv_import_export.import_row_validator')
      ->validateMultiple($rows, $this->institution);
    $this->assertCount(1, $errors);
    $this->assertSame(3, $errors[0]->rowNumber);
    $this->assertSame('title|en', $errors[0]->column);
    $this->assertSame('parent', $rows[1]->fieldValue('field_parent_organizational_unit'));
  }

  /**
   * A dependency cycle is returned before typed rows reach decoding or saving.
   */
  public function testImportStopsOnDependencyCycle(): void {
    $this->addOrganizationalUnitIdentityFields();
    $this->accessManager->method('loadCurrentUserInstitutions')->willReturn([$this->institution]);
    $this->accessManager->method('getSchacCodesFromInstitutions')->willReturn(['csv-test.example']);
    $this->accessManager->method('getSchacCodeFromInstitution')->willReturn('csv-test.example');
    $path = tempnam(sys_get_temp_dir(), 'csv-cycle-');
    try {
      file_put_contents($path, "field_ou_institution,field_ou_code,field_parent_organizational_unit,title|en,field_ou_description|en,field_ou_type|en\ncsv-test.example,a,b,A,Description,Faculty\ncsv-test.example,b,a,B,Description,Faculty\n");
      $file = File::create(['uri' => $path]);
      $result = $this->container->get('dacem_csv_import_export.csv_importer')
        ->import($file, 'organizational_unit', TRUE);
      $this->assertFalse($result->isSuccessful());
      $this->assertSame(CsvSorter::CYCLIC_ERROR_NAME, $result->errors[0]->type);
    }
    finally {
      unlink($path);
    }
  }

  /**
   * An import row cannot accidentally apply changes to another bundle.
   */
  public function testPlannerRejectsMismatchedBundle(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->container->get('dacem_csv_import_export.entity_import_planner')
      ->plan($this->programme, new ImportRow('institution', 2, ['en' => ['title' => 'Wrong bundle']]));
  }

  /**
   * Exercises completed imports and save failures through the CSV service.
   *
   * @dataProvider importOutcomes
   */
  public function testCsvFileImportUsesTypedRows(string $outcome): void {
    $this->installEntitySchema('taxonomy_term');
    Vocabulary::create(['vid' => 'languages', 'name' => 'Languages'])->save();
    $term = Term::create(['vid' => 'languages', 'name' => 'English']);
    $term->save();
    $this->addField('programme', 'field_programme_language_of_inst', 'entity_reference', FALSE, ['target_type' => 'taxonomy_term'], -1);
    $this->accessManager->method('loadCurrentUserInstitutions')->willReturn([$this->institution]);
    $this->accessManager->method('getSchacCodesFromInstitutions')->willReturn(['csv-test.example']);
    $this->accessManager->method('getSchacCodeFromInstitution')->willReturn('csv-test.example');
    $before = $this->storage->loadUnchanged($this->programme->id())->toArray();
    $assignment = $this->accessManager->expects($outcome === 'validation' ? $this->never() : $this->once())
      ->method('addEntityToInstitutionGroup');
    if ($outcome === 'storage') {
      $assignment->willThrowException(new \RuntimeException('Private database diagnostic'));
    }
    if ($outcome === 'validation') {
      $writer = $this->createMock(ImportWriterInterface::class);
      $writer->method('writeMultiple')->willReturnCallback(function (array $rows): void {
        $entity = clone $this->programme;
        $entity->getTranslation('es')->set('title', '');
        throw new ImportWriteException($rows[0], 'es', new ValidationFailedException($entity, $entity->getTranslation('es')->validate()));
      });
      $this->container->set('dacem_csv_import_export.entity_import_writer', $writer);
    }
    $definition = $this->container->get('dacem_csv_import_export.bundle_csv_definitions')->get('programme');
    $record = array_fill_keys($definition->requiredSharedColumns, '1');
    foreach (['en', 'es', 'pt-pt'] as $language) {
      foreach ($definition->requiredTranslatedColumns as $column) {
        $record[$column . '|' . $language] = 'Imported ' . $language;
      }
    }
    $record['field_programme_institution'] = 'csv-test.example';
    $record['field_programme_code'] = 'csv-test-programme';
    $record['field_programme_language_of_inst____0'] = 'English';
    $record['field_programme_start_date'] = '';
    $record['field_programme_end_date'] = '';
    $record['field_programme_ou'] = '';
    $path = tempnam(sys_get_temp_dir(), 'csv-import-');
    try {
      $stream = fopen($path, 'w');
      fputcsv($stream, array_keys($record));
      fputcsv($stream, array_values($record));
      fclose($stream);
      $result = $this->container->get('dacem_csv_import_export.csv_importer')
        ->import(File::create(['uri' => $path]), 'programme');
      if ($outcome !== 'success') {
        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $result->importedCount);
        $this->assertSame(2, $result->errors[0]->rowNumber);
        $this->assertSame($before, $this->storage->loadUnchanged($this->programme->id())->toArray());
        if ($outcome === 'validation') {
          $this->assertSame('title|es', $result->errors[0]->column);
          $this->assertSame('es', $result->errors[0]->language);
          $this->assertStringContainsString('should not be null', $result->errors[0]->message);
        }
        else {
          $this->assertSame('Save failed', $result->errors[0]->type);
          $this->assertStringNotContainsString('Private database diagnostic', $result->errors[0]->message);
        }
        return;
      }
      $this->assertTrue($result->isSuccessful());
      $this->assertSame([], $result->errors);
      $this->assertSame(1, $result->importedCount);
      $saved = $this->storage->loadUnchanged($this->programme->id());
      $this->assertTrue($saved->get('field_programme_start_date')->isEmpty());
      $this->assertTrue($saved->get('field_programme_end_date')->isEmpty());
      $this->assertTrue($saved->get('field_programme_ou')->isEmpty());
      $this->assertSame('Imported es', $saved->getTranslation('es')->get('field_programme_learn_outcomes')->value);
      $this->assertSame($term->id(), $saved->get('field_programme_language_of_inst')->target_id);
    }
    finally {
      unlink($path);
    }
  }

  public static function importOutcomes(): array {
    return [['success'], ['storage'], ['validation']];
  }

  /**
   * Deferred references resolve after parent creation without mutating rows.
   */
  public function testDeferredReferencesUseCopiedRows(): void {
    $this->addOrganizationalUnitIdentityFields();
    $records = [
      1 => ['field_ou_institution' => 'csv-test.example', 'field_ou_code' => 'child', 'field_parent_organizational_unit' => 'parent', 'title|en' => 'Child'],
      2 => ['field_ou_institution' => 'csv-test.example', 'field_ou_code' => 'parent', 'field_parent_organizational_unit' => '', 'title|en' => 'Parent'],
    ];
    $sorted = $this->container->get('dacem_csv_import_export.csv_sorter')->sortData($records, 'organizational_unit');
    $rows = $this->decoder->decodeMultiple('organizational_unit', $sorted);
    $rows = $this->container->get('dacem_csv_import_export.reference_resolver')
      ->resolveReferencesMultiple($rows, $this->institution);
    $this->accessManager->expects($this->exactly(2))->method('addEntityToInstitutionGroup');
    $this->writer->writeMultiple($rows, $this->institution);
    $parents = $this->storage->loadByProperties(['type' => 'organizational_unit', 'field_ou_code' => 'parent']);
    $children = $this->storage->loadByProperties(['type' => 'organizational_unit', 'field_ou_code' => 'child']);
    $this->assertCount(1, $parents);
    $this->assertCount(1, $children);
    $this->assertSame(reset($parents)->id(), reset($children)->get('field_parent_organizational_unit')->target_id);
    $this->assertSame('parent', $rows[1]->fieldValue('field_parent_organizational_unit'));
    $this->assertSame([3, 2], array_column($rows, 'rowNumber'));
  }

  /**
   * Unique lookups return NULL when missing and never choose a duplicate.
   */
  public function testRepositoryIdentityScopeAndDuplicates(): void {
    $repository = $this->container->get('dacem_csv_import_export.catalogue_repository');
    $row = $this->decode();
    $this->assertSame($this->programme->id(), $repository->findImportTarget($row, $this->institution)->id());
    $this->assertNull($repository->findImportTarget($this->decode(['field_programme_code' => 'missing']), $this->institution));
    $this->assertNull($repository->findInstitutionBySchacCode('missing.example'));
    $this->assertSame([], $repository->findInstitutionsBySchacCodes([]));
    $other = Node::create(['type' => 'institution', 'title' => 'Other', 'field_shac_code' => 'other.example']);
    $other->save();
    $other_programme = Node::create([
      'type' => 'programme', 'title' => 'Other programme',
      'field_programme_code' => 'csv-test-programme', 'field_programme_institution' => $other->id(),
    ]);
    $other_programme->save();
    $this->assertSame($other_programme->id(), $repository->findImportTarget($row, $other)->id());
    $this->assertSame($this->programme->id(), $repository->findImportTarget($row, $this->institution)->id());
    Node::create([
      'type' => 'programme', 'title' => 'Duplicate programme',
      'field_programme_code' => 'csv-test-programme', 'field_programme_institution' => $this->institution->id(),
    ])->save();
    $errors = $this->container->get('dacem_csv_import_export.import_row_validator')
      ->validateMultiple([$row], $this->institution);
    $this->assertSame('Ambiguous identity', $errors[0]->type);
    $this->assertSame('field_programme_code', $errors[0]->column);
    $this->assertSame($row->rowNumber, $errors[0]->rowNumber);
    $this->expectException(AmbiguousEntityException::class);
    $repository->findImportTarget($row, $this->institution);
  }

  /**
   * An institution code must identify one institution, not an arbitrary match.
   */
  public function testDuplicateInstitutionLookupIsRejected(): void {
    Node::create(['type' => 'institution', 'title' => 'Duplicate', 'field_shac_code' => 'csv-test.example'])->save();
    $this->expectException(AmbiguousEntityException::class);
    $this->container->get('dacem_csv_import_export.catalogue_repository')
      ->findInstitutionBySchacCode('csv-test.example');
  }

  /**
   * Course preflight and persistence agree on the programme/institution path.
   */
  public function testCourseLookupAndUpdateAcrossInstitutions(): void {
    $bundle = 'individual_educational_component';
    NodeType::create(['type' => $bundle, 'name' => 'Course'])->save();
    $this->addField($bundle, 'field_iec_code', 'string');
    $this->addField($bundle, 'field_iec_programme', 'entity_reference', FALSE, ['target_type' => 'node']);
    $course = Node::create([
      'type' => $bundle, 'title' => 'Original course', 'field_iec_code' => 'shared-code',
      'field_iec_programme' => $this->programme->id(),
    ]);
    $course->save();
    $other = Node::create(['type' => 'institution', 'title' => 'Other', 'field_shac_code' => 'other.example']);
    $other->save();
    $programme = Node::create([
      'type' => 'programme', 'title' => 'Other programme', 'field_programme_code' => 'other-programme',
      'field_programme_institution' => $other->id(),
    ]);
    $programme->save();
    $foreign_course = Node::create([
      'type' => $bundle, 'title' => 'Foreign course', 'field_iec_code' => 'shared-code',
      'field_iec_programme' => $programme->id(),
    ]);
    $foreign_course->save();
    // A different bundle using the same fields must not match the course query.
    NodeType::create(['type' => 'other_course', 'name' => 'Other course type'])->save();
    foreach (['field_iec_code', 'field_iec_programme'] as $field) {
      FieldConfig::create(['entity_type' => 'node', 'bundle' => 'other_course', 'field_name' => $field, 'label' => $field])->save();
    }
    $other_bundle = Node::create([
      'type' => 'other_course', 'title' => 'Other bundle', 'field_iec_code' => 'shared-code',
      'field_iec_programme' => $this->programme->id(),
    ]);
    $other_bundle->save();
    $row = $this->decoder->decode($bundle, ['field_iec_code' => 'shared-code', 'field_iec_programme' => 'csv-test-programme'], 9);
    $row = $this->container->get('dacem_csv_import_export.reference_resolver')->resolveReferences($row, $this->institution);
    $repository = $this->container->get('dacem_csv_import_export.catalogue_repository');
    $this->assertSame($course->id(), $repository->findImportTarget($row, $this->institution)->id());
    $this->assertSame($foreign_course->id(), $repository->findImportTarget($row, $other)->id());
    // Omitting title is valid only when preflight finds the existing course.
    $this->assertSame([], $this->container->get('dacem_csv_import_export.import_row_validator')->validateMultiple([$row], $this->institution));
    $fields = $row->fieldsByLanguage;
    $fields['en']['title'] = 'Updated course';
    $this->writer->writeMultiple([$row->withFields($fields)], $this->institution);
    $this->assertSame('Updated course', $this->storage->loadUnchanged($course->id())->label());
    $this->assertSame('Foreign course', $this->storage->loadUnchanged($foreign_course->id())->label());
    $this->assertSame('Other bundle', $this->storage->loadUnchanged($other_bundle->id())->label());
    $this->assertCount(2, $this->storage->loadByProperties(['type' => $bundle, 'field_iec_code' => 'shared-code']));
  }

  /**
   * Adds the minimal schema required for real organizational-unit lookups.
   */
  protected function addOrganizationalUnitIdentityFields(): void {
    $this->addField('organizational_unit', 'field_ou_code', 'string');
    $this->addField('organizational_unit', 'field_ou_institution', 'entity_reference', FALSE, ['target_type' => 'node']);
    $this->addField('organizational_unit', 'field_parent_organizational_unit', 'entity_reference', FALSE, ['target_type' => 'node']);
  }

}
