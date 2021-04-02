<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;
use Driver\SQL\Strategy\Strategy;

class Insert extends Query {

  private string $tableName;
  private array $columns;
  private array $rows;
  private ?Strategy $onDuplicateKey;
  private ?string $returning;

  public function __construct(SQL $sql, string $name, array $columns = array()) {
    parent::__construct($sql);
    $this->tableName = $name;
    $this->columns = $columns;
    $this->rows = array();
    $this->onDuplicateKey = NULL;
    $this->returning = NULL;
  }

  public function addRow(...$values): Insert {
    $this->rows[] = $values;
    return $this;
  }

  public function onDuplicateKeyStrategy(Strategy $strategy): Insert {
    $this->onDuplicateKey = $strategy;
    return $this;
  }

  public function returning(string $column): Insert {
    $this->returning = $column;
    return $this;
  }

  public function execute(): bool {
    return $this->sql->executeInsert($this);
  }

  public function getTableName(): string { return $this->tableName; }
  public function getColumns(): array { return $this->columns; }
  public function getRows(): array { return $this->rows; }
  public function onDuplicateKey(): ?Strategy { return $this->onDuplicateKey; }
  public function getReturning(): ?string { return $this->returning; }
}