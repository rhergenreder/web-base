<?php

namespace Driver\SQL\Query;

use Driver\SQL\Column\Column;
use Driver\SQL\Constraint\Constraint;
use Driver\SQL\Constraint\ForeignKey;
use Driver\SQL\Constraint\PrimaryKey;
use Driver\SQL\SQL;

class AlterTable extends Query {

  private string $table;
  private string $action;

  private ?Column $column;
  private ?Constraint $constraint;

  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->table = $table;
    $this->column = null;
    $this->constraint = null;
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

  public function getAction(): string { return $this->action; }
  public function getColumn(): ?Column { return $this->column; }
  public function getConstraint(): ?Constraint { return $this->constraint; }
  public function getTable(): string { return $this->table; }

  public function build(array &$params, Query $context = NULL): ?string {
    $tableName = $this->sql->tableName($this->getTable());
    $action = $this->getAction();
    $column = $this->getColumn();
    $constraint = $this->getConstraint();

    $query = "ALTER TABLE $tableName $action ";

    if ($column) {
      $query .= "COLUMN ";
      if ($action === "DROP") {
        $query .= $this->sql->columnName($column->getName());
      } else {
        // ADD or modify
        $query .= $this->sql->getColumnDefinition($column);
      }
    } else if ($constraint) {
      if ($action === "DROP") {
        if ($constraint instanceof PrimaryKey) {
          $query .= "PRIMARY KEY";
        } else if ($constraint instanceof ForeignKey) {
          // TODO: how can we pass the constraint name here?
          $this->sql->setLastError("DROP CONSTRAINT foreign key is not supported yet.");
          return null;
        }
      } else if ($action === "ADD") {
        $query .= "CONSTRAINT ";
        $query .= $this->sql->getConstraintDefinition($constraint);
      } else if ($action === "MODIFY") {
        $this->sql->setLastError("MODIFY CONSTRAINT foreign key is not supported.");
        return null;
      }
    } else {
      $this->sql->setLastError("ALTER TABLE requires at least a column or a constraint.");
      return null;
    }

    return $query;
  }
}