<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Condition\CondIn;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\Json;
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

  #[MaxLength(256)]
  protected string $ssoUrl;

  #[MaxLength(128)]
  protected string $clientId;

  #[Json]
  #[DefaultValue('{}')]
  protected array $groupMapping;

  protected string $certificate;

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

  public function getGroupMapping(): array {
    return $this->groupMapping;
  }

  public function createUser(Context $context, string $email, array $groupNames) : User {
    $sql = $context->getSQL();
    $loggerName = "SSO-" . strtoupper($this->protocol);
    $logger = new Logger($loggerName, $sql);

    if (empty($groupNames)) {
      $groups = [];
    } else {
      $groups = Group::findAll($sql, new CondIn(new Column("name"), $groupNames));
      if ($groups === false) {
        throw new \Exception("Error fetching available groups: " . $sql->getLastError());
      } else if (count($groups) !== count($groupNames)) {
        $availableGroups = array_map(function (Group $group) {
          return $group->getName();
        }, $groups);
        $nonExistentGroups = array_diff($groupNames, $availableGroups);
        $logger->warning("Could not resolve group names: " . implode(', ', $nonExistentGroups));
      }
    }

    // TODO: create a possibility to map attribute values to user properties
    $user = new User();
    $user->email = $email;
    $user->name = $email;
    $user->password = null;
    $user->fullName = "";
    $user->ssoProvider = $this;
    $user->confirmed = true;
    $user->active = true;
    $user->groups = $groups;
    return $user;
  }

  public abstract function login(Context $context, ?string $redirectUrl);
}