<?php

namespace Objects;

abstract class ApiObject implements \JsonSerializable {

  public abstract function jsonSerialize();

  public function __toString() { return json_encode($this); }

}
