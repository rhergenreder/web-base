<?php

namespace Core\Objects\SSO;

use Core\Driver\SQL\Condition\Compare;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\SsoProvider;
use Core\Objects\DatabaseEntity\SsoRequest;
use Core\Objects\DatabaseEntity\User;
use DOMDocument;

class SAMLResponse {

  private bool $success;
  private string $error;
  private ?User $user;
  private ?SsoRequest $request;

  private static function createSuccess(SsoRequest $request, User $user) : SAMLResponse {
    $response = new SAMLResponse();
    $response->user = $user;
    $response->request = $request;
    $response->success = true;
    return $response;
  }

  private static function createError(?SsoRequest $request, string $error) : SAMLResponse {
    $response = new SAMLResponse();
    $response->error = $error;
    $response->request = $request;
    $response->success = false;
    return $response;
  }

  public static function parseResponse(Context $context, string $response) : SAMLResponse {
    $sql = $context->getSQL();
    $xml = new DOMDocument();
    $xml->loadXML($response);

    if ($xml->documentElement->nodeName !== "samlp:Response") {
      return self::createError(null, "Invalid root node");
    }

    $requestId = $xml->documentElement->getAttribute("InResponseTo");
    if (empty($requestId)) {
      return self::createError(null, "Root node missing attribute 'InResponseTo'");
    }

    $ssoRequest = SsoRequest::findBy(SsoRequest::createBuilder($sql, true)
      ->whereEq("SsoRequest.identifier", $requestId)
      ->fetchEntities()
    );

    if ($ssoRequest === false) {
      return self::createError(null, "Error fetching SSO provider: " . $sql->getLastError());
    } else if ($ssoRequest === null) {
      return self::createError(null, "Request not found");
    } else if ($ssoRequest->wasUsed()) {
      return self::createError($ssoRequest, "SAMLResponse already processed");
    } else if (!$ssoRequest->isValid()) {
      return self::createError($ssoRequest, "Authentication request expired");
    } else {
      $ssoRequest->invalidate($sql);
    }

    $provider = $ssoRequest->getProvider();
    if (!($provider instanceof SSOProviderSAML)) {
      return self::createError(null, "Authentication request was not a SAML request");
    }

    // Validate XML and extract user info
    if (!$xml->getElementsByTagName("Assertion")->length) {
      return self::createError(null, "Assertion tag missing");
    }


    $assertion = $xml->getElementsByTagName('Assertion')->item(0);
    if (!$assertion->getElementsByTagName("Signature")->length) {
      return self::createError(null, "Signature tag missing");
    }

    $signature = $assertion->getElementsByTagName("Signature")->item(0);
    // TODO: parse and validate signature

    $statusCode = $xml->getElementsByTagName('StatusCode')->item(0);
    if ($statusCode->getAttribute("Value") !== "urn:oasis:names:tc:SAML:2.0:status:Success") {
      return self::createError(null, "StatusCode was not successful");
    }

    $issuer = $xml->getElementsByTagName('Issuer')->item(0)->nodeValue;
    // TODO: validate issuer

    $username = $xml->getElementsByTagName('NameID')->item(0)->nodeValue;
    $attributes = [];
    foreach ($xml->getElementsByTagName('Attribute') as $attribute) {
      $name = $attribute->getAttribute('Name');
      $value = $attribute->getElementsByTagName('AttributeValue')->item(0)->nodeValue;
      $attributes[$name] = $value;
    }

    $email = $attributes["email"];
    $fullName = [];

    if (isset($attributes["firstName"])) {
      $fullName[] = $attributes["firstName"];
    }

    if (isset($attributes["lastName"])) {
      $fullName[] = $attributes["lastName"];
    }

    $fullName = implode(" ", $fullName);
    $user = User::findBy(User::createBuilder($context->getSQL(), true)
      ->where(new Compare("email", $email), new Compare("name", $username)));

    if ($user === false) {
      return self::createError($ssoRequest, "Error fetching user: " . $sql->getLastError());
    } else if ($user === null) {
      $user = new User();
      $user->fullName = $fullName;
      $user->email = $email;
      $user->name = $username;
      $user->password = null;
      $user->ssoProvider = $ssoRequest->getProvider();
      $user->confirmed = true;
      $user->active = true;
      $user->groups = []; // TODO: create a possibility to set auto-groups for SSO registered users
    }

    return self::createSuccess($ssoRequest, $user);
  }

  public function wasSuccessful() : bool {
    return $this->success;
  }

  public function getError() : string {
    return $this->error;
  }

  public function getUser() : User {
    return $this->user;
  }

  public function getRedirectURL() : ?string {
    return $this->request->getRedirectUrl();
  }

}