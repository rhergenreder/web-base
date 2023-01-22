<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Expression\Alias;
use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;

/**
 * this class is similar to \Driver\SQL\Query\Select but with reduced functionality
 * and more adapted to entities.
*/
class DatabaseEntityQuery extends Select {

  const FETCH_NONE = 0;
  const FETCH_DIRECT = 1;
  const FETCH_RECURSIVE = 2;

  private Logger $logger;
  private DatabaseEntityHandler $handler;
  private int $resultType;
  private bool $logVerbose;
  private array $additionalColumns;

  private int $fetchSubEntities;
  private ?DatabaseEntityQueryContext $context;

  private function __construct(DatabaseEntityHandler $handler, int $resultType) {
    parent::__construct($handler->getSQL(), ...$handler->getColumnNames());
    $this->handler = $handler;
    $this->logger = new Logger("DB-EntityQuery", $handler->getSQL());
    $this->resultType = $resultType;
    $this->logVerbose = false;
    $this->context = null;
    $this->additionalColumns = [];

    $this->from($handler->getTableName());
    $this->fetchSubEntities = self::FETCH_NONE;
    if ($this->resultType === SQL::FETCH_ONE) {
      $this->first();
    }
  }

  public function only(array $fields): DatabaseEntityQuery {
    if (!in_array("id", $fields)) {
      $fields[] = "id";
    }

    $this->select(array_map(function ($field) {
      return $this->handler->getColumnName($field);
    }, $fields));
    return $this;
  }

  public function withContext(DatabaseEntityQueryContext $context): self {
    $this->context = $context;
    return $this;
  }

  public function addCustomValue(mixed $selectValue): DatabaseEntityQuery {
    if (is_string($selectValue)) {
      $this->additionalColumns[] = $selectValue;
    } else if ($selectValue instanceof Alias) {
      $this->additionalColumns[] = $selectValue->getAlias();
    } else if ($selectValue instanceof Column) {
      $this->additionalColumns[] = $selectValue->getName();
    } else {
      $this->logger->debug("Cannot get selected column name from custom value of type: " . get_class($selectValue));
      return $this;
    }

    $this->addSelectValue($selectValue);
    return $this;
  }

  public function getHandler(): DatabaseEntityHandler {
    return $this->handler;
  }

  public function debug(): DatabaseEntityQuery {
    $this->logVerbose = true;
    return $this;
  }

  public static function fetchAll(DatabaseEntityHandler $handler): DatabaseEntityQuery {
    return new DatabaseEntityQuery($handler, SQL::FETCH_ALL);
  }

  public static function fetchOne(DatabaseEntityHandler $handler): DatabaseEntityQuery {
    return new DatabaseEntityQuery($handler, SQL::FETCH_ONE);
  }

  // TODO: clean this up
  public function fetchEntities(bool $recursive = false): DatabaseEntityQuery {

    $this->fetchSubEntities = ($recursive ? self::FETCH_RECURSIVE : self::FETCH_DIRECT);
    $visited = [$this->handler->getTableName()];
    foreach ($this->handler->getRelations() as $propertyName => $relationHandler) {
      $this->fetchRelation($propertyName, $this->handler->getTableName(), $this->handler, $relationHandler,
        $recursive, "", $visited);
    }

    return $this;
  }

  private function fetchRelation(string $propertyName, string $tableName, DatabaseEntityHandler $src, DatabaseEntityHandler $relationHandler,
                                 bool $recursive = false, string $relationColumnPrefix = "", array &$visited = []) {

    $relIndex = count($visited);
    if (in_array($relationHandler->getTableName(), $visited)) {
      return;
    } else {
      $visited[] = $relationHandler->getTableName();
    }

    $columns = $src->getColumns();

    $foreignColumn = $columns[$propertyName];
    $foreignColumnName = $foreignColumn->getName();
    $referencedTable = $relationHandler->getTableName();
    $isNullable = !$foreignColumn->notNull();
    $alias = "t$relIndex"; // t1, t2, t3, ...

    if ($isNullable) {
      $this->leftJoin($referencedTable, "$tableName.$foreignColumnName", "$alias.id", $alias);
    } else {
      $this->innerJoin($referencedTable, "$tableName.$foreignColumnName", "$alias.id", $alias);
    }

    $relationColumnPrefix .= DatabaseEntityHandler::buildColumnName($propertyName) . "_";
    $recursiveRelations = $relationHandler->getRelations();
    foreach ($relationHandler->getColumns() as $relPropertyName => $relColumn) {
      $relColumnName = $relColumn->getName();
      if (!isset($recursiveRelations[$relPropertyName]) || $recursive) {
        $this->addValue("$alias.$relColumnName as $relationColumnPrefix$relColumnName");
        if (isset($recursiveRelations[$relPropertyName]) && $recursive) {
          $this->fetchRelation($relPropertyName, $alias, $relationHandler, $recursiveRelations[$relPropertyName],
            $recursive, $relationColumnPrefix, $visited);
        }
      }
    }
  }

  public function execute(): DatabaseEntity|array|null|false {

    if ($this->logVerbose) {
      $params = [];
      $query = $this->build($params);
      $this->logger->debug("QUERY: $query\nARGS: " . print_r($params, true));
    }

    $res = parent::execute();
    if ($res === null || $res === false) {
      return $res;
    }

    if ($this->resultType === SQL::FETCH_ALL) {
      $entities = [];
      $entitiesNM = [];

      foreach ($res as $row) {

        $cached = false;
        $entity = null;

        if ($this->context) {
          $entity = $this->context->queryCache($this->handler, $row["id"]);
          $cached = $entity !== null;
        }

        if (!$cached) {
          $entity = $this->handler->entityFromRow($row, $this->additionalColumns, $this->fetchSubEntities, $this->context);
          $this->context?->addCache($this->handler, $entity);
          $entitiesNM[$entity->getId()] = $entity;
        }

        if ($entity) {
          $entities[$entity->getId()] = $entity;
        }
      }

      if (!empty($entitiesNM) && $this->fetchSubEntities !== self::FETCH_NONE) {
        $this->handler->fetchNMRelations($entitiesNM, $this->fetchSubEntities, $this->context);
      }

      return $entities;
    } else if ($this->resultType === SQL::FETCH_ONE) {

      $cached = false;
      $entity = null;

      if ($this->context) {
        $entity = $this->context->queryCache($this->handler, $res["id"]);
        $cached = $entity !== null;
      }

      if (!$cached) {
        $entity = $this->handler->entityFromRow($res, $this->additionalColumns, $this->fetchSubEntities, $this->context);
        if ($entity instanceof DatabaseEntity && $this->fetchSubEntities !== self::FETCH_NONE) {
          $this->handler->fetchNMRelations([$entity->getId() => $entity], $this->fetchSubEntities, $this->context);
        }

        $this->context?->addCache($this->handler, $entity);
      }

      return $entity;
    } else {
      $this->handler->getLogger()->error("Invalid result type for query builder, must be FETCH_ALL or FETCH_ONE");
      return null;
    }
  }

  public function executeSQL() {
    return parent::execute();
  }
}