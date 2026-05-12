<?php

namespace Drupal\euf_csv_import_export\CsvImporter;

use Drupal\Core\File\FileSystem;
use Drupal\euf_csv_import_export\FileValidator\FileValidator;
use Drupal\euf_csv_import_export\AccessManager\UserAccessManager;
use Drupal\file\Entity\File;
use League\Csv\Reader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CsvImporter {

	protected FileSystem $fileSystem;
	protected FileValidator $fileValidator;
	protected UserAccessManager $userAccessManager;

	public function __construct(
		FileSystem $file_system,
		FileValidator $file_validator,
		UserAccessManager $user_access_manager,
	)	{
		$this->fileSystem = $file_system;
		$this->fileValidator = $file_validator;
		$this->userAccessManager = $user_access_manager;
	}

	public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
			$container->get('euf_csv_import_export.file_validator'),
			$container->get('euf_csv_import_export.user_access_manager'),
    );
  }

	public function import(File $file, string $entityType) {
		$userInstitutions = $this->userAccessManager->loadCurrentUserInstitutions();

		$csvReader = $this->createReaderFromFile($file);
		$errors = $this->fileValidator->validateFileData($csvReader, $entityType, $userInstitutions);

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

}
