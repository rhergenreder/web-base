<?php

namespace Core\Objects\SSO;

use Core\Driver\SQL\Condition\Compare;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\SsoProvider;
use Core\Objects\DatabaseEntity\User;
use DOMDocument;

class SSOProviderSAML extends SSOProvider {

  const TYPE = "saml";

  public function __construct(?int $id = null) {
    parent::__construct(self::TYPE, $id);
  }

  public function login(Context $context, ?string $redirectUrl) {

    $settings = $context->getSettings();
    $baseUrl = $settings->getBaseUrl();
    $params = ["provider" => $this->getIdentifier()];

    if (!empty($redirectUrl)) {
      $params["redirect"] = $redirectUrl;
    }

    $acsUrl = $baseUrl . "/api/sso/saml?" . http_build_query($params);
    $samlp = html_tag_ex("samlp:AuthnRequest", [
      "xmlns:samlp" => "urn:oasis:names:tc:SAML:2.0:protocol",
      "xmlns:saml" => "urn:oasis:names:tc:SAML:2.0:assertion",
      "ID" => "_" . uniqid(),
      "Version" => "2.0",
      "IssueInstant" => gmdate('Y-m-d\TH:i:s\Z'),
      "Destination" => $this->ssoUrl,
      "ProtocolBinding" => "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST",
      "AssertionConsumerServiceURL" => $acsUrl
    ], html_tag("saml:Issuer", [], $baseUrl), false);

    $samlRequest = base64_encode(gzdeflate($samlp));
    $samlUrl = $this->buildUrl($this->ssoUrl, [ "SAMLRequest" => $samlRequest ]);

    if ($samlUrl === null) {
      throw new \Exception("SSO Provider has an invalid URL configured");
    }

    $context->router->redirect(302, $samlUrl);
    die();
  }

  public function parseResponse(Context $context, string $response): ?User {
    $xml = new DOMDocument();
    $xml->loadXML($response);

    // Validate XML and extract user info
    if (!$xml->getElementsByTagName("Assertion")->length) {
      return null;
    }


    $assertion = $xml->getElementsByTagName('Assertion')->item(0);
    if (!$assertion->getElementsByTagName("Signature")->length) {
      return null;
    }

    $signature = $assertion->getElementsByTagName("Signature")->item(0);
    // TODO: parse and validate signature

    $statusCode = $xml->getElementsByTagName('StatusCode')->item(0);
    if ($statusCode->getAttribute("Value") !== "urn:oasis:names:tc:SAML:2.0:status:Success") {
      return null;
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
      return null;
    } else if ($user === null) {
      $user = new User();
      $user->fullName = $fullName;
      $user->email = $email;
      $user->name = $username;
      $user->password = null;
      $user->ssoProvider = $this;
      $user->confirmed = true;
      $user->active = true;
      $user->groups = []; // TODO: create a possibility to set auto-groups for SSO registered users
    }

    return $user;
  }
}