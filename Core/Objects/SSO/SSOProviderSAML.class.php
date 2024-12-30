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
    ], html_tag("saml:Issuer", [], $baseUrl), false);

    $samlRequest = base64_encode(gzdeflate($samlp));
    $samlUrl = $this->buildUrl($this->ssoUrl, [ "SAMLRequest" => $samlRequest ]);

    if ($samlUrl === null) {
      throw new \Exception("SSO Provider has an invalid URL configured");
    }

    $context->router->redirect(302, $samlUrl);
    die();
  }
}