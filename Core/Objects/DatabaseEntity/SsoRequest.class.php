<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Unique;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class SsoRequest extends DatabaseEntity {

  const SSO_REQUEST_DURABILITY = 15; // in minutes

  #[MaxLength(128)]
  #[Unique]
  private string $identifier;

  private SsoProvider $ssoProvider;

  private \DateTime $validUntil;

  #[DefaultValue(false)]
  private bool $used;

  private ?string $redirectUrl;

  public static function create(SQL $sql, SsoProvider $ssoProvider, ?string $redirectUrl): ?SsoRequest {
    $request = new SsoRequest();
    $request->identifier = uuidv4();
    $request->ssoProvider = $ssoProvider;
    $request->used = false;
    $request->validUntil = (new \DateTime())->modify(sprintf('+%d minutes', self::SSO_REQUEST_DURABILITY));
    $request->redirectUrl = $redirectUrl;
    if ($request->save($sql)) {
      return $request;
    } else {
      return null;
    }
  }

  public function getIdentifier() : string {
    return $this->identifier;
  }

  public function getRedirectUrl() : ?string {
    return $this->redirectUrl;
  }

  public function wasUsed() : bool {
    return $this->used;
  }

  public function isValid() : bool {
    return !isInPast($this->validUntil);
  }

  public function getProvider() : SsoProvider {
    return $this->ssoProvider;
  }

  public function invalidate(SQL $sql) : bool {
    $this->used = true;
    return $this->save($sql, ["used"]);
  }

}