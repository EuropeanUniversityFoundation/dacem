## INTRODUCTION

The module imports and exports catalogue entities using CSV files.

### Entity lookup and row validation

`Repository/CatalogueEntityRepository` replaces `DataLoader`. Its
`findImportTarget()` method returns one entity or NULL using the bundle's code
field, bundle ID, and institution path. Courses use their programme's institution
through the same query used for programme and organizational-unit identities.
Preflight and saving therefore agree on which entity is being updated.

The repository also provides explicit institution lookup, configured reference
queries, and loading by IDs. Unique identity lookups reject multiple matches
instead of choosing the first. `ImportRowValidator` turns duplicate target codes
into form errors identifying the source row and code column; the importer also
reports duplicate institution identities.

Institution authorization is checked by the import's file/access validation.
Repository identity queries disable entity-query access checks consistently for
preflight and saving; they are internal operations scoped to the authorized
institution and target bundle, not standalone authorization checks.

`Import/ImportRowValidator` replaces `EntityValidatorBase`, `ProgrammeValidator`,
`OunitValidator`, and `CourseValidator`. One service uses bundle definitions for
preflight exclusions and error labels and the shared repository for lookup.
`CsvImporter` no longer selects a different validator for each bundle.

### Import results and user-facing errors

`CsvImporter::import()` always returns an `ImportResult`: a completed row count
on success, or a flat list of `ImportError` objects and zero committed rows on
failure. It no longer mixes decoded rows with grouped error arrays.

The file validator, dependency sorter, and entity validation use the same error
objects. `ValidationErrorMapper` maps Drupal field paths to the original CSV
header, including delta/property/language suffixes. Import rows retain their
source columns through conversion, reference resolution, and preflight.
Entity-level constraints without a field address appear as whole-row errors.

`EntityImportWriter` rolls back before throwing `ImportWriteException`, which
retains the failing row and stage language. The importer converts save-time
validation failures to detailed CSV errors. Other save failures produce a clear
row-level message for the user; diagnostic details go to the module's logger.
Empty files, missing headers, malformed suffixes, duplicate codes, invalid
references, and dependency cycles also return structured errors.

The form displays row, error type, message, CSV column, language, and values.
Error messages allow a small set of formatting tags through Drupal's HTML filtering, so validation placeholders display correctly. CSV values, column names, and other source context use plain-text rendering.
Failures show no success notice; successful imports show the completed row
count rather than an "Import started" message. Uploaded files are cleaned up
after either result.

### Typed import rows

`Import/ImportRow` carries the target bundle, original one-based CSV record
number (including the header), and field changes grouped by language. The
decoder returns these rows without inserting `type`, `langcode`, or `csv_row`
into field data. Empty cells still clear values; absent columns remain absent.
CSV headers cannot override operation metadata or protected Drupal fields.

`CsvDecoder::decodeMultiple()` accepts records keyed by their original
zero-based CSV offsets, as returned by League CSV. The sorter retains those
keys when ordering dependencies. Validation reads `ImportRow::rowNumber`, so
errors point to the original record even after sorting. Standalone `decode()`
accepts an explicit row number and defaults to 2 (the first data record).

Reference resolution returns new rows with updated fields and unchanged
metadata. Preflight uses `withoutFields()` to omit deferred references without
altering the row later used by the writer. Service methods consume typed rows
rather than accepting separate bundle and nested data arguments.

### Field value conversion

`CsvDecoder` maps cells to field replacements, then delegates conversion in the
existing order: coordinates first, formatted text second. Both services accept
an `ImportRow` and return updated rows while preserving source metadata.

- `Import/Conversion/CoordinatesConverter` reads the bundle's coordinate mapping.
  Complete pairs become WKT points in longitude/latitude order. Zero coordinates
  are valid, two empty cells clear the field, and partial or absent coordinates
  remain unchanged for downstream handling.
- `Import/Conversion/FormattedTextConverter` discovers Drupal's `text`,
  `text_long`, and `text_with_summary` fields. It preserves the existing
  `nl2br()` conversion and assigns `basic_html`, including for explicit empty
  strings. Plain fields and missing columns remain unchanged.

`FieldMappingService` no longer discovers import text fields, and the decoder
contains no text-formatting or WKT construction logic. Reference resolution
remains its own separate step because some lookups must wait for earlier rows
to be saved.

### Shared column schema

`Schema/CsvHeaderParser` reads and builds headers in the existing
`field[____delta][___property][|language]` format. It returns immutable `CsvColumn`
addresses used by the decoder, file validator, sorter, and export compiler.
Malformed suffixes are reported as file validation errors before import.
The decoder reuses parsed columns across rows while retaining missing-cell and
empty-cell semantics.

`Schema/CsvSchemaCompiler` expands bundle layouts and field metadata into a
`CsvExportSchema`. Each `CsvExportColumn` pairs a shared column address with its
Drupal value property and reference flag. A property's extraction name is kept
separate from its optional header suffix: a single-property reference still
exports its code without adding `___target_id` to the header. Headers and values
now follow one ordered schema rather than parallel header and blueprint arrays.

`CsvConverter/FieldMappingService` retains field metadata collection and export
value extraction. Header parsing and schema compilation are separate services.

### Bundle CSV definitions

`Definition/BundleCsvDefinitions` provides one immutable `BundleCsvDefinition`
per bundle. Definitions own the CSV identity and institution columns, required
shared and translated columns, export order, reference rules, dependency parent,
preflight exclusions, and coordinate mapping. Import, export, sorting, and
validation services all receive this provider through dependency injection.

