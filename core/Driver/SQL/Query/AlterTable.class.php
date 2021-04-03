<?php

namespace Driver\SQL\Query;

use Driver\SQL\Column\Column;
use Driver\SQL\Constraint\Constraint;
use Driver\SQL\SQL;

class AlterTable extends Query {

  private string $table;
  private string $action;

  private ?Column $column;
  private ?Constraint $constraint;

  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->table = $table;
  }

  public function add($what): AlterTable {
    if ($what instanceof Column) {
      $this->column = $what;
    } else if ($what instanceof Constraint) {
      $this->constraint = $what;
    } else {
      $this->column = new Column($what);
    }

    $this->action = "ADD";
    return $this;
  }

  public function modify(Column $column): AlterTable {
    $this->column = $column;
    $this->action = "MODIFY";
    return $this;
  }

  public function drop($what): AlterTable {
    if ($what instanceof Column) {
      $this->column = $what;
    } else if ($what instanceof Constraint) {
      $this->constraint = $what;
    } else {
      $this->column = new Column($what);
    }
    $this->action = "DROP";
    return $this;
  }


  public function execute(): bool {
    return $this->sql->executeAlter($this);
  }

  public function getAction(): string { return $this->action; }
  public function getColumn(): ?Column { return $this->column; }
  public function getConstraint(): ?Constraint { return $this->constraint; }
  public function getTable(): string { return $this->table; }
}