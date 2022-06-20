<?php

namespace Objects\DatabaseEntity;

use Api\Parameter\Parameter;
use Driver\SQL\Expression\CurrentTimeStamp;
use Objects\DatabaseEntity\Attribute\DefaultValue;
use Objects\DatabaseEntity\Attribute\MaxLength;

class News extends DatabaseEntity {

  public User $publishedBy;
  #[DefaultValue(CurrentTimeStamp::class)] private \DateTime $publishedAt;
  #[MaxLength(128)] public string $title;
  #[MaxLength(1024)] public string $text;

  public function __construct(?int $id = null) {
    parent::__construct($id);
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "publishedBy" => $this->publishedBy->jsonSerialize(),
      "publishedAt" => $this->publishedAt->format(Parameter::DATE_TIME_FORMAT),
      "title" => $this->title,
      "text" => $this->text
    ];
  }
}