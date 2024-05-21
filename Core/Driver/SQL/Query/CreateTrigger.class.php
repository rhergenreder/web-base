<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;
use Exception;

class CreateTrigger extends Query {

  private string $name;
  private string $time;
  private string $event;
  private string $tableName;
  private array $parameters;

  private bool $ifNotExist;

  private ?CreateProcedure $procedure;

  public function __construct(SQL $sql, string $triggerName) {
    parent::__construct($sql);
    $this->name = $triggerName;
    $this->time = "AFTER";
    $this->tableName = "";
    $this->event = "";
    $this->parameters = [];
    $this->procedure = null;
    $this->ifNotExist = false;
  }

  public function before(): CreateTrigger {
    $this->time = "BEFORE";
    return $this;
  }

  public function onlyIfNotExist(): CreateTrigger {
    $this->ifNotExist = true;
    return $this;
  }

  public function after(): CreateTrigger {
    $this->time = "AFTER";
    return $this;
  }

  public function update(string $table): CreateTrigger {
    $this->tableName = $table;
    $this->event = "UPDATE";
    return $this;
  }

  public function insert(string $table): CreateTrigger {
    $this->tableName = $table;
    $this->event = "INSERT";
    return $this;
  }

  public function delete(string $table): CreateTrigger {
    $this->tableName = $table;
    $this->event = "DELETE";
    return $this;
  }

  public function exec(CreateProcedure $procedure, array $parameters = []): CreateTrigger {
    $this->procedure = $procedure;
    $this->parameters = $parameters;
    return $this;
  }

  public function getName(): string { return $this->name; }
  public function getTime(): string { return $this->time; }
  public function getEvent(): string { return $this->event; }
  public function getTable(): string { return $this->tableName; }
  public function getProcedure(): CreateProcedure { return $this->procedure; }

  public function build(array &$params): ?string {
    $name = $this->sql->tableName($this->getName());
    $time = $this->getTime();
    $event = $this->getEvent();
    $tableName = $this->sql->tableName($this->getTable());

    $params = array();

    if ($this->sql instanceof MySQL) {
      $query = "CREATE TRIGGER";
      if ($this->ifNotExist) {
        $query .= " IF NOT EXISTS";
      }
    } else if ($this->sql instanceof PostgreSQL) {
      $ifNotExists = $this->ifNotExist ? " OR REPLACE" : "";
      $query = "CREATE$ifNotExists TRIGGER";
    } else {
      throw new Exception("CreateTrigger Not implemented for driver type: " . get_class($this->sql));
    }

    $query .= " $name $time $event ON $tableName FOR EACH ROW ";
    $triggerBody = $this->sql->createTriggerBody($this, $this->parameters);
    if ($triggerBody === null) {
      return null;
    }

    $query .= $triggerBody;
    return $query;
  }
}