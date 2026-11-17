<?php

declare(strict_types=1);

namespace Drupal\Tests\dacem_csv_import_export\Kernel;

use Drupal\dacem_csv_import_export\Import\ImportRow;
use Drupal\dacem_csv_import_export\Import\ImportWriteException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;

/**
 * Exercises real memberships and group relationships without mocked assignment.
 *
 * @group dacem_csv_import_export
 */
class GroupImportIntegrationTest extends GroupKernelTestBase {

  protected static $modules = ['node', 'gnode', 'views', 'ewp_core', 'ewp_institutions', 'dacem_csv_import_export'];

  protected GroupInterface $group;
  protected Node $institution;

  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('hei');
    $this->installConfig(['system', 'user', 'node', 'filter']);
    $this->setCurrentUser($this->createUser(['access content', 'bypass node access']));
    foreach (['institution', 'programme'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
    }
    $this->addField('node', 'institution', 'field_shac_code', 'string');
    $this->addField('node', 'programme', 'field_programme_code', 'string');
    $this->addField('node', 'programme', 'field_programme_institution', 'entity_reference', ['target_type' => 'node']);
    $this->addField('hei', 'hei', 'hei_id', 'ascii_identifier');
    $hei = $this->entityTypeManager->getStorage('hei')->create(['label' => 'Test HEI', 'hei_id' => 'group-test.example']);
    $hei->save();
    $this->institution = Node::create(['type' => 'institution', 'title' => 'Institution', 'field_shac_code' => 'group-test.example']);
    $this->institution->save();
    $type = $this->createGroupType(['id' => 'institutions', 'creator_membership' => FALSE]);
    $this->addField('group', $type->id(), 'field_institution_profile', 'entity_reference', ['target_type' => 'hei']);
    $this->entityTypeManager->getStorage('group_relationship_type')
      ->createFromPlugin($type, 'group_node:programme')->save();
    $this->group = $this->createGroup(['type' => $type->id(), 'field_institution_profile' => $hei->id()]);
    $this->group->addMember($this->getCurrentUser());
  }

  private function addField(string $entity_type, string $bundle, string $name, string $type, array $settings = []): void {
    FieldStorageConfig::create(['entity_type' => $entity_type, 'field_name' => $name, 'type' => $type, 'settings' => $settings])->save();
    FieldConfig::create(['entity_type' => $entity_type, 'bundle' => $bundle, 'field_name' => $name, 'label' => $name])->save();
  }

  private function row(string $code, string $title): ImportRow {
    return new ImportRow('programme', 2, ['en' => [
      'field_programme_code' => $code, 'field_programme_institution' => $this->institution,
      'title' => $title,
    ]]);
  }

  public function testRealMembershipAndAssignmentAreIdempotent(): void {
    $access = $this->container->get('dacem_csv_import_export.user_access_manager');
    $institutions = $access->loadCurrentUserInstitutions();
    $this->assertSame([$this->institution->id()], array_map('strval', array_keys($institutions)));
    $writer = $this->container->get('dacem_csv_import_export.entity_import_writer');
    $writer->writeMultiple([$this->row('programme-1', 'First')], $this->institution);
    $writer->writeMultiple([$this->row('programme-1', 'Updated')], $this->institution);
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'programme', 'field_programme_code' => 'programme-1']);
    $this->assertCount(1, $nodes);
    $node = reset($nodes);
    $this->assertSame('Updated', $node->label());
    $this->assertCount(1, $this->group->getRelationshipsByEntity($node, 'group_node:programme'));
    $this->setCurrentUser($this->createUser(['access content']));
    $this->assertSame([], $access->loadCurrentUserInstitutions());
  }

  public function testRollbackIncludesRealGroupRelationships(): void {
    $writer = $this->container->get('dacem_csv_import_export.entity_import_writer');
    try {
      $writer->writeMultiple([$this->row('good', 'First'), $this->row('invalid', '')], $this->institution);
      $this->fail('The invalid second row should roll back the batch.');
    }
    catch (ImportWriteException) {
      $this->assertSame([], $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'programme']));
      $this->assertCount(0, $this->group->getRelationships('group_node:programme'));
    }
  }

}
