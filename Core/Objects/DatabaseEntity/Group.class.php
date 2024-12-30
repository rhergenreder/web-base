<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\DatabaseEntity\Controller\NMRelation;

class Group extends DatabaseEntity {

  const ADMIN = 1;
  const SUPPORT = 2;
  const MODERATOR = 3;

  const GROUPS = [
    self::ADMIN => "Administrator",
    self::SUPPORT => "Support",
    self::MODERATOR => "Moderator",
  ];

  #[MaxLength(32)] public string $name;
  #[MaxLength(10)] public string $color;

  public function __construct(?int $id, string $name, string $color) {
    parent::__construct($id);
    $this->name = $name;
    $this->color = $color;
  }

  public function getMembers(SQL $sql): array {
    $nmTable = User::getHandler($sql)->getNMRelation("groups")->getTableName();
    $users = User::findBy(User::createBuilder($sql, false)
      ->innerJoin($nmTable, "user_id", "User.id")
      ->whereEq("group_id", $this->id));

    return User::toJsonArray($users, ["id", "name", "fullName", "profilePicture"]);
  }

  public static function getPredefinedValues(): array {
    return [
      new Group(Group::ADMIN, Group::GROUPS[Group::ADMIN], "#dc3545"),
      new Group(Group::MODERATOR, Group::GROUPS[Group::MODERATOR], "#28a745"),
      new Group(Group::SUPPORT, Group::GROUPS[Group::SUPPORT], "#007bff"),
    ];
  }

  public function delete(SQL $sql): bool {
    if (parent::delete($sql)) {
      $handler = User::getHandler($sql);
      $table = $handler->getNMRelation("groups")->getTableName();
      return $sql->delete($table)->whereEq("group_id", $this->id)->execute();
    } else {
      return false;
    }
  }
}