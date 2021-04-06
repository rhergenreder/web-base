<?php

namespace Driver\SQL\Query;

use Driver\SQL\Column\SerialColumn;
use Driver\SQL\Column\StringColumn;
use Driver\SQL\Column\IntColumn;
use Driver\SQL\Column\DateTimeColumn;
use Driver\SQL\Column\EnumColumn;
use Driver\SQL\Column\BoolColumn;
use Driver\SQL\Column\JsonColumn;

use Driver\SQL\Constraint\PrimaryKey;
use Driver\SQL\Constraint\Unique;
use Driver\SQL\Constraint\ForeignKey;
use Driver\SQL\SQL;
use Driver\SQL\Strategy\Strategy;

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

  public function addInt(string $name, bool $nullable = false, $defaultValue = NULL): CreateTable {
    $this->columns[$name] = new IntColumn($name, $nullable, $defaultValue);
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

  public function primaryKey(...$names): CreateTable {
    $this->constraints[] = new PrimaryKey($names);
    return $this;
  }

  public function unique(...$names): CreateTable {
    $this->constraints[] = new Unique($names);
    return $this;
  }

  public function foreignKey(string $name, string $refTable, string $refColumn, ?Strategy $strategy = NULL): CreateTable {
    $this->constraints[] = new ForeignKey($name, $refTable, $refColumn, $strategy);
    return $this;
  }

  public function onlyIfNotExists(): CreateTable {
    $this->ifNotExists = true;
    return $this;
  }

  public function execute(): bool {
    return $this->sql->executeCreateTable($this);
  }

  public function ifNotExists(): bool { return $this->ifNotExists; }
  public function getTableName(): string { return $this->tableName; }
  public function getColumns(): array { return $this->columns; }
  public function getConstraints(): array { return $this->constraints; }
}
