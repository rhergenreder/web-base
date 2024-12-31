<?php

namespace Core\Objects\SSO;

use Core\Objects\Context;
use Core\Objects\DatabaseEntity\SsoProvider;
use Core\Objects\DatabaseEntity\SsoRequest;

class SSOProviderSAML extends SSOProvider {

  const TYPE = "saml";

  public function __construct(?int $id = null) {
    parent::__construct(self::TYPE, $id);
  }

  public function login(Context $context, ?string $redirectUrl) {

    $sql = $context->getSQL();
    $settings = $context->getSettings();
    $baseUrl = $settings->getBaseUrl();
    $ssoRequest = SsoRequest::create($sql, $this, $redirectUrl);
    if (!$ssoRequest) {
      throw new \Exception("Could not save SSO request: " . $sql->getLastError());
    }

    $acsUrl = $baseUrl . "/api/sso/saml";
    $samlp = html_tag_ex("samlp:AuthnRequest", [
      "xmlns:samlp" => "urn:oasis:names:tc:SAML:2.0:protocol",
      "xmlns:saml" => "urn:oasis:names:tc:SAML:2.0:assertion",
      "ID" => $ssoRequest->getIdentifier(),
      "Version" => "2.0",
      "IssueInstant" => gmdate('Y-m-d\TH:i:s\Z'),
      "Destination" => $this->ssoUrl,
      "ProtocolBinding" => "urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST",
      "AssertionConsumerServiceURL" => $acsUrl
    ], html_tag("saml:Issuer", [], $this->clientId), false);

    $samlRequest = base64_encode($samlp);
    $req = new \Core\API\Template\Render($context);
    $success = $req->execute([
      "file" => "sso.twig",
      "parameters" => [
        "sso" => [
          "url" => $this->ssoUrl,
          "data" => [
            "SAMLRequest" => $samlRequest
          ]
        ]
      ]
    ]);

    if (!$success) {
      throw new \Exception("Could not redirect: " . $req->getLastError());
    }

    die($req->getResult()["html"]);
  }

  public function validateSignature(string $what, string $signature, int $algorithm) : bool {
    $publicKey = openssl_pkey_get_public($this->certificate);
    if (!$publicKey) {
      throw new \Exception("Failed to load certificate: " . openssl_error_string());
    }

    $result = openssl_verify($what, $signature, $publicKey, $algorithm);
    if ($result === 1) {
      return true;
    } else if ($result === 0) {
      return false;
    } else {
      throw new \Exception("Failed to validate signature: " . openssl_error_string());
    }
  }
}