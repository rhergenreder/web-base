<?php

namespace Core\API\Traits;

use Core\Objects\DatabaseEntity\GpgKey;

trait GpgKeyValidation {

  function testKey(string $keyString, ?string $expectedType = "pub") {
    $res = GpgKey::getKeyInfo($keyString);
    if (!$res["success"]) {
      return $this->createError($res["error"] ?? $res["msg"]);
    }

    $keyData = $res["data"];
    $keyType = $keyData["type"];
    $expires = $keyData["expires"];

    if ($expectedType === "pub" && $keyType === "sec#") {
      return $this->createError("ATTENTION! It seems like you've imported a PGP PRIVATE KEY instead of a public key. 
            It is recommended to immediately revoke your private key and create a new key pair.");
    } else if ($expectedType !== null && $keyType !== $expectedType) {
      return $this->createError("Key has unexpected type: $keyType, expected: $expectedType");
    } else if (isInPast($expires)) {
      return $this->createError("It seems like the gpg key is already expired.");
    } else {
      return $keyData;
    }
  }

  function formatKey(string $keyString): string {
     return preg_replace("/(-{2,})\n([^\n])/", "$1\n\n$2", $keyString);
  }
}