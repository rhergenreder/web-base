<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\SQL\SQL;

interface Persistable {

  public function dependsOn(): array;
  public function getTableName(): string;
  public function getCreateQueries(SQL $sql): array;

}