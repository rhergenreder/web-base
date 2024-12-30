<?php

namespace Core\Objects\DatabaseEntity;

use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Unique;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\SSO\SSOProviderOAuth2;
use Core\Objects\SSO\SSOProviderOIDC;
use Core\Objects\SSO\SSOProviderSAML;

abstract class SsoProvider extends DatabaseEntity {

  const PROTOCOLS = [
    "oidc" => SSOProviderOIDC::class,
    "oauth2" => SSOProviderOAuth2::class,
    "saml" => SSOProviderSAML::class,
  ];

  #[MaxLength(64)]
  private string $name;

  #[MaxLength(36)]
  #[Unique]
  private string $identifier;

  private bool $active;

  #[ExtendingEnum(self::PROTOCOLS)]
  private string $protocol;

  protected string $ssoUrl;

  public function __construct(string $protocol, ?int $id = null) {
    parent::__construct($id);
    $this->protocol = $protocol;
  }

  public static function newInstance(\ReflectionClass $reflectionClass, array $row) {
    $type = $row["protocol"] ?? null;
    if ($type === "saml") {
      return (new \ReflectionClass(SSOProviderSAML::class))->newInstanceWithoutConstructor();
    } else if ($type === "oauth2") {
      return (new \ReflectionClass(SSOProviderOAuth2::class))->newInstanceWithoutConstructor();
    } else if ($type === "oidc") {
      return (new \ReflectionClass(SSOProviderOIDC::class))->newInstanceWithoutConstructor();
    } else {
      return parent::newInstance($reflectionClass, $row);
    }
  }

  protected function buildUrl(string $url, array $params): ?string {
    $parts = parse_url($url);
    if ($parts === false || !isset($parts["host"])) {
      return null;
    }

    if (!isset($parts["query"])) {
      $parts["query"] = http_build_query($params);
    } else {
      $parts["query"] .= "&" . http_build_query($params);
    }

    $parts["scheme"] = $parts["scheme"] ?? "https";
    return unparse_url($parts);
  }

  public function getIdentifier(): string {
    return $this->identifier;
  }

  public abstract function login(Context $context, ?string $redirectUrl);
}