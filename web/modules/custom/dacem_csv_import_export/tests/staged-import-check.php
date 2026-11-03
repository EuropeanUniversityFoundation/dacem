<?php

/**
 * Local integration regression: drush php:script <path-to-this-file>.
 * Requires the programme sss fixture at uvigo.es; all writes are rolled back.
 */

// Match an authorized import user, including access to configured text formats.
\Drupal::service('account_switcher')->switchTo(\Drupal\user\Entity\User::load(1));

$storage = \Drupal::entityTypeManager()->getStorage('node');
$loader = \Drupal::service('dacem_csv_import_export.data_loader');
$normalizer = \Drupal::service('dacem_csv_import_export.csv_normalizer');
$institution = $loader->getInstitutionBySchacCode('uvigo.es');
$nodes = $loader->getEntityByCodeAndInstitution('programme', 'field_programme_code', 'sss', 'field_programme_institution', $institution);
$node = reset($nodes);
if (!$node) { throw new \RuntimeException('Missing local fixture programme sss.'); }
$before = $node->toArray();
$data = [
  'en' => [
    'field_programme_code' => 'sss',
    'field_programme_institution' => $institution,
    'field_programme_start_date' => '',
    'field_programme_end_date' => '',
    'field_programme_ou' => [],
  ],
  'es' => ['field_programme_learn_outcomes' => ['value' => '<p>staged import regression</p>', 'format' => 'basic_html']],
  'pt-pt' => ['field_programme_learn_outcomes' => ['value' => '<p>staged import regression</p>', 'format' => 'basic_html']],
];
$check = static function ($condition, $message) {
  if (!$condition) { throw new \RuntimeException($message); }
};
$spanish_original = $storage->create(['type' => 'programme', 'langcode' => 'es']);
$stages = $normalizer->buildImportStages($spanish_original, $data);
$check(array_key_first($stages) === 'es', 'Original-language stage must run first.');
$check(array_key_exists('field_programme_start_date', $stages['es']), 'Shared fields must use the actual original language.');
$check(!array_key_exists('field_programme_start_date', ($stages['en'] ?? [])), 'Shared fields leaked into another translation.');
$violations = $normalizer->validateImport('programme', $data, $node);
$check($violations->count() === 0, (string) $violations);
$check($node->toArray() === $before, 'Preflight mutated the cached entity.');
$invalid = $data;
$invalid['en']['title'] = '';
$check($normalizer->validateImport('programme', $invalid, $node)->count() > 0, 'Required field validation was bypassed.');
$transaction = \Drupal::database()->startTransaction();
try {
  $batch = [$data];
  $normalizer->upsertMultiple('programme', $batch, $institution);
  $saved = $storage->loadUnchanged($node->id());
  $check($saved->get('field_programme_start_date')->isEmpty(), 'Start date not cleared.');
  $check($saved->get('field_programme_end_date')->isEmpty(), 'End date not cleared.');
  $check($saved->get('field_programme_ou')->isEmpty(), 'OU not cleared.');
  foreach (['es', 'pt-pt'] as $code) {
    $check($saved->getTranslation($code)->get('field_programme_learn_outcomes')->value === '<p>staged import regression</p>', 'Translation not saved.');
  }
  $check($saved->get('field_credits')->getValue() === $node->get('field_credits')->getValue(), 'Missing column changed.');
  $update = $data;
  $update['en']['field_programme_start_date'] = '2030-01-01';
  $batch = [$update, $invalid];
  try {
    $normalizer->upsertMultiple('programme', $batch, $institution);
    throw new \RuntimeException('Invalid batch unexpectedly saved.');
  }
  catch (\Symfony\Component\Validator\Exception\ValidationFailedException $expected) {
    $check($storage->loadUnchanged($node->id())->get('field_programme_start_date')->isEmpty(), 'Earlier row was not rolled back.');
  }
  echo "Staged validation, saves, missing columns, required fields, and batch rollback passed.\n";
}
finally {
  $transaction->rollBack();
  $storage->resetCache();
}
$check($storage->loadUnchanged($node->id())->toArray() === $before, 'Fixture was not restored.');
\Drupal::service('account_switcher')->switchBack();
