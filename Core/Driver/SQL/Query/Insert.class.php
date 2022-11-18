<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Strategy\Strategy;

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

  public function getTableName(): string { return $this->tableName; }
  public function getColumns(): array { return $this->columns; }
  public function getRows(): array { return $this->rows; }
  public function onDuplicateKey(): ?Strategy { return $this->onDuplicateKey; }
  public function getReturning(): ?string { return $this->returning; }

  public function build(array &$params): ?string {
    $tableName = $this->sql->tableName($this->getTableName());
    $columns = $this->getColumns();
    $rows = $this->getRows();

    if (empty($rows)) {
      $this->sql->setLastError("No rows to insert given.");
      return null;
    }

    if (is_null($columns) || empty($columns)) {
      $columnStr = "";
    } else {
      $columnStr = " (" . $this->sql->columnName($columns) . ")";
    }

    $values = array();
    foreach ($rows as $row) {
      $rowPlaceHolder = array();
      foreach ($row as $val) {
        $rowPlaceHolder[] = $this->sql->addValue($val, $params);
      }

      $values[] = "(" . implode(",", $rowPlaceHolder) . ")";
    }

    $values = implode(",", $values);

    $onDuplicateKey = $this->sql->getOnDuplicateStrategy($this->onDuplicateKey(), $params);
    if ($onDuplicateKey === FALSE) {
      return null;
    }

    $returningCol = $this->getReturning();
    $returning = $this->sql->getReturning($returningCol);
    return "INSERT INTO $tableName$columnStr VALUES $values$onDuplicateKey$returning";
  }
}