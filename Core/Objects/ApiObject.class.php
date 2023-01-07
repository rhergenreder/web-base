<?php

namespace Core\Objects;

abstract class ApiObject implements \JsonSerializable {

  public function __toString() {
    return json_encode($this->jsonSerialize());
  }

}
