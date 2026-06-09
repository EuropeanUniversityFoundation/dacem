<?php

namespace Drupal\euf_csv_import_export\CsvImporter;

use Drupal\Core\File\FileSystem;
use Drupal\euf_csv_import_export\AccessManager\UserAccessManager;
use Drupal\euf_csv_import_export\CsvConverter\CsvDecoder;
use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\euf_csv_import_export\CsvConverter\ReferenceResolver;
use Drupal\euf_csv_import_export\CsvImporter\CsvSorter;
use Drupal\euf_csv_import_export\CsvNormalizer\CsvNormalizer;
use Drupal\euf_csv_import_export\Dataloader\Dataloader;
use Drupal\euf_csv_import_export\EntityValidator\CourseValidator;
use Drupal\euf_csv_import_export\EntityValidator\OunitValidator;
use Drupal\euf_csv_import_export\EntityValidator\ProgrammeValidator;
use Drupal\euf_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\euf_csv_import_export\FileValidator\FileValidator;
use Drupal\file\Entity\File;
use League\Csv\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CsvImporter {

	protected FileSystem $fileSystem;
	protected FileValidator $fileValidator;
	protected UserAccessManager $userAccessManager;
	protected CsvSorter $csvSorter;
	protected CsvDecoder $csvDecoder;
	protected ReferenceResolver $referenceResolver;
	protected Dataloader $dataLoader;
	protected OunitValidator $ounitValidator;
	protected ProgrammeValidator $programmeValidator;
	protected CourseValidator $courseValidator;
	protected CsvNormalizer $csvNormalizer;

	public function __construct(
		FileSystem $file_system,
		FileValidator $file_validator,
		UserAccessManager $user_access_manager,
		CsvSorter $csv_sorter,
		CsvDecoder $csv_decoder,
		ReferenceResolver $reference_resolver,
		Dataloader $data_loader,
		OunitValidator $ounit_validator,
		ProgrammeValidator $programme_validator,
		CourseValidator $course_validator,
		CsvNormalizer $csv_normalizer,
	)	{
		$this->fileSystem = $file_system;
		$this->fileValidator = $file_validator;
		$this->userAccessManager = $user_access_manager;
		$this->csvSorter = $csv_sorter;
		$this->csvDecoder = $csv_decoder;
		$this->referenceResolver = $reference_resolver;
		$this->dataLoader = $data_loader;
		$this->ounitValidator = $ounit_validator;
		$this->programmeValidator = $programme_validator;
		$this->courseValidator = $course_validator;
		$this->csvNormalizer = $csv_normalizer;
	}

	public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
			$container->get('euf_csv_import_export.file_validator'),
			$container->get('euf_csv_import_export.user_access_manager'),
			$container->get('euf_csv_import_export.csv_sorter'),
			$container->get('euf_csv_import_export.csv_decoder'),
			$container->get('euf_csv_import_export.reference_resolver'),
			$container->get('euf_csv_import_export.data_loader'),
			$container->get('euf_csv_import_export.ounit_validator'),
			$container->get('euf_csv_import_export.programme_validator'),
			$container->get('euf_csv_import_export.course_validator'),
			$container->get('euf_csv_import_export.csv_normalizer'),
    );
  }

	public function import(File $file, string $entityType, ?bool $sort = FALSE) {
		$userInstitutions = $this->userAccessManager->loadCurrentUserInstitutions();

		$csvReader = $this->createReaderFromFile($file);
		$records = iterator_to_array($csvReader->getRecords());
    $headers = iterator_to_array($csvReader->getHeader());

		$errors = $this->fileValidator->validateFileData($records, $headers, $entityType, $userInstitutions);
		if (!empty($errors)) {
			return ['errors' => $errors];
		}

		// Entities having parents of the same type as themselves, have to be sorted before import.
		if ($sort) {
			$records = $this->csvSorter->sortData($records, $entityType);
			if (isset($results[CsvSorter::CYCLIC_ERROR_NAME])) {
				return ['errors' => $records];
			}
		} else {
			$records = $this->addRowNumbers($records);
		}

		$results = $this->csvDecoder->decodeMultiple($entityType, $records);

		$institution_schac_code = $records[array_key_first($records)][FieldMappingService::HEI_COLUMNS[$entityType]['hei_column_name']];
		$institution_in_file = $this->dataLoader->getInstitutionBySchacCode($institution_schac_code);
		$results = $this->referenceResolver->resolveReferencesMultiple($entityType, $results, $institution_in_file);

		if ($entityType === ImportTargetEntityType::OUNIT->value) {
			$violations = $this->ounitValidator->validateMultiple($entityType, $results);
		} else if ($entityType === ImportTargetEntityType::PROGRAMME->value){
			$violations = $this->programmeValidator->validateMultiple($entityType, $results);
		} else if ($entityType === ImportTargetEntityType::COURSE->value){
			$violations = $this->courseValidator->validateMultiple($entityType, $results, $institution_in_file);
		} else if ($entityType === ImportTargetEntityType::COURSE_INSTANCE->value){

		}

		if (!empty($violations)) {
			return ['errors' => $violations];
		}

		$entities = $this->csvNormalizer->upsertMultiple($entityType, $results, $institution_in_file);

		$results['errors'] = $errors;

		return($results);
	}

	protected function createReaderFromFile(File $file) {
		$path = $this->getFilePath($file);

		$csv_reader = Reader::from($path, 'r');
		$csv_reader->setHeaderOffset(0);

		return $csv_reader;
	}

	protected function getFilePath(File $file) {
		$file_uri = $file->getFileUri();
    $physical_path = $this->fileSystem->realpath($file_uri);

    if (!$physical_path || !file_exists($physical_path)) {
      throw new \Exception("The file could not be uploaded to the server or is not found.");
    }

		return $physical_path;
	}

	protected function addRowNumbers(array $records) {
		foreach ($records as $index => &$record) {
    	$record['csv_row'] = $index;
  	}

		return $records;
	}

}
