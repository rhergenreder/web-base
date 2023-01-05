<?php

namespace Core\API {

  use Core\Driver\SQL\Expression\Count;
  use Core\Objects\Context;

  abstract class GroupsAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function groupExists($name): bool {
      $sql = $this->context->getSQL();
      $res = $sql->select(new Count())
        ->from("Group")
        ->whereEq("name", $name)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success && $res[0]["count"] > 0;
    }
  }
}

namespace Core\API\Groups {

  use Core\API\GroupsAPI;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\Traits\Pagination;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Expression\Alias;
  use Core\Driver\SQL\Expression\Count;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Controller\NMRelation;
  use Core\Objects\DatabaseEntity\Group;

  class Fetch extends GroupsAPI {

    use Pagination;

    private int $groupCount;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall,
        self::getPaginationParameters(['id', 'name', 'member_count'])
      );

      $this->groupCount = 0;
    }

    public function _execute(): bool {

      $sql = $this->context->getSQL();
      if (!$this->initPagination($sql, Group::class)) {
        return false;
      }

      $memberCount = new Alias($sql->select(new Count())
        ->from(NMRelation::buildTableName("User", "Group"))
        ->whereEq("group_id", new Column("Group.id")), "memberCount");

      $groupsQuery = $this->createPaginationQuery($sql, [$memberCount]);
      $groups = $groupsQuery->execute();
      if ($groups !== false && $groups !== null) {
        $this->result["groups"] = [];

        foreach ($groups as $group) {
          $groupData = $group->jsonSerialize();
          $groupData["memberCount"] = $group["memberCount"];
          $this->result["groups"][] = $groupData;
        }
      } else {
        return $this->createError("Error fetching groups: " . $sql->getLastError());
      }

      return $this->success;
    }
  }

  class Get extends GroupsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ]);
    }

    protected function _execute(): bool {
      $sql = $this->context->getSQL();
      $groupId = $this->getParam("id");
      $group = Group::find($sql, $groupId);
      if ($group === false) {
        return $this->createError("Error fetching group: " . $sql->getLastError());
      } else if ($group === null) {
        return $this->createError("Group not found");
      } else {
        $this->result["group"] = $group->jsonSerialize();
        $this->result["group"]["members"] = $group->getMembers($sql);
      }

      return true;
    }
  }

  class Create extends GroupsAPI {
    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'name' => new StringType('name', 32),
        'color' => new StringType('color', 10),
      ));
    }

    public function _execute(): bool {
      $name = $this->getParam("name");
      if (preg_match("/^[a-zA-Z][a-zA-Z0-9_-]*$/", $name) !== 1) {
        return $this->createError("Invalid name");
      }

      $color = $this->getParam("color");
      if (preg_match("/^#[a-fA-F0-9]{3,6}$/", $color) !== 1) {
        return $this->createError("Invalid color");
      }

      $exists = $this->groupExists($name);
      if (!$this->success) {
        return false;
      } else if ($exists) {
        return $this->createError("A group with this name already exists");
      }

      $sql = $this->context->getSQL();

      $group = new Group(null, $name, $color);
      $this->success = ($group->save($sql) !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["id"] = $group->getId();
      }

      return $this->success;
    }
  }

  class Delete extends GroupsAPI {
    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));
    }

    public function _execute(): bool {
      $id = $this->getParam("id");
      if (in_array($id, array_keys(Group::GROUPS))) {
        return $this->createError("You cannot delete a default group.");
      }

      $sql = $this->context->getSQL();
      $group = Group::find($sql, $id);

      $this->success = ($group !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success && $group === null) {
        return $this->createError("This group does not exist.");
      }

      $this->success = ($group->delete($sql) !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}