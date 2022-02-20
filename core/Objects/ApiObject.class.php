<?php

namespace Objects;

abstract class ApiObject implements \JsonSerializable {

  public abstract function jsonSerialize(): array;

  public function __toString() { return json_encode($this->jsonSerialize()); }

}
