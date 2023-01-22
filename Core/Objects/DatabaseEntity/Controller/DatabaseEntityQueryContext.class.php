<?php

namespace Core\Objects\DatabaseEntity\Controller;

class DatabaseEntityQueryContext {

  // tableName => [ entityId => entity ]
  private array $entityCache;

  public function __construct() {
    $this->entityCache = [];
  }

  public function queryCache(DatabaseEntityHandler $handler, int $id): ?DatabaseEntity {
    $tableName = $handler->getTableName();
    if (isset($this->entityCache[$tableName])) {
      return $this->entityCache[$tableName][$id] ?? null;
    }

    return null;
  }

  public function addCache(DatabaseEntityHandler $handler, DatabaseEntity $entity): void {
    $tableName = $handler->getTableName();
    if (!isset($this->entityCache[$tableName])) {
      $this->entityCache[$tableName] = [];
    }

    $this->entityCache[$tableName][$entity->getId()] = $entity;
  }
}