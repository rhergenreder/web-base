<?php

namespace Objects\DatabaseEntity;

use Api\Parameter\Parameter;
use Driver\SQL\Expression\CurrentTimeStamp;
use Objects\DatabaseEntity\Attribute\DefaultValue;
use Objects\DatabaseEntity\Attribute\Enum;
use Objects\DatabaseEntity\Attribute\MaxLength;

class SystemLog extends DatabaseEntity {

  #[DefaultValue(CurrentTimeStamp::class)] private \DateTime $timestamp;
  private string $message;
  #[MaxLength(64)] #[DefaultValue('global')] private string $module;
  #[Enum('debug','info','warning','error','severe')] private string $severity;

  public function __construct(?int $id = null) {
    parent::__construct($id);
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "timestamp" => $this->timestamp->format(Parameter::DATE_TIME_FORMAT),
      "message" => $this->message,
      "module" => $this->module,
      "severity" => $this->severity
    ];
  }
}