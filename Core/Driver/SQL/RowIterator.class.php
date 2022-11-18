<?php

namespace Core\Driver\SQL;

abstract class RowIterator implements \Iterator {

  protected $resultSet;
  protected int $rowIndex;
  protected array $fetchedRows;
  protected int $numRows;
  protected bool $useCache;

  public function __construct($resultSet, bool $useCache = false) {
    $this->resultSet = $resultSet;
    $this->fetchedRows = [];
    $this->rowIndex = 0;
    $this->numRows = $this->getNumRows();
    $this->useCache = $useCache;
  }

  protected abstract function getNumRows(): int;
  protected abstract function fetchRow(int $index): array;

  public function current() {
    return $this->fetchRow($this->rowIndex);
  }

  public function next() {
    $this->rowIndex++;
  }

  public function key() {
    return $this->rowIndex;
  }

  public function valid(): bool {
    return $this->rowIndex < $this->numRows;
  }
}
