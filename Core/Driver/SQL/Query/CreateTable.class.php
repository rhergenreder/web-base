<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\Column\BigIntColumn;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\DoubleColumn;
use Core\Driver\SQL\Column\FloatColumn;
use Core\Driver\SQL\Column\NumericColumn;
use Core\Driver\SQL\Column\SerialColumn;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Column\DateTimeColumn;
use Core\Driver\SQL\Column\EnumColumn;
use Core\Driver\SQL\Column\BoolColumn;
use Core\Driver\SQL\Column\JsonColumn;

use Core\Driver\SQL\Constraint\Constraint;
use Core\Driver\SQL\Constraint\PrimaryKey;
use Core\Driver\SQL\Constraint\Unique;
use Core\Driver\SQL\Constraint\ForeignKey;
use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Strategy\Strategy;

class CreateTable extends Query {

  private string $tableName;
  private array $columns;
  private array $constraints;
  private bool $ifNotExists;

  public function __construct(SQL $sql, string $name) {
    parent::__construct($sql);
    $this->tableName = $name;
    $this->columns = array();
    $this->constraints = array();
    $this->ifNotExists = false;
  }

  public function addColumn(Column $column): CreateTable {
    $this->columns[$column->getName()] = $column;
    return $this;
  }

  public function addConstraint(Constraint $constraint): CreateTable {
    $this->constraints[] = $constraint;
    return $this;
  }

  public function addSerial(string $name): CreateTable {
    $this->columns[$name] = new SerialColumn($name);
    return $this;
  }

  public function addString(string $name, ?int $maxSize = NULL, bool $nullable = false, $defaultValue = NULL): CreateTable {
    $this->columns[$name] = new StringColumn($name, $maxSize, $nullable, $defaultValue);
    return $this;
  }

  public function addDateTime(string $name, bool $nullable = false, $defaultValue = NULL): CreateTable {
    $this->columns[$name] = new DateTimeColumn($name, $nullable, $defaultValue);
    return $this;
  }

  public function addInt(string $name, bool $nullable = false, $defaultValue = NULL, bool $unsigned = false): CreateTable {
    $this->columns[$name] = new IntColumn($name, $nullable, $defaultValue, $unsigned);
    return $this;
  }

  public function addBigInt(string $name, bool $nullable = false, $defaultValue = NULL, bool $unsigned = false): CreateTable {
    $this->columns[$name] = new BigIntColumn($name, $nullable, $defaultValue, $unsigned);
    return $this;
  }

  public function addBool(string $name, $defaultValue = false): CreateTable {
    $this->columns[$name] = new BoolColumn($name, $defaultValue);
    return $this;
  }

  public function addJson(string $name, bool $nullable = false, $defaultValue = NULL): CreateTable {
    $this->columns[$name] = new JsonColumn($name, $nullable, $defaultValue);
    return $this;
  }

  public function addEnum(string $name, array $values, bool $nullable = false, $defaultValue = NULL): CreateTable {
    $this->columns[$name] = new EnumColumn($name, $values, $nullable, $defaultValue);
    return $this;
  }

  public function addNumeric(string $name, bool $nullable = false, $defaultValue = NULL, ?int $digitsTotal = 10, ?int $digitsDecimal = 0): CreateTable {
    $this->columns[$name] = new NumericColumn($name, $nullable, $defaultValue, $digitsTotal, $digitsDecimal);
    return $this;
  }

  public function addFloat(string $name, bool $nullable = false, $defaultValue = NULL, ?int $digitsTotal = null, ?int $digitsDecimal = null): CreateTable {
    $this->columns[$name] = new FloatColumn($name, $nullable, $defaultValue, $digitsTotal, $digitsDecimal);
    return $this;
  }

  public function addDouble(string $name, bool $nullable = false, $defaultValue = NULL, ?int $digitsTotal = null, ?int $digitsDecimal = null): CreateTable {
    $this->columns[$name] = new DoubleColumn($name, $nullable, $defaultValue, $digitsTotal, $digitsDecimal);
    return $this;
  }

  public function primaryKey(...$names): CreateTable {
    $pk = new PrimaryKey($names);
    $pk->setName(strtolower("pk_{$this->tableName}"));
    $this->constraints[] = $pk;
    return $this;
  }

  public function unique(...$names): CreateTable {
    $this->constraints[] = new Unique($names);
    return $this;
  }

  public function foreignKey(string $column, string $refTable, string $refColumn, ?Strategy $strategy = NULL): CreateTable {
    $fk = new ForeignKey($column, $refTable, $refColumn, $strategy);
    $fk->setName(strtolower("fk_{$this->tableName}_${refTable}_${refColumn}"));
    $this->constraints[] = $fk;
    return $this;
  }

  public function onlyIfNotExists(): CreateTable {
    $this->ifNotExists = true;
    return $this;
  }

  public function ifNotExists(): bool { return $this->ifNotExists; }
  public function getTableName(): string { return $this->tableName; }
  public function getColumns(): array { return $this->columns; }
  public function getConstraints(): array { return $this->constraints; }

  public function build(array &$params): ?string {
    $tableName = $this->sql->tableName($this->getTableName());
    $ifNotExists = $this->ifNotExists() ? " IF NOT EXISTS" : "";

    $entries = array();
    foreach ($this->getColumns() as $column) {
      $entries[] = ($tmp = $this->sql->getColumnDefinition($column));
      if (is_null($tmp)) {
        return false;
      }
    }

    foreach ($this->getConstraints() as $constraint) {
      $entries[] = ($tmp = $this->sql->getConstraintDefinition($constraint));
      if (is_null($tmp)) {
        return false;
      }
    }

    $entries = implode(",", $entries);
    return "CREATE TABLE$ifNotExists $tableName ($entries)";
  }
}
