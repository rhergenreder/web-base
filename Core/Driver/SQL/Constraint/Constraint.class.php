<?php

namespace Core\Driver\SQL\Constraint;

abstract class Constraint {

  private array $columnNames;
  private ?string $name;

  public function __construct($columnNames, ?string $constraintName = NULL) {
    $this->columnNames = (!is_array($columnNames) ? array($columnNames) : $columnNames);
    $this->name = $constraintName;
  }

  public function getColumnNames(): array { return $this->columnNames; }
  public function getName(): ?string { return $this->name; }
  public function setName(string $name) { $this->name = $name; }
}