<?php

use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Constraint\ForeignKey;
use Core\Driver\SQL\Strategy\CascadeStrategy;
use Core\Objects\DatabaseEntity\SsoProvider;
use Core\Objects\DatabaseEntity\User;

$userHandler = User::getHandler($sql);
$ssoProviderHandler = SsoProvider::getHandler($sql);

$userTable = $userHandler->getTableName();
$ssoProviderTable = $ssoProviderHandler->getTableName();
$ssoProviderColumn = $userHandler->getColumnName("ssoProvider", false);
$passwordColumn = $userHandler->getColumnName("password");

$queries = array_merge($queries, $ssoProviderHandler->getCreateQueries($sql));

$queries[] = $sql->alterTable($userTable)
  ->add(new IntColumn($ssoProviderColumn, true,null));

// make password nullable for SSO-login
$queries[] = $sql->alterTable($userTable)
  ->modify(new StringColumn($passwordColumn, 128,true));

$constraint = new ForeignKey($ssoProviderColumn, $ssoProviderTable, "id", new CascadeStrategy());
$constraint->setName("${userTable}_ibfk_$ssoProviderColumn");
$queries[] = $sql->alterTable($userTable)
  ->add($constraint);