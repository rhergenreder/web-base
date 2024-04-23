<?php

namespace Core\API;

use Core\API\Parameter\IntegerType;
use Core\API\Parameter\RegexType;
use Core\API\Parameter\StringType;
use Core\Objects\Context;

class Swagger extends Request {

  public function __construct(Context $context, bool $externalCall = false) {
    parent::__construct($context, $externalCall, []);
    $this->csrfTokenRequired = false;
  }

  protected function getCORS(): array {
    return ["*"];
  }

  public function _execute(): bool {
    header("Content-Type: application/x-yaml");
    die($this->getDocumentation());
  }

  private function fetchPermissions(): array {
    $req = new \Core\API\Permission\Fetch($this->context);
    $this->success = $req->execute();
    $permissions = [];
    foreach ($req->getResult()["permissions"] as $permission) {
      $permissions["/" . strtolower($permission["method"])] = $permission["groups"];
    }

    return $permissions;
  }

  private function canView(array $requiredGroups, Request $request): bool {
    if (!$request->isPublic())  {
      return false;
    }

    $currentUser = $this->context->getUser();
    $isLoggedIn = $currentUser !== null;
    if (($request->loginRequired() || !empty($requiredGroups)) && !$isLoggedIn) {
      return false;
    }

    if (!empty($requiredGroups)) {
      $userGroups = array_keys($currentUser?->getGroups() ?? []);
      return !empty(array_intersect($requiredGroups, $userGroups));
    }

    return true;
  }

  private function getBodyName(\ReflectionClass $class): string {
    $bodyName = $class->getShortName() . "Body";
    $namespace = explode("\\", $class->getNamespaceName());
    if (count($namespace) > 2) { // Core\API\XYZ or Site\API\XYZ
      $bodyName = $namespace[2] . $bodyName;
    }

    return $bodyName;
  }

  private function getDocumentation(): string {

    $settings = $this->context->getSettings();
    $siteName = $settings->getSiteName();
    $domain = parse_url($settings->getBaseUrl(), PHP_URL_HOST);
    $protocol = getProtocol();

    $permissions = $this->fetchPermissions();

    $definitions = [];
    $paths = [];
    $tags = [];

    // TODO: consumes and produces is not always the same, but it's okay for now
    foreach (self::getApiEndpoints() as $endpoint => $apiClass) {
      $body = null;
      $requiredProperties = [];
      $endpoint = "/$endpoint";
      $apiObject = $apiClass->newInstance($this->context, false);
      if (!$this->canView($permissions[strtolower($endpoint)] ?? [], $apiObject)) {
        continue;
      }

      $tag = null;
      if ($apiClass->getParentClass()->getName() !== Request::class) {
        $parentClass = $apiClass->getParentClass()->getShortName();
        if (endsWith($parentClass, "API")) {
          $tag = substr($parentClass, 0, strlen($parentClass) - 3);
          if (!in_array($tag, $tags)) {
            $tags[] = $tag;
          }
        }
      }

      $bodyName = $this->getBodyName($apiClass);
      $parameters = $apiObject->getDefaultParams();
      if (!empty($parameters)) {
        $body = [];
        foreach ($apiObject->getDefaultParams() as $param) {
          $body[$param->name] = [
            "type" => $param->getSwaggerTypeName(),
            "default" => $param->value
          ];

          if ($param instanceof StringType && $param->maxLength > 0) {
            $body[$param->name]["maxLength"] = $param->maxLength;
          } else if ($param instanceof IntegerType) {
            if ($param->minValue > PHP_INT_MIN) {
              $body[$param->name]["minimum"] = $param->minValue;
            }
            if ($param->maxValue < PHP_INT_MAX) {
              $body[$param->name]["maximum"] = $param->maxValue;
            }
          }

          if ($param instanceof RegexType) {
            $body[$param->name]["pattern"] = $param->pattern;
          }

          if ($body[$param->name]["type"] === "string" && ($format = $param->getSwaggerFormat())) {
            $body[$param->name]["format"] = $format;
          }

          if (!$param->optional) {
            $requiredProperties[] = $param->name;
          }
        }

        $definitions[$bodyName] = [
          "description" => "Body for $endpoint",
          "properties" => $body
        ];

        if (!empty($requiredProperties)) {
          $definitions[$bodyName]["required"] = $requiredProperties;
        }
      }

      $endPointDefinition = [
        "post" => [
          "tags" => [$tag ?? "Global"],
          "summary" => $apiObject->getDescription(),
          "produces" => ["application/json"],
          "responses" => [
            "200" => ["description" => "OK!"],
            "400" => ["description" => "Parameter validation failed"],
            "401" => ["description" => "Login or 2FA Authorization is required"],
            "403" => ["description" => "CSRF-Token validation failed or insufficient permissions"],
            "503" => ["description" => "Function is disabled"],
          ]
        ]
      ];

      if ($apiObject->isDisabled()) {
        $endPointDefinition["post"]["deprecated"] = true;
      }

      if ($body) {
        $endPointDefinition["post"]["consumes"] = ["application/json", "application/x-www-form-urlencoded"];
        $endPointDefinition["post"]["parameters"] = [[
          "in" => "body",
          "name" => "body",
          "required" => !empty($requiredProperties),
          "schema" => ["\$ref" => "#/definitions/$bodyName"]
        ]];
      } else if ($apiObject->isMethodAllowed("GET")) {
        $endPointDefinition["get"] = $endPointDefinition["post"];
        unset($endPointDefinition["post"]);
      }

      $paths[$endpoint] = $endPointDefinition;
    }

    $yamlData = [
      "swagger" => "2.0",
      "info" => [
        "description" => "This is the Backend API-Description of $siteName",
        "version" => WEBBASE_VERSION,
        "title" => $siteName,
        "contact" => [ "email" => "webmaster@$domain" ],
      ],
      "host" => $domain,
      "basePath" => "/api",
      "schemes" => ["$protocol"],
      "tags" => $tags,
      "paths" => $paths,
      "definitions" => $definitions
    ];

    return \yaml_emit($yamlData);
  }

  public static function getDescription(): string {
    return "Returns the API-specification for this site. Endpoints, a user does not have access to, are hidden by default.";
  }

  public static function getDefaultPermittedGroups(): array {
    return [];
  }
}