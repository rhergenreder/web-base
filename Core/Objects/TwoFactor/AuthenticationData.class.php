<?php

namespace Core\Objects\TwoFactor;

use Core\Objects\ApiObject;

class AuthenticationData extends ApiObject {

  use CBORDecoder;

  const FLAG_USER_PRESENT = 1;
  const FLAG_USER_VERIFIED = 4;
  const FLAG_ATTESTED_DATA_INCLUDED = 64;
  const FLAG_EXTENSION_DATA_INCLUDED = 128;

  private string $rpIDHash;
  private int $flags;
  private int $signCount;
  private string $aaguid;
  private string $credentialID;
  private array $extensions;
  private PublicKey $publicKey;

  public function __construct(string $buffer) {

    $bufferLength = strlen($buffer);
    if ($bufferLength < 32 + 1 + 4) {
      throw new \Exception("Invalid authentication data buffer size");
    }

    $offset = 0;
    $this->rpIDHash = substr($buffer, $offset, 32); $offset += 32;
    $this->flags = ord(substr($buffer, $offset, 1)); $offset += 1;
    $this->signCount = unpack("N", substr($buffer, $offset, 4))[1]; $offset += 4;

    if ($this->attestedCredentialData()) {
      $this->aaguid = substr($buffer, $offset, 16); $offset += 16;
      $credentialIdLength = unpack("n",  substr($buffer, $offset, 4))[1]; $offset += 2;
      $this->credentialID = substr($buffer, $offset, $credentialIdLength); $offset += $credentialIdLength;

      if ($bufferLength > $offset) {
        $publicKeyData = $this->decode(substr($buffer, $offset));
        var_dump($publicKeyData);
        $this->publicKey = new PublicKey($publicKeyData);
        // TODO: we should add $publicKeyData->length to $offset, but it's not implemented yet?;
      }
    }

    if ($this->hasExtensionData()) {
      // not supported yet
    }
  }

  public function jsonSerialize(): array {
    return [
      "rpIDHash" => base64_encode($this->rpIDHash),
      "flags" => $this->flags,
      "signCount" => $this->signCount,
      "aaguid" => base64_encode($this->aaguid),
      "credentialID" => base64_encode($this->credentialID),
      "publicKey" => $this->publicKey->jsonSerialize()
    ];
  }

  public function getHash(): string {
    return $this->rpIDHash;
  }

  public function verifyIntegrity(string $rp): bool {
    return $this->rpIDHash === hash("sha256", $rp, true);
  }

  public function isUserPresent(): bool {
    return boolval($this->flags & self::FLAG_USER_PRESENT);
  }

  public function isUserVerified(): bool {
		return boolval($this->flags & self::FLAG_USER_VERIFIED);
	}

  public function attestedCredentialData(): bool {
		return boolval($this->flags & self::FLAG_ATTESTED_DATA_INCLUDED);
	}

  public function hasExtensionData(): bool {
		return boolval($this->flags & self::FLAG_EXTENSION_DATA_INCLUDED);
	}

  public function getPublicKey(): PublicKey {
    return $this->publicKey;
  }

  public function getCredentialID(): string {
    return $this->credentialID;
  }
}