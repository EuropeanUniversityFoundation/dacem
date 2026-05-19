<?php

namespace Drupal\euf_csv_import_export\CsvImporter;

use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\euf_csv_import_export\Enum\ImportTargetEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class CsvSorter {

  public const CYCLIC_ERROR_NAME = 'Cyclic dependency found';

  public const SORTING_BY_TYPE = [
    ImportTargetEntityType::OUNIT->value => [
      'code_key' => 'field_ou_code',
      'parent_key' => 'field_parent_organizational_unit'
    ],
  ];

  protected array $errors = [];

  protected FieldMappingService $fieldMappingService;

  public function __construct(
    FieldMappingService $field_mapping_service
  ) {
    $this->fieldMappingService = $field_mapping_service;
  }

  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('euf_csv_import_export.field_mapping_service'),
    );
  }

  /**
   *
   */
  public function sortData(array $records, string $entityType) {

    $sorted_collection = $this->topologicalSort($records, self::SORTING_BY_TYPE[$entityType]['code_key'], self::SORTING_BY_TYPE[$entityType]['parent_key']);

    return $sorted_collection;
  }

  public function topologicalSort(array $records, string $code_key, string $parent_key) {
    $graph = [];
    $inDegree = [];
    $objectMap = [];

    foreach ($records as $row_no => $obj) {
      $code = $obj[$code_key];
      $objectMap[$code] = $obj;
      $objectMap[$code]['csv_row'] = $row_no;

      if (!isset($graph[$code])) {
        $graph[$code] = [];
        $inDegree[$code] = 0;
      }

      foreach ($obj as $key => $value) {
        if ($this->fieldMappingService->resolveFieldFromColumn($key) === $parent_key && !empty($value)) {
          $parent = $value;

          if (!isset($graph[$parent])) {
            $graph[$parent] = [];
            $inDegree[$parent] = 0;
          }

          $graph[$parent][] = $code;
          if (!isset($inDegree[$code])) {
            $inDegree[$code] = 0;
          }
          $inDegree[$code]++;
        }
      }
    }

    // Kahn’s Algorithm
    $queue = [];
    foreach ($inDegree as $node => $degree) {
      if ($degree === 0) {
        $queue[] = $node;
      }
    }

    $sorted = [];
    while (!empty($queue)) {
      $current = array_shift($queue);
      $sorted[] = $current;

      foreach ($graph[$current] as $neighbor) {
        $inDegree[$neighbor]--;
        if ($inDegree[$neighbor] === 0) {
          $queue[] = $neighbor;
        }
      }
    }

    // If cycle exists - find SCCs
    if (count($sorted) !== count($graph)) {
      $cycles = $this->findCycles($graph);

      foreach ($cycles as $cycle) {
        $this->errors[self::CYCLIC_ERROR_NAME][] = [
          'message' => 'Two or more entities reference each other in the CSV.',
          'source' => $parent_key,
          'values' => $cycle,
          'row_number' => 'Multiple',
        ];
      }

      return $this->errors;
    }

    // Build sorted list of objects.
    $sortedObjects = [];
    foreach ($sorted as $code) {
      if (isset($objectMap[$code])) {
        $sortedObjects[] = $objectMap[$code];
      }
    }

    return $sortedObjects;
  }

  /**
   * Tarjan’s SCC algorithm to detect cycles.
   */
  private function findCycles(array $graph): array {
    $index = 0;
    $stack = [];
    $onStack = [];
    $indices = [];
    $lowlink = [];
    $sccs = [];

    $strongConnect = function($v) use (&$graph, &$index, &$stack, &$onStack, &$indices, &$lowlink, &$sccs, &$strongConnect) {
      $indices[$v] = $index;
      $lowlink[$v] = $index;
      $index++;
      array_push($stack, $v);
      $onStack[$v] = true;

      foreach ($graph[$v] as $w) {
        if (!isset($indices[$w])) {
          $strongConnect($w);
          $lowlink[$v] = min($lowlink[$v], $lowlink[$w]);
        } elseif ($onStack[$w]) {
          $lowlink[$v] = min($lowlink[$v], $indices[$w]);
        }
      }

      if ($lowlink[$v] === $indices[$v]) {
        $scc = [];
        do {
          $w = array_pop($stack);
          $onStack[$w] = false;
          $scc[] = $w;
        } while ($w !== $v);

        if (count($scc) > 1) {
          $sccs[] = $scc; // Only return real cycles (size > 1)
        }
      }
    };

    foreach (array_keys($graph) as $v) {
      if (!isset($indices[$v])) {
        $strongConnect($v);
      }
    }

    return $sccs;
  }

}
