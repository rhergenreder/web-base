<?php

namespace Core\Configuration;

use Core\Driver\SQL\SQL;

abstract class DatabaseScript {
  public static abstract function createQueries(SQL $sql);
}