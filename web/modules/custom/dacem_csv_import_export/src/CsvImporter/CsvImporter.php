<?php

namespace Drupal\dacem_csv_import_export\CsvImporter;

use Drupal\Core\File\FileSystem;
use Drupal\dacem_csv_import_export\AccessManager\UserAccessManager;
use Drupal\dacem_csv_import_export\CsvConverter\CsvDecoder;
use Drupal\dacem_csv_import_export\CsvConverter\ReferenceResolver;
use Drupal\dacem_csv_import_export\CsvImporter\CsvSorter;
use Drupal\dacem_csv_import_export\Import\ImportWriterInterface;
use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\FileValidator\FileValidator;
use Drupal\dacem_csv_import_export\Import\ImportRowValidator;
use Drupal\dacem_csv_import_export\Import\ImportWriteException;
use Drupal\dacem_csv_import_export\Import\Result\ImportError;
use Drupal\dacem_csv_import_export\Import\Result\ImportResult;
use Drupal\dacem_csv_import_export\Import\Result\ValidationErrorMapper;
use Drupal\dacem_csv_import_export\Repository\AmbiguousEntityException;
use Drupal\dacem_csv_import_export\Repository\CatalogueEntityRepository;
use Drupal\file\Entity\File;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class CsvImporter {

  public function __construct(
    protected FileSystem $fileSystem,
    protected FileValidator $fileValidator,
    protected UserAccessManager $userAccessManager,
    protected CsvSorter $csvSorter,
    protected CsvDecoder $csvDecoder,
    protected ReferenceResolver $referenceResolver,
    protected CatalogueEntityRepository $repository,
    protected ImportRowValidator $rowValidator,
    protected ImportWriterInterface $writer,
    protected BundleCsvDefinitions $definitions,
    protected ValidationErrorMapper $errorMapper,
    protected LoggerInterface $logger,
  ) {}

  public function import(File $file, string $entityType, ?bool $sort = FALSE): ImportResult {
    try {
      return $this->importFile($file, $entityType, (bool) $sort);
    }
    catch (ImportWriteException $exception) {
      $cause = $exception->getPrevious();
      if ($cause instanceof AmbiguousEntityException) {
        $definition = $this->definitions->get($exception->row->bundle);
        return ImportResult::failure([new ImportError(
          'Ambiguous identity', $cause->getMessage(), $exception->row->rowNumber,
          $definition->codeField, [(string) $exception->row->fieldValue($definition->codeField)],
        )]);
      }
      if ($cause instanceof ValidationFailedException) {
        return ImportResult::failure($this->errorMapper->map(
          $exception->row, $cause->getViolations(), 'Validation failed while saving', $exception->language,
        ));
      }
      $this->logger->error('CSV import failed at row @row: @message', [
        '@row' => $exception->row->rowNumber, '@message' => $cause->getMessage(), 'exception' => $cause,
      ]);
      return ImportResult::failure([new ImportError(
        'Save failed',
        'This row could not be saved. No changes were saved. Please contact the site administrator.',
        $exception->row->rowNumber,
      )]);
    }
    catch (AmbiguousEntityException $exception) {
      return ImportResult::failure([new ImportError('Ambiguous identity', $exception->getMessage(), 'File')]);
    }
    catch (CsvException | \InvalidArgumentException $exception) {
      return ImportResult::failure([new ImportError('Invalid CSV', $exception->getMessage(), 'File')]);
    }
    catch (\Throwable $exception) {
      $this->logger->error('CSV import failed: @message', ['@message' => $exception->getMessage(), 'exception' => $exception]);
      return ImportResult::failure([new ImportError(
        'Import failed', 'The CSV could not be imported. No changes were saved. Please contact the site administrator.', 'File',
      )]);
    }
  }

  protected function importFile(File $file, string $entityType, bool $sort): ImportResult {
    $userInstitutions = $this->userAccessManager->loadCurrentUserInstitutions();
    $reader = $this->createReaderFromFile($file);
    $records = iterator_to_array($reader->getRecords());
    $headers = $reader->getHeader();
    $errors = $this->fileValidator->validateFileData($records, $headers, $entityType, $userInstitutions);
    if ($errors) {
      return ImportResult::failure($errors);
    }
    if ($sort) {
      $records = $this->csvSorter->sortData($records, $entityType);
      if ($records && reset($records) instanceof ImportError) {
        return ImportResult::failure($records);
      }
    }
    $rows = $this->csvDecoder->decodeMultiple($entityType, $records);
    $institution_code = $records[array_key_first($records)][$this->definitions->get($entityType)->institutionColumn];
    $institution = $this->repository->findInstitutionBySchacCode($institution_code);
    if (!$institution) {
      return ImportResult::failure([new ImportError('Invalid institution', 'The institution code does not identify an institution.', 'File', $this->definitions->get($entityType)->institutionColumn, [$institution_code])]);
    }
    $rows = $this->referenceResolver->resolveReferencesMultiple($rows, $institution);
    $errors = $this->rowValidator->validateMultiple($rows, $institution);
    if ($errors) {
      return ImportResult::failure($errors);
    }
    $this->writer->writeMultiple($rows, $institution);
    return ImportResult::success(count($rows));
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
