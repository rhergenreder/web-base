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

  private function __construct() {
  }

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

  private static function findSignatureNode(\DOMNode $node) : ?\DOMNode {
    foreach ($node->childNodes as $child) {
      if ($child->nodeName === 'dsig:Signature') {
        return $child;
      }
    }

    return null;
  }

  private static function parseSignatureAlgorithm($name) : ?int {
    return match ($name) {
      'http://www.w3.org/2000/09/xmldsig#sha1' => OPENSSL_ALGO_SHA1,
      'http://www.w3.org/2001/04/xmlenc#sha256' => OPENSSL_ALGO_SHA256,
      'http://www.w3.org/2001/04/xmldsig-more#sha384' => OPENSSL_ALGO_SHA384,
      'http://www.w3.org/2001/04/xmlenc#sha512' => OPENSSL_ALGO_SHA512,
      'http://www.w3.org/2001/04/xmlenc#ripemd160' => OPENSSL_ALGO_RMD160,
      'http://www.w3.org/2001/04/xmldsig-more#md5' => OPENSSL_ALGO_MD5,
        default => throw new \Exception("Unsupported digest algorithm: $name"),
    };
  }

  private static function verifyNodeSignature(SsoProvider $provider, \DOMNode $signatureNode) {
    $signedInfoNode = $signatureNode->getElementsByTagName('SignedInfo')->item(0);
    if (!$signedInfoNode) {
      throw new \Exception("SignedInfo not found in the Signature element.");
    }

    $signedInfo = $signedInfoNode->C14N(true, false);
    $signatureValueNode = $signatureNode->getElementsByTagName('SignatureValue')->item(0);
    if (!$signatureValueNode) {
      throw new \Exception("SignatureValue not found in the Signature element.");
    }

    $digestMethodNode = $signatureNode->getElementsByTagName('DigestMethod')->item(0);
    if (!$digestMethodNode) {
      throw new \Exception("DigestMethod not found in the Signature element.");
    }

    $algorithm = self::parseSignatureAlgorithm($digestMethodNode->getAttribute("Algorithm"));
    $signatureValue = base64_decode($signatureValueNode->nodeValue);
    if (!$provider->validateSignature($signedInfo, $signatureValue, $algorithm)) {
      throw new \Exception("Invalid Signature.");
    }
  }

  public static function parseResponse(Context $context, string $response) : SAMLResponse {
    $sql = $context->getSQL();
    $xml = new DOMDocument();
    $xml->loadXML($response);

    if ($xml->documentElement->nodeName !== "samlp:Response") {
      return self::createError(null, "Invalid root node, expected: 'samlp:Response'");
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
      // $ssoRequest->invalidate($sql);
    }

    try {
      $provider = $ssoRequest->getProvider();
      if (!($provider instanceof SSOProviderSAML)) {
        return self::createError($ssoRequest, "Authentication request was not a SAML request");
      }

      // Validate XML and extract user info
      if (!$xml->getElementsByTagName("Assertion")->length) {
        return self::createError($ssoRequest, "Assertion tag missing");
      }

      $assertion = $xml->getElementsByTagName('Assertion')->item(0);

      //// <-- Signature Validation
      $rootSignature = self::findSignatureNode($xml->documentElement);
      $assertionSignature = self::findSignatureNode($assertion);
      if ($rootSignature === null && $assertionSignature === null) {
        return self::createError($ssoRequest, "Neither a document nor an assertion signature was present.");
      }

      if ($rootSignature !== null) {
        self::verifyNodeSignature($provider, $rootSignature);
      }

      if ($assertionSignature !== null) {
        self::verifyNodeSignature($provider, $assertionSignature);
      }
      //// Signature Validation -->

      // Check status code
      $statusCode = $xml->getElementsByTagName('StatusCode')->item(0);
      if ($statusCode->getAttribute("Value") !== "urn:oasis:names:tc:SAML:2.0:status:Success") {
        return self::createError(null, "StatusCode was not successful");
      }

      $groupMapping = $provider->getGroupMapping();
      $email = $xml->getElementsByTagName('NameID')->item(0)->nodeValue;
      $attributes = [];
      $groupNames = [];
      foreach ($xml->getElementsByTagName('Attribute') as $attribute) {
        $name = $attribute->getAttribute('Name');
        $value = $attribute->getElementsByTagName('AttributeValue')->item(0)->nodeValue;
        if ($name === "Role") {
          if (isset($groupMapping[$value])) {
            $groupNames[] = $groupMapping[$value];
          }
        } else {
          $attributes[$name] = $value;
        }
      }

      $user = User::findBy(User::createBuilder($context->getSQL(), true)
        ->where(new Compare("User.email", $email), new Compare("User.name", $email))
        ->fetchEntities());

      if ($user === false) {
        return self::createError($ssoRequest, "Error fetching user: " . $sql->getLastError());
      } else if ($user === null) {
        $user = $ssoRequest->getProvider()->createUser($context, $email, $groupNames);
      }

      return self::createSuccess($ssoRequest, $user);
    } catch (\Exception $ex) {
      return self::createError($ssoRequest, $ex->getMessage());
    }
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

  public function getProvider(): SSOProvider {
    return $this->request->getProvider();
  }

}