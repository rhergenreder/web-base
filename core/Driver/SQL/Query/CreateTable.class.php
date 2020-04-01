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

class CreateTable extends Query {

  private $tableName;
  private $columns;
  private $constraints;
  private $ifNotExists;

  public function __construct($sql, $name) {
    parent::__construct($sql);
    $this->tableName = $name;
    $this->columns = array();
    $this->constraints = array();
    $this->ifNotExists = false;
  }

  public function addSerial($name) {
    $this->columns[$name] = new SerialColumn($name);
    return $this;
  }

  public function addString($name, $maxSize=NULL, $nullable=false, $defaultValue=NULL) {
    $this->columns[$name] = new StringColumn($name, $maxSize, $nullable, $defaultValue);
    return $this;
  }

  public function addDateTime($name, $nullable=false, $defaultNow=false) {
    $this->columns[$name] = new DateTimeColumn($name, $nullable, $defaultNow);
    return $this;
  }

  public function addInt($name, $nullable=false, $defaultValue=NULL) {
    $this->columns[$name] = new IntColumn($name, $nullable, $defaultValue);
    return $this;
  }

  public function addBool($name, $defaultValue=false) {
    $this->columns[$name] = new BoolColumn($name, $defaultValue);
    return $this;
  }

  public function addJson($name, $nullable=false, $defaultValue=NULL) {
    $this->columns[$name] = new JsonColumn($name, $nullable, $defaultValue);
    return $this;
  }

  public function addEnum($name, $values, $nullable=false, $defaultValue=NULL) {
    $this->columns[$name] = new EnumColumn($name, $values, $nullable, $defaultValue);
    return $this;
  }

  public function primaryKey(...$names) {
    $this->constraints[] = new PrimaryKey($names);
    return $this;
  }

  public function unique(...$names) {
    $this->constraints[] = new Unique($names);
    return $this;
  }

  public function foreignKey($name, $refTable, $refColumn, $strategy = NULL) {
    $this->constraints[] = new ForeignKey($name, $refTable, $refColumn, $strategy);
    return $this;
  }

  public function onlyIfNotExists() {
    $this->ifNotExists = true;
    return $this;
  }

  public function execute() {
    return $this->sql->executeCreateTable($this);
  }

  public function ifNotExists() { return $this->ifNotExists; }
  public function getTableName() { return $this->tableName; }
  public function getColumns() { return $this->columns; }
  public function getConstraints() { return $this->constraints; }
};

?>
