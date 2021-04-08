<?php

namespace Driver\SQL\Query;

use Api\User\Create;
use Driver\SQL\SQL;

class CreateTrigger extends Query {

  private string $name;
  private string $time;
  private string $event;
  private string $tableName;
  private ?CreateProcedure $procedure;

  public function __construct(SQL $sql, string $triggerName) {
    parent::__construct($sql);
    $this->name = $triggerName;
    $this->time = "AFTER";
    $this->tableName = "";
    $this->event = "";
    $this->procedure = null;
  }

  public function before(): CreateTrigger {
    $this->time = "BEFORE";
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

  public function exec(CreateProcedure $procedure): CreateTrigger {
    $this->procedure = $procedure;
    return $this;
  }

  public function getName(): string { return $this->name; }
  public function getTime(): string { return $this->time; }
  public function getEvent(): string { return $this->event; }
  public function getTable(): string { return $this->tableName; }
  public function getProcedure(): CreateProcedure { return $this->procedure; }

  public function build(array &$params, Query $context = NULL): ?string {
    $name = $this->sql->tableName($this->getName());
    $time = $this->getTime();
    $event = $this->getEvent();
    $tableName = $this->sql->tableName($this->getTable());

    $params = array();
    $query = "CREATE TRIGGER $name $time $event ON $tableName FOR EACH ROW ";
    $triggerBody = $this->sql->createTriggerBody($this);
    if ($triggerBody === null) {
      return null;
    }

    $query .= $triggerBody;
    return $query;
  }
}