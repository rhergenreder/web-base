<?php


namespace Driver\SQL\Query;

use Driver\SQL\Column\Column;
use Driver\SQL\SQL;

class CreateProcedure extends Query {

  private string $name;
  private array $parameters;
  private array $statements;
  private $returns;

  public function __construct(SQL $sql, string $procName) {
    parent::__construct($sql);
    $this->name = $procName;
    $this->parameters = [];
    $this->statements = [];
    $this->returns = NULL;
  }

  public function param(Column $parameter): CreateProcedure {
    $this->parameters[] = $parameter;
    return $this;
  }

  public function returns($column): CreateProcedure {
    $this->returns = $column;
    return $this;
  }

  public function exec(array $statements): CreateProcedure {
    $this->statements = $statements;
    return $this;
  }

  public function build(array &$params): ?string {
    $head = $this->sql->getProcedureHead($this);
    $body = $this->sql->getProcedureBody($this);
    $tail = $this->sql->getProcedureTail();
    return "$head BEGIN $body END; $tail";
  }

  public function getName(): string { return $this->name; }
  public function getParameters(): array { return $this->parameters; }
  public function getReturns() { return $this->returns; }
  public function getStatements(): array { return $this->statements; }
}