<?php

namespace Core\API\Traits;

use Core\API\Parameter\Parameter;
use Core\API\Parameter\StringType;
use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntityHandler;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntityQuery;
use Core\Objects\DatabaseEntity\User;

trait Pagination {

  static function getPaginationParameters(array $orderColumns, string $defaultOrderBy = "id", string $defaultSortOrder = "asc"): array {
    return [
      'page' => new Parameter('page', Parameter::TYPE_INT, true, 1),
      'count' => new Parameter('count', Parameter::TYPE_INT, true, 20),
      'orderBy' => new StringType('orderBy', -1, true, $defaultOrderBy, $orderColumns),
      'sortOrder' => new StringType('sortOrder', -1, true, $defaultSortOrder, ['asc', 'desc']),
    ];
  }

  function initPagination(SQL $sql, string $class, ?Condition $condition = null, int $maxPageSize = 100, ?array $joins = null): bool {
    $this->paginationClass = $class;
    $this->paginationCondition = $condition;
    if (!$this->validateParameters($maxPageSize)) {
      return false;
    }

    $this->entityCount = call_user_func("$this->paginationClass::count", $sql, $condition, $joins);
    if ($this->entityCount === false) {
      return $this->createError("Error fetching $this->paginationClass::count: " . $sql->getLastError());
    }

    $pageCount = intval(ceil($this->entityCount / $this->pageSize));
    $this->page = min($this->page, $pageCount); // number of pages changed due to pageSize / filter

    $this->result["pagination"] = [
      "current" => $this->page,
      "pageSize" => $this->pageSize,
      "pageCount" => $pageCount,
      "total" => $this->entityCount
    ];

    return true;
  }

  function validateParameters(int $maxCount = 100): bool {
    $this->page = $this->getParam("page");
    if ($this->page < 1) {
      return $this->createError("Invalid page count");
    }

    $this->pageSize = $this->getParam("count");
    if ($this->pageSize < 1 || $this->pageSize > $maxCount) {
      return $this->createError("Invalid fetch count");
    }

    return true;
  }

  function createPaginationQuery(SQL $sql, ?array $additionalValues = null, ?array $joins = null): DatabaseEntityQuery {
    $page = $this->getParam("page");
    $count = $this->getParam("count");
    $orderBy = $this->getParam("orderBy");
    $sortOrder = $this->getParam("sortOrder");

    $baseQuery = call_user_func("$this->paginationClass::createBuilder", $sql, false);
    $entityQuery = $baseQuery
      ->fetchEntities()
      ->limit($count)
      ->offset(($page - 1) * $count);

    if ($this->paginationCondition) {
      $entityQuery->where($this->paginationCondition);
    }

    if ($additionalValues) {
      foreach ($additionalValues as $additionalValue) {
        $entityQuery->addCustomValue($additionalValue);
      }
    }

    if ($joins) {
      foreach ($joins as $join) {
        $entityQuery->addJoin($join);
      }
    }

    if ($orderBy) {
      $handler = $baseQuery->getHandler();
      $baseTable = $handler->getTableName();
      $sortColumn = DatabaseEntityHandler::buildColumnName($orderBy);
      $fullyQualifiedColumn = "$baseTable.$sortColumn";
      $selectedColumns = $baseQuery->getSelectValues();

      if (in_array($sortColumn, $selectedColumns)) {
        $entityQuery->orderBy($sortColumn);
      } else if (in_array($fullyQualifiedColumn, $selectedColumns)) {
        $entityQuery->orderBy($fullyQualifiedColumn);
      } else {
        $entityQuery->orderBy($orderBy);
      }
    }

    if ($sortOrder === "asc") {
      $entityQuery->ascending();
    } else {
      $entityQuery->descending();
    }

    return $entityQuery;
  }
}