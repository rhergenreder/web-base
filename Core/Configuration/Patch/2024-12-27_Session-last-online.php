<?php

use Core\Configuration\CreateDatabase;
use Core\Driver\SQL\Column\DateTimeColumn;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Objects\DatabaseEntity\Session;

$handler = Session::getHandler($sql);
$queries[] = $sql->alterTable($handler->getTableName())
->add(new DateTimeColumn($handler->getColumnName("lastOnline"), false, new CurrentTimeStamp()));

CreateDatabase::loadDefaultACL($sql, $queries, [
  \Core\API\User\GetSessions::class,
  \Core\API\User\DestroySession::class
]);