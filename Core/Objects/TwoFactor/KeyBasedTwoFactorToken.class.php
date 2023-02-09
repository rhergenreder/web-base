<?php

namespace Core\Objects\TwoFactor;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Cose\Algorithm\Signature\ECDSA\ECSignature;
use Core\Objects\DatabaseEntity\TwoFactorToken;
use Cose\Key\Key;

class KeyBasedTwoFactorToken extends TwoFactorToken {

  const TYPE = "fido";

  #[Transient]
  private ?string $challenge;

  #[Transient]
  private ?string $credentialID;

  #[Transient]
  private ?PublicKey $publicKey;

  private function __construct() {
    parent::__construct(self::TYPE);
  }

  public function generateChallenge(int $length = 32) {
    $this->challenge = base64_encode(generateRandomString($length, "raw"));
    $_SESSION["challenge"] = $this->challenge;
  }

  public static function create(int $challengeLength = 32): KeyBasedTwoFactorToken {
    $token = new KeyBasedTwoFactorToken();
    $token->generateChallenge($challengeLength);
    return $token;
  }

  public function hasChallenge(): bool {
    return isset($this->challenge);
  }

  public function getChallenge(): string {
    return $this->challenge;
  }

  protected function readData(string $data) {
    if (!$this->isConfirmed()) {
      $this->challenge = $data;
      $this->credentialID = null;
      $this->publicKey = null;
    } else {
      $jsonData = json_decode($data, true);
      $this->challenge = $_SESSION["challenge"] ?? "";
      $this->credentialID = base64_decode($jsonData["credentialID"]);
      $this->publicKey = PublicKey::fromJson($jsonData["publicKey"]);
    }
  }

  public function getData(): string {
    if (!$this->isConfirmed()) {
      return $this->challenge;
    } else {
      return json_encode([
        "credentialID" => $this->credentialID,
        "publicKey" => $this->publicKey->jsonSerialize()
      ]);
    }
  }

  public function confirmKeyBased(SQL $sql, string $credentialID, PublicKey $publicKey): bool {
    $this->credentialID = $credentialID;
    $this->publicKey = $publicKey;
    return parent::confirm($sql);
  }

  public function getPublicKey(): ?PublicKey {
    return $this->publicKey;
  }

  public function getCredentialId(): ?string {
    return $this->credentialID;
  }

  public function jsonSerialize(?array $propertyNames = null): array {
    $jsonData = parent::jsonSerialize();

    if (!$this->isAuthenticated()) {
      if (!empty($this->challenge) && ($propertyNames === null || in_array("challenge", $propertyNames))) {
        $jsonData["challenge"] = $this->challenge;
      }

      if (!empty($this->credentialID) && ($propertyNames === null || in_array("credentialID", $propertyNames))) {
        $jsonData["credentialID"] = base64_encode($this->credentialID);
      }
    }

    return $jsonData;
  }

  // TODO: algorithms, hardcoded values, ...
  public function verify(string $signature, string $data): bool {
    switch ($this->publicKey->getUsedAlgorithm()) {
      case -7: // EC2

        if (strlen($signature) !== 64) {
          $signature = \Cose\Algorithm\Signature\ECDSA\ECSignature::fromAsn1($signature, 64);
        }

        $coseKey = new \Cose\Key\Key($this->publicKey->getNormalizedData());
        $ec2key = new \Cose\Key\Ec2Key($coseKey->getData());
        $publicKey = $ec2key->toPublic();
        $signature = ECSignature::toAsn1($signature, 64);
        return openssl_verify($data, $signature, $publicKey->asPEM(), "sha256") === 1;
      default:
        // Not implemented :(
        return false;
    }
  }
}