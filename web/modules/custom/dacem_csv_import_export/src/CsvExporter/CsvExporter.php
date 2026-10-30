<?php

namespace Drupal\dacem_csv_import_export\CsvExporter;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\dacem_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\dacem_csv_import_export\CsvExporter\DacemCsvForceQuotesFilter;
use Drupal\dacem_csv_import_export\DataLoader\DataLoader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CsvExporter {

	protected DataLoader $dataLoader;
  protected EntityFieldManager $entityFieldManager;
  protected FieldMappingService $fieldMappingService;
  protected FileSystem $fileSystem;

  public function __construct(
		DataLoader $data_loader,
    EntityFieldManager $entity_field_manager,
    FieldMappingService $field_mapping_service,
    FileSystem $file_system,
  ) {
    $this->dataLoader = $data_loader;
    $this->entityFieldManager = $entity_field_manager;
    $this->fieldMappingService = $field_mapping_service;
    $this->fileSystem = $file_system;
  }

  public function export(string $entity_type_id, string $entity_bundle, array $entity_ids) {
    // Load entities to dynamically analyze headers to be created.
    $entities = $this->dataLoader->loadEntitiesByIds($entity_type_id, $entity_bundle, $entity_ids);
    $field_metadata = $this->fieldMappingService->generateFieldMetadata($entity_type_id, $entity_bundle, $entities);
    $schema = $this->fieldMappingService->generateHeadersAndBlueprint($entity_bundle, $field_metadata);
    $headers = $schema['headers'];
    $blueprint = $schema['blueprint'];

    // 4. Set up a secure temporary file on the system disk using league/csv
    $temp_dir = 'temporary://csv-exports';
    $this->fileSystem->prepareDirectory($temp_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $temp_filepath = $temp_dir . '/' . $entity_bundle . '-export-' . time() . '.csv';
    $physical_path = $this->fileSystem->realpath($temp_filepath);

    // Register our concrete class stream filter safely if it hasn't been defined in this cycle yet
    if (!in_array('dacem_csv_force_quotes', stream_get_filters())) {
      stream_filter_register('dacem_csv_force_quotes', DacemCsvForceQuotesFilter::class);
    }

    // 5. PASS 2: Stream-write data to disk
    $csv_writer = Writer::from($physical_path, 'w+');
    $csv_writer->appendStreamFilterOnWrite('dacem_csv_force_quotes');

    // Set UTF-8 BOM to ensure Excel opens multi-lingual characters correctly (e.g., Hungarian accents)
    $csv_writer->setOutputBOM(Writer::BOM_UTF8);
    $csv_writer->setDelimiter(',');

    // Add a modern Formatter closure to check values row by row
    $csv_writer->addFormatter(function (array $row) {
      return array_map(function ($value) {
        $strValue = (string) $value;

        // Target non-empty fields that start with 0 but are longer than a singular digit "0"
        if (strlen($strValue) > 1 && str_starts_with($strValue, '0')) {
          return DacemCsvForceQuotesFilter::FORCE_QUOTE_MARKER . $strValue . DacemCsvForceQuotesFilter::FORCE_QUOTE_MARKER;
        }

        return $value;
      }, $row);
    });

    // Insert the first row (The flat CSV Headers)
    $csv_writer->insertOne($headers);

    // Loop through entities and extract rows instantly using our O(1) blueprint instructions
    foreach ($entities as $entity) {
      $row_data = $this->fieldMappingService->exportEntityRow($entity, $blueprint);
      $csv_writer->insertOne($row_data);
    }

    // 6. Return a modern Symfony response to immediately trigger a browser download save prompt
    $response = new BinaryFileResponse($physical_path);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $entity_bundle . '_export_' . date('Y-m-d') . '.csv'
    );
    // Tell Drupal to delete the temporary file from disk after sending it to the user
    $response->deleteFileAfterSend(TRUE);

    return $response;
  }

}