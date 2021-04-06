<?php

namespace Driver\SQL;

class Join {

  private string $type;
  private string $table;
  private string $columnA;
  private string $columnB;
  private ?string $tableAlias;

  public function __construct(string $type, string $table, string $columnA, string $columnB, ?string $tableAlias = null) {
    $this->type = $type;
    $this->table = $table;
    $this->columnA = $columnA;
    $this->columnB = $columnB;
    $this->tableAlias = $tableAlias;
  }

  public function getType(): string { return $this->type; }
  public function getTable(): string { return $this->table; }
  public function getColumnA(): string { return $this->columnA; }
  public function getColumnB(): string { return $this->columnB; }
  public function getTableAlias(): ?string { return $this->tableAlias; }

}