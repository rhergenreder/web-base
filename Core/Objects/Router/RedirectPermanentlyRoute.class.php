<?php

namespace Core\Objects\Router;

use Core\Driver\SQL\SQL;

class RedirectPermanentlyRoute extends RedirectRoute {

  const HTTP_STATUS_CODE = 308;

  public function __construct(string $pattern, bool $exact, string $destination) {
    parent::__construct("redirect_permanently", $pattern, $exact, $destination, self::HTTP_STATUS_CODE);
  }

  public function postFetch(SQL $sql, array $row) {
    parent::postFetch($sql, $row);
    $this->code = self::HTTP_STATUS_CODE;
  }
}