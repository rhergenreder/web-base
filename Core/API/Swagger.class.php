<?php

namespace Core\API;

use Core\API\Parameter\StringType;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Group;

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

    // special case: hardcoded permission
    if ($request instanceof \Core\API\Permission\Save && (!$isLoggedIn || !$currentUser->hasGroup(Group::ADMIN))) {
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
    $protocol = getProtocol();

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
      "schemes" => ["$protocol"],
      "paths" => $paths,
      "definitions" => $definitions
    ];

    return \yaml_emit($yamlData);

  }
}