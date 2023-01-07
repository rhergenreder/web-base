<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\Enum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class SystemLog extends DatabaseEntity {

  #[DefaultValue(CurrentTimeStamp::class)] private \DateTime $timestamp;
  private string $message;
  #[MaxLength(64)] #[DefaultValue('global')] private string $module;
  #[Enum('debug','info','warning','error','severe')] private string $severity;

  public function __construct(?int $id = null) {
    parent::__construct($id);
  }
}