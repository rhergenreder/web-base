<?php

namespace Core\Objects\TwoFactor;

use Core\Objects\ApiObject;

class AuthenticationData extends ApiObject {

  private string $rpIDHash;
  private int $flags;
  private int $counter;
  private string $aaguid;
  private string $credentialID;
  private PublicKey $publicKey;

  public function __construct(string $buffer) {

    if (strlen($buffer) < 32 + 1 + 4) {
      throw new \Exception("Invalid authentication data buffer size");
    }

    $offset = 0;
    $this->rpIDHash = substr($buffer, $offset, 32); $offset += 32;
    $this->flags = ord($buffer[$offset]); $offset += 1;
    $this->counter = unpack("N", $buffer, $offset)[1]; $offset += 4;

    if (strlen($buffer) >= $offset + 4 + 2) {
      $this->aaguid = substr($buffer, $offset, 16); $offset += 16;
      $credentialIdLength = unpack("n", $buffer, $offset)[1]; $offset += 2;
      $this->credentialID = substr($buffer, $offset, $credentialIdLength); $offset += $credentialIdLength;

      $credentialData = substr($buffer, $offset);
      $this->publicKey = new PublicKey($credentialData);
    }
  }

  public function jsonSerialize(): array {
    return [
      "rpIDHash" => base64_encode($this->rpIDHash),
      "flags" => $this->flags,
      "counter" => $this->counter,
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
    return boolval($this->flags & (1 << 0));
  }

  public function isUserVerified(): bool {
		return boolval($this->flags & (1 << 2));
	}

  public function attestedCredentialData(): bool {
		return boolval($this->flags & (1 << 6));
	}

  public function hasExtensionData(): bool {
		return boolval($this->flags & (1 << 7));
	}

  public function getPublicKey(): PublicKey {
    return $this->publicKey;
  }

  public function getCredentialID() {
    return $this->credentialID;
  }
}