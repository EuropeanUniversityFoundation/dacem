<?php

namespace Drupal\dacem_csv_import_export\CsvImporter;

use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\Result\ImportError;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;

/**
 *
 */
class CsvSorter {

  public const CYCLIC_ERROR_NAME = 'Cyclic dependency found';

  protected array $errors = [];

  public function __construct(
    protected CsvHeaderParser $headerParser,
    protected BundleCsvDefinitions $definitions,
  ) {}

  public function sortData(array $records, string $entityType) {

    $definition = $this->definitions->get($entityType);
    if (!$definition->parentField) {
      return $records;
    }
    $sorted_collection = $this->topologicalSort($records, $definition->codeField, $definition->parentField);

    return $sorted_collection;
  }

  public function topologicalSort(array $records, string $code_key, string $parent_key) {
    $graph = [];
    $inDegree = [];
    $objectMap = [];
    $rowOffsets = [];

    foreach ($records as $row_no => $obj) {
      $code = $obj[$code_key];
      $objectMap[$code] = $obj;
      $rowOffsets[$code] = $row_no;

      if (!isset($graph[$code])) {
        $graph[$code] = [];
        $inDegree[$code] = 0;
      }

      foreach ($obj as $key => $value) {
        if ($this->headerParser->parse($key)->fieldName === $parent_key && !empty($value)) {
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

      $this->errors = [];
      foreach ($cycles as $cycle) {
        $this->errors[] = new ImportError(
          self::CYCLIC_ERROR_NAME,
          'Two or more entities reference each other in the CSV.',
          'Multiple', $parent_key, array_map('strval', $cycle),
        );
      }
      // Tarjan's larger components omit self-references, which are cycles too.
      if (!$this->errors) {
        $this->errors[] = new ImportError(self::CYCLIC_ERROR_NAME, 'A parent reference creates a dependency cycle.', 'Multiple', $parent_key);
      }

      return $this->errors;
    }

    // Build sorted list of objects.
    $sortedObjects = [];
    foreach ($sorted as $code) {
      if (isset($objectMap[$code])) {
        $sortedObjects[$rowOffsets[$code]] = $objectMap[$code];
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
