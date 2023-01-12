<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Expression\Alias;
use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;
use Core\External\PHPMailer\Exception;

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

  private function __construct(DatabaseEntityHandler $handler, int $resultType) {
    parent::__construct($handler->getSQL(), ...$handler->getColumnNames());
    $this->handler = $handler;
    $this->logger = new Logger("DB-EntityQuery", $handler->getSQL());
    $this->resultType = $resultType;
    $this->logVerbose = false;
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

    // $this->selectQuery->dump();
    $this->fetchSubEntities = ($recursive ? self::FETCH_RECURSIVE : self::FETCH_DIRECT);

    $relIndex = 1;
    foreach ($this->handler->getRelations() as $propertyName => $relationHandler) {
      $this->fetchRelation($propertyName, $this->handler->getTableName(), $this->handler, $relationHandler, $relIndex, $recursive);
    }

    return $this;
  }

  private function fetchRelation(string $propertyName, string $tableName, DatabaseEntityHandler $src, DatabaseEntityHandler $relationHandler,
                                 int &$relIndex = 1, bool $recursive = false, string $relationColumnPrefix = "") {

    // TODO: fix recursion here...
    if ($src === $relationHandler && $recursive) {
      return;
    }

    $columns = $src->getColumns();

    $foreignColumn = $columns[$propertyName];
    $foreignColumnName = $foreignColumn->getName();
    $referencedTable = $relationHandler->getTableName();
    $isNullable = !$foreignColumn->notNull();
    $alias = "t$relIndex"; // t1, t2, t3, ...
    $relIndex++;


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
          $this->fetchRelation($relPropertyName, $alias, $relationHandler, $recursiveRelations[$relPropertyName], $relIndex, $recursive, $relationColumnPrefix);
        }
      }
    }
  }

  public function execute(): DatabaseEntity|array|null {

    if ($this->logVerbose) {
      $params = [];
      $query = $this->build($params);
      $this->logger->debug("QUERY: $query\nARGS: " . print_r($params, true));
    }

    $res = parent::execute();
    if ($res === null || $res === false) {
      return null;
    }

    if ($this->resultType === SQL::FETCH_ALL) {
      $entities = [];
      foreach ($res as $row) {
        $entity = $this->handler->entityFromRow($row, $this->additionalColumns, $this->fetchSubEntities !== self::FETCH_NONE);
        if ($entity) {
          $entities[$entity->getId()] = $entity;
        }
      }

      if ($this->fetchSubEntities !== self::FETCH_NONE) {
        $this->handler->fetchNMRelations($entities, $this->fetchSubEntities === self::FETCH_RECURSIVE);
      }

      return $entities;
    } else if ($this->resultType === SQL::FETCH_ONE) {
      $entity = $this->handler->entityFromRow($res, $this->additionalColumns, $this->fetchSubEntities !== self::FETCH_NONE);
      if ($entity instanceof DatabaseEntity && $this->fetchSubEntities !== self::FETCH_NONE) {
        $this->handler->fetchNMRelations([$entity->getId() => $entity], $this->fetchSubEntities === self::FETCH_RECURSIVE);
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