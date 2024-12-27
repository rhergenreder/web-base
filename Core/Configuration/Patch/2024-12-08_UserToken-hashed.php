<?php

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Expression\Hash;
use Core\Objects\DatabaseEntity\UserToken;

$handler = UserToken::getHandler($sql);
$columnSize = 512 / 8 * 2; // sha512 as hex
$tokenTable = $handler->getTableName();
$tokenColumn = $handler->getColumnName("token");

$queries[] = $sql->alterTable($tokenTable)
  ->modify(new StringColumn($tokenColumn, $columnSize));

$queries[] = $sql->update($tokenTable)
  ->set($tokenColumn, new Hash(Hash::SHA_512, new Column($tokenColumn)));