Add or change a bundle's CSV rules in its definition rather than in the
consumer services. Drupal field definitions still determine cardinality and
translatability. Unknown bundles retain export's field-order fallback.

Reference resolution and file reference validation have separate rule lists:
resolving term labels and checking node codes currently have different scopes.
Moving the rules has preserved those scopes. Only organizational units,
programmes, and courses are marked importable; an institution definition supplies
reference codes, and legacy course-instance metadata does not enable importing
course instances.

### Staged entity updates

Import updates shared fields and original-language fields first, then updates
the other translations separately. Each stage is validated before saving, and
the entity is reloaded between stages. Batch writes use a database transaction;
an exception rolls back the batch.

- `Import/EntityImportPlanner` routes decoded fields into ordered stages without
  modifying the entity. It uses the entity's actual original language.
- `Import/ImportStage` holds one language and its field replacements. An absent
  field is preserved; a present empty value clears it; `"0"` remains a value.
- `Import/EntityImportApplier` applies one stage in memory without saving.
- `Import/EntityImportValidator` simulates stages on cloned entities. It models
  the baseline that a reload supplies between saved stages, without writing.
  Its stage validation is also used immediately before persistence.
- `Import/EntityImportWriter` resolves deferred references, finds or creates
  entities, validates and saves stages, reloads between stages, and assigns
  institution group membership. It owns the batch transaction and rollback.
  Rows must already be sorted and have regular references resolved.
- Callers use `EntityImportValidator` and `EntityImportWriter` directly; the
  transitional `CsvNormalizer` wrapper and service have been removed.
  `ImportWriterInterface` defines the persistence boundary for the importer,
  including source-aware failures after rollback.

These stages preserve Drupal's restrictions on editing shared fields through
translation forms. They do not change the bundle's translation configuration.

### Automated tests

Install the development dependencies with `ddev composer install`, then run:

```sh
ddev exec composer test:csv
```

The command runs unit and kernel tests with PHPUnit. Unit tests exercise header
parsing and schema expansion without Drupal. Each kernel test uses an in-memory
SQLite database, boots a minimal Drupal kernel, and creates its own languages,
content types, fields,
text format, user, institution, organizational unit, and programme. No existing
site records or exported site configuration are required. Drupal discards the
temporary database and test configuration after each test.

`tests/src/Unit/` covers header suffix combinations, malformed headers, language
grouping, schema expansion, row metadata, and the extracted text and coordinate
converters.

`tests/src/Kernel/StagedEntityImportTest.php` exercises the real decoder,
reference resolver, planner, applier, validator, and writer services.
It covers:

- Clearing shared dates and references while updating Spanish and Portuguese
  outcomes, with the translation editing restriction enabled.
- Distinguishing empty cells, missing columns, and zero values.
- Preflight validation without changing cached or stored entities.
- Required translated fields and source-aware preflight errors.
- Rolling back earlier rows and stages when a later translation is invalid.
- Rolling back entity writes when institution group assignment throws.
- Planning against an original language other than English.
- Creating a new programme and translation.
- Multi-value link export/decode mapping, including empty translated items.
- Virtual course-to-institution reference exports and malformed-header errors.
- Original record numbers after sorting, immutable reference resolution, and
  deferred parent resolution after creating a parent earlier in the batch.
- A real CSV file import through the importer and early dependency-cycle errors.
- Save-time validation and storage failures with rollback, source headers, and
  zero committed-row reporting.
- Form success/error messages and plain-text rendering of CSV-supplied values.
- Course identity lookup across programme/institution references, with matching
  codes in other institutions and bundles left untouched.
- Missing targets, duplicate target codes, and duplicate institution identities.

Group assignment remains mocked in the focused staged-import tests.
`tests/src/Kernel/GroupImportIntegrationTest.php` separately uses real HEI
profiles, memberships, and Group node relationships. It checks assignment on
create/update, duplicate prevention, membership-based institution discovery,
non-member exclusion, and transactional rollback of relationships.

The browser suite runs separately because it needs DDEV's HTTP server and a
SQLite file shared with web requests:

```sh
ddev exec composer test:csv:browser
```

It installs an isolated test site and uses the actual managed-file upload form.
A successful upload creates a programme and a real Group relationship; a bad
header produces an error table and no success notice. It uses
`/tmp/dacem-csv-browser.sqlite` and writes debugging pages under
`/tmp/dacem-csv-browser-output`; the installed site's records are not used.
This functional browser test uses BrowserKit and does not require Chrome.

`tests/bootstrap.php` preserves Drupal's normal deprecation rules and adds the
two exact upstream EWP/Group notices listed in `tests/deprecation-ignore.txt`.
Other deprecations still fail tests, and an explicitly supplied
`SYMFONY_DEPRECATIONS_HELPER` policy takes precedence. Remove those allowances
when the dependencies update their field-category and membership APIs.

The old `tests/staged-import-check.php` script was replaced by this suite. It
required specific records and a privileged user in the local site database;
the kernel tests create explicit fixtures and can run on a fresh checkout or CI.

## REQUIREMENTS

DESCRIBE_MODULE_DEPENDENCIES_HERE

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## CONFIGURATION
- Configuration step #1
- Configuration step #2
- Configuration step #3

## MAINTAINERS

Current maintainers for Drupal 10:

- FIRST_NAME LAST_NAME (NICKNAME) - https://www.drupal.org/u/NICKNAME

