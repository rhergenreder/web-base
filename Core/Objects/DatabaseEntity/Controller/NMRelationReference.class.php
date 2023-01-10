<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\SQL\SQL;

class NMRelationReference implements Persistable {

  private DatabaseEntityHandler $handler;
  private string $thisProperty;
  private string $refProperty;

  public function __construct(DatabaseEntityHandler $handler, string $thisProperty, string $refProperty) {
    $this->handler = $handler;
    $this->thisProperty = $thisProperty;
    $this->refProperty = $refProperty;
  }

  public function dependsOn(): array {
    return [$this->handler->getTableName()];
  }

  public function getTableName(): string {
    return $this->handler->getTableName();
  }

  public function getCreateQueries(SQL $sql): array {
    return [];  // nothing to do here, will be managed by other handler
  }

  public function getThisProperty(): string {
    return $this->thisProperty;
  }

  public function getRefProperty(): string {
    return $this->refProperty;
  }

  public function getRelHandler(): DatabaseEntityHandler {
    return $this->handler;
  }
}