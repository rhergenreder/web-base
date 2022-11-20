<?php

namespace Core\API;

use Core\API\Parameter\StringType;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Group;
use Core\Objects\DatabaseEntity\User;

class Swagger extends Request {

  public function __construct(Context $context, bool $externalCall = false) {
    parent::__construct($context, $externalCall, []);
    $this->csrfTokenRequired = false;
  }

  public function _execute(): bool {
    header("Content-Type: application/x-yaml");
    header("Access-Control-Allow-Origin: *");
    die($this->getDocumentation());
  }

  private function getApiEndpoints(): array {

    // first load all direct classes
    $classes = [];
    $apiDirs = ["Core", "Site"];
    foreach ($apiDirs as $apiDir) {
      $basePath = realpath(WEBROOT . "/$apiDir/Api/");
      if (!$basePath) {
        continue;
      }

      foreach (scandir($basePath) as $fileName) {
        $fullPath = $basePath . "/" . $fileName;
        if (is_file($fullPath) && endsWith($fileName, ".class.php")) {
          require_once $fullPath;
          $apiName = explode(".", $fileName)[0];
          $className = "\\API\\$apiName";
          if (!class_exists($className)) {
            var_dump("Class not exist: $className");
            continue;
          }

          $reflection = new \ReflectionClass($className);
          if (!$reflection->isSubclassOf(Request::class) || $reflection->isAbstract()) {
            continue;
          }

          $endpoint = "/" . strtolower($apiName);
          $classes[$endpoint] = $reflection;
        }
      }
    }

    // then load all inheriting classes
    foreach (get_declared_classes() as $declaredClass) {
      $reflectionClass = new \ReflectionClass($declaredClass);
      if (!$reflectionClass->isAbstract() && $reflectionClass->isSubclassOf(Request::class)) {
        $inheritingClass = $reflectionClass->getParentClass();
        if ($inheritingClass->isAbstract() && endsWith($inheritingClass->getShortName(), "API")) {
          $endpoint = strtolower(substr($inheritingClass->getShortName(), 0, -3));
          $endpoint = "/$endpoint/" . lcfirst($reflectionClass->getShortName());
          $classes[$endpoint] = $reflectionClass;
        }
      }
    }

    return $classes;
  }

  private function fetchPermissions(): array {
    $req = new Permission\Fetch($this->context);
    $this->success = $req->execute();
    $permissions = [];
    foreach( $req->getResult()["permissions"] as $permission) {
      $permissions["/" . strtolower($permission["method"])] = $permission["groups"];
    }

    return $permissions;
  }

  private function canView(array $requiredGroups, Request $request): bool {
    if (!$request->isPublic())  {
      return false;
    }

    $currentUser = $this->context->getUser();
    if (($request->loginRequired() || !empty($requiredGroups)) && !$currentUser) {
      return false;
    }

    // special case: hardcoded permission
    if ($request instanceof Permission\Save && (!$currentUser || !$currentUser->hasGroup(Group::ADMIN))) {
      return false;
    }

    if (!empty($requiredGroups)) {
      $userGroups = array_keys($currentUser?->getGroups() ?? []);
      return !empty(array_intersect($requiredGroups, $userGroups));
    }

    return true;
  }

  private function getDocumentation(): string {

    $settings = $this->context->getSettings();
    $siteName = $settings->getSiteName();
    $domain = parse_url($settings->getBaseUrl(), PHP_URL_HOST);

    $permissions = $this->fetchPermissions();

    $definitions = [];
    $paths = [];
    foreach (self::getApiEndpoints() as $endpoint => $apiClass) {
      $body = null;
      $requiredProperties = [];
      $apiObject = $apiClass->newInstance($this->context, false);
      if (!$this->canView($permissions[strtolower($endpoint)] ?? [], $apiObject)) {
        continue;
      }

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
          }

          if ($body[$param->name]["type"] === "string" && ($format = $param->getSwaggerFormat())) {
            $body[$param->name]["format"] = $format;
          }

          if (!$param->optional) {
            $requiredProperties[] = $param->name;
          }
        }

        $bodyName = $apiClass->getShortName() . "Body";
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
          "produces" => ["application/json"],
          "responses" => [
            "200" => ["description" => ""],
            "401" => ["description" => "Login or 2FA Authorization is required"],
          ]
        ]
      ];

      if ($apiObject->isDisabled()) {
        $endPointDefinition["post"]["deprecated"] = true;
      }

      if ($body) {
        $endPointDefinition["post"]["consumes"] = ["application/json"];
        $endPointDefinition["post"]["parameters"] = [[
          "in" => "body",
          "name" => "body",
          "required" => !empty($requiredProperties),
          "schema" => ["\$ref" => "#/definitions/" . $apiClass->getShortName() . "Body"]
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
      "schemes" => ["https"],
      "paths" => $paths,
      "definitions" => $definitions
    ];

    return \yaml_emit($yamlData);

  }
}