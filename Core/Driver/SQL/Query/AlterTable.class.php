<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\EnumColumn;
use Core\Driver\SQL\Constraint\Constraint;
use Core\Driver\SQL\Constraint\PrimaryKey;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;

class AlterTable extends Query {

  private string $table;
  private string $action;
  private $data;

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

  public function resetAutoIncrement(): AlterTable {
    $this->action = "RESET_AUTO_INCREMENT";
    return $this;
  }

  public function addToEnum(EnumColumn $column, string $newValue): AlterTable {
    $this->action = "MODIFY";
    $this->column = $column;
    $this->data = $newValue;
    return $this;
  }

  public function getAction(): string { return $this->action; }
  public function getColumn(): ?Column { return $this->column; }
  public function getConstraint(): ?Constraint { return $this->constraint; }
  public function getTable(): string { return $this->table; }

  public function build(array &$params): ?string {
    $tableName = $this->sql->tableName($this->getTable());
    $action = $this->getAction();
    $column = $this->getColumn();
    $constraint = $this->getConstraint();

    if ($action === "RESET_AUTO_INCREMENT") {
      return "ALTER TABLE $tableName AUTO_INCREMENT=1";
    }

    $query = "ALTER TABLE $tableName $action ";

    if ($column) {
      $query .= "COLUMN ";
      if ($action === "DROP") {
        $query .= $this->sql->columnName($column->getName());
      } else {
        // ADD or modify
        if ($column instanceof EnumColumn) {
          if ($this->sql instanceof PostgreSQL) {
            $typeName = $this->sql->getColumnType($column);
            $value = $this->sql->addValue($this->data, $params);
            return "ALTER TYPE $typeName ADD VALUE $value";
          }
          $column->addValue($this->data);
        }

        $query .= $this->sql->getColumnDefinition($column);
      }
    } else if ($constraint) {
      if ($action === "DROP") {
        if ($constraint instanceof PrimaryKey) {
          $query .= "PRIMARY KEY";
        } else {
          $constraintName = $constraint->getName();
          if ($constraintName) {
            $query .= "CONSTRAINT " . $this->sql->columnName($constraintName);
          } else {
            $this->sql->setLastError("Cannot DROP CONSTRAINT without a constraint name.");
            return null;
          }
        }
      } else if ($action === "ADD") {
        $constraintName = $constraint->getName();

        if ($constraintName) {
          $query .= "CONSTRAINT ";
          $query .= $constraintName;
          $query .= " ";
          $query .= $this->sql->getConstraintDefinition($constraint);
        } else {
          $this->sql->setLastError("Cannot ADD CONSTRAINT without a constraint name.");
          return null;
        }
      } else if ($action === "MODIFY") {
        $this->sql->setLastError("MODIFY CONSTRAINT foreign key is not supported.");
        return null;
      }
    } else {
      $this->sql->setLastError("'ALTER TABLE' requires at least a column or a constraint.");
      return null;
    }

    return $query;
  }
}