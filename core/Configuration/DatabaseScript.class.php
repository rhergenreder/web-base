<?php

namespace Configuration;

use Driver\SQL\SQL;

abstract class DatabaseScript {
  public static abstract function createQueries(SQL $sql);
}