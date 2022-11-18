<?php

namespace Core\Objects\TwoFactor;

use Cose\Algorithm\Signature\ECDSA\ECSignature;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\TwoFactorToken;

class KeyBasedTwoFactorToken extends TwoFactorToken {

  const TYPE = "fido";

  #[Transient] private ?string $challenge;
  #[Transient] private ?string $credentialId;
  #[Transient] private ?PublicKey $publicKey;

  protected function readData(string $data) {
    if ($this->isConfirmed()) {
      $this->challenge = base64_decode($data);
      $this->credentialId = null;
      $this->publicKey = null;
    } else {
      $jsonData = json_decode($data, true);
      $this->challenge = base64_decode($_SESSION["challenge"] ?? "");
      $this->credentialId = base64_decode($jsonData["credentialID"]);
      $this->publicKey = PublicKey::fromJson($jsonData["publicKey"]);
    }
  }

  public function getData(): string {
    return $this->challenge;
  }

  public function getPublicKey(): ?PublicKey {
    return $this->publicKey;
  }

  public function getCredentialId(): ?string {
    return $this->credentialId;
  }

  public function jsonSerialize(): array {
    $json = parent::jsonSerialize();

    if (!empty($this->challenge) && !$this->isAuthenticated()) {
      $json["challenge"] = base64_encode($this->challenge);
    }

    if (!empty($this->credentialId)) {
      $json["credentialID"] = base64_encode($this->credentialId);
    }

    return $json;
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