<?php

namespace Core\API {

  use Core\Driver\SQL\Condition\Compare;
  use Core\Objects\Context;

  abstract class GroupsAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function groupExists($name): bool {
      $sql = $this->context->getSQL();
      $res = $sql->select($sql->count())
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
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Controller\NMRelation;
  use Core\Objects\DatabaseEntity\Group;

  class Fetch extends GroupsAPI {

    private int $groupCount;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'page' => new Parameter('page', Parameter::TYPE_INT, true, 1),
        'count' => new Parameter('count', Parameter::TYPE_INT, true, 20)
      ));

      $this->groupCount = 0;
    }

    public function _execute(): bool {
      $page = $this->getParam("page");
      if($page < 1) {
        return $this->createError("Invalid page count");
      }

      $count = $this->getParam("count");
      if($count < 1 || $count > 50) {
        return $this->createError("Invalid fetch count");
      }

      $sql = $this->context->getSQL();
      $groupCount = Group::count($sql);
      if ($groupCount === false) {
        return $this->createError("Error fetching group count: " . $sql->getLastError());
      }

      $groups = Group::findBy(Group::createBuilder($sql, false)
        ->orderBy("id")
        ->ascending()
        ->limit($count)
        ->offset(($page - 1) * $count));

      if ($groups !== false) {
        $this->result["groups"] = [];
        $this->result["pageCount"] = intval(ceil($this->groupCount / $count));
        $this->result["totalCount"] = $this->groupCount;

        foreach ($groups as $groupId => $group) {
          $this->result["groups"][$groupId] = $group->jsonSerialize();
          $this->result["groups"][$groupId]["memberCount"] = 0;
        }

        $nmTable = NMRelation::buildTableName("User", "Group");
        $res = $sql->select("group_id", $sql->count("user_id"))
          ->from($nmTable)
          ->groupBy("group_id")
          ->execute();

        if (is_array($res)) {
          foreach ($res as $row) {
            list ($groupId, $memberCount) = [$row["group_id"], $row["user_id_count"]];
            if (isset($this->result["groups"][$groupId])) {
              $this->result["groups"][$groupId]["memberCount"] = $memberCount;
            }
          }
        }
      }

      return $this->success;
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

      $group = new Group();
      $group->name = $name;
      $group->color = $color;

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