<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\Join;
use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;

/**
 * this class is similar to \Driver\SQL\Query\Select but with reduced functionality
 * and more adapted to entities.
*/
class DatabaseEntityQuery {

  private DatabaseEntityHandler $handler;
  private Select $selectQuery;
  private int $resultType;

  private function __construct(DatabaseEntityHandler $handler, int $resultType) {
    $this->handler = $handler;
    $this->selectQuery = $handler->getSelectQuery();
    $this->resultType = $resultType;

    if ($this->resultType === SQL::FETCH_ONE) {
      $this->selectQuery->first();
    }
  }

  public static function fetchAll(DatabaseEntityHandler $handler): DatabaseEntityQuery {
    return new DatabaseEntityQuery($handler, SQL::FETCH_ALL);
  }

  public static function fetchOne(DatabaseEntityHandler $handler): DatabaseEntityQuery {
    return new DatabaseEntityQuery($handler, SQL::FETCH_ONE);
  }

  public function limit(int $limit): DatabaseEntityQuery {
    $this->selectQuery->limit($limit);
    return $this;
  }

  public function where(Condition ...$condition): DatabaseEntityQuery {
    $this->selectQuery->where(...$condition);
    return $this;
  }

  public function orderBy(string ...$column): DatabaseEntityQuery {
    $this->selectQuery->orderBy(...$column);
    return $this;
  }

  public function ascending(): DatabaseEntityQuery {
    $this->selectQuery->ascending();
    return $this;
  }

  public function descending(): DatabaseEntityQuery {
    $this->selectQuery->descending();
    return $this;
  }

  // TODO: clean this up
  public function fetchEntities(bool $recursive = false): DatabaseEntityQuery {

    // $this->selectQuery->dump();

    $relIndex = 1;
    foreach ($this->handler->getRelations() as $propertyName => $relationHandler) {
      $this->fetchRelation($propertyName, $this->handler->getTableName(), $this->handler, $relationHandler, $relIndex, $recursive);
    }

    return $this;
  }

  private function fetchRelation(string $propertyName, string $tableName, DatabaseEntityHandler $src, DatabaseEntityHandler $relationHandler,
                                 int &$relIndex = 1, bool $recursive = false, string $relationColumnPrefix = "") {

    $columns = $src->getColumns();

    $foreignColumn = $columns[$propertyName];
    $foreignColumnName = $foreignColumn->getName();
    $referencedTable = $relationHandler->getTableName();
    $isNullable = !$foreignColumn->notNull();
    $alias = "t$relIndex"; // t1, t2, t3, ...
    $relIndex++;


    if ($isNullable) {
      $this->selectQuery->leftJoin($referencedTable, "$tableName.$foreignColumnName", "$alias.id", $alias);
    } else {
      $this->selectQuery->innerJoin($referencedTable, "$tableName.$foreignColumnName", "$alias.id", $alias);
    }

    $relationColumnPrefix .= DatabaseEntityHandler::getColumnName($propertyName) . "_";
    $recursiveRelations = $relationHandler->getRelations();
    foreach ($relationHandler->getColumns() as $relPropertyName => $relColumn) {
      $relColumnName = $relColumn->getName();
      if (!isset($recursiveRelations[$relPropertyName]) || $recursive) {
        $this->selectQuery->addValue("$alias.$relColumnName as $relationColumnPrefix$relColumnName");
        if (isset($recursiveRelations[$relPropertyName]) && $recursive) {
          $this->fetchRelation($relPropertyName, $alias, $relationHandler, $recursiveRelations[$relPropertyName], $relIndex, $recursive, $relationColumnPrefix);
        }
      }
    }
  }

  public function execute(): DatabaseEntity|array|null {
    $res = $this->selectQuery->execute();
    if ($res === null || $res === false) {
      return null;
    }

    if ($this->resultType === SQL::FETCH_ALL) {
      $entities = [];
      foreach ($res as $row) {
        $entity = $this->handler->entityFromRow($row);
        if ($entity) {
          $entities[$entity->getId()] = $entity;
        }
      }
      return $entities;
    } else if ($this->resultType === SQL::FETCH_ONE) {
      return $this->handler->entityFromRow($res);
    } else {
      $this->handler->getLogger()->error("Invalid result type for query builder, must be FETCH_ALL or FETCH_ONE");
      return null;
    }
  }

  public function addJoin(Join $join): DatabaseEntityQuery {
    $this->selectQuery->addJoin($join);
    return $this;
  }
}