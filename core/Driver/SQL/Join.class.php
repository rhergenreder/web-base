<?php

namespace Driver\SQL;

use Driver\SQL\Column\Column;
use Driver\SQL\Condition\Compare;

class Join {

  private string $type;
  private string $table;
  private string $columnA;
  private string $columnB;
  private ?string $tableAlias;
  private array $conditions;

  public function __construct(string $type, string $table, string $columnA, string $columnB, ?string $tableAlias = null, array $conditions = []) {
    $this->type = $type;
    $this->table = $table;
    $this->columnA = $columnA;
    $this->columnB = $columnB;
    $this->tableAlias = $tableAlias;
    $this->conditions = $conditions;
    array_unshift($this->conditions , new Compare($columnA, new Column($columnB), "="));
  }

  public function getType(): string { return $this->type; }
  public function getTable(): string { return $this->table; }
  public function getColumnA(): string { return $this->columnA; }
  public function getColumnB(): string { return $this->columnB; }
  public function getTableAlias(): ?string { return $this->tableAlias; }
  public function getConditions(): array { return $this->conditions; }

}