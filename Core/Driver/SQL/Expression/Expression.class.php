<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

abstract class Expression {

  abstract function getExpression(SQL $sql, array &$params): string;

}