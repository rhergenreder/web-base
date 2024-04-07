<?php

namespace Core\Objects\TwoFactor;

use CBOR\MapObject;
use Core\Objects\ApiObject;

class PublicKey extends ApiObject {

  private int $keyType;
  private int $usedAlgorithm;
  private int $curveType;
  private string $xCoordinate;
  private string $yCoordinate;

  public function __construct(?MapObject $publicKeyData = null) {
    if ($publicKeyData) {
      $this->keyType = $publicKeyData["1"]->getValue();
      $this->usedAlgorithm = $publicKeyData["3"]->getValue();
      $this->curveType = $publicKeyData["-1"]->getValue();
      $this->xCoordinate = $publicKeyData["-2"]->getValue();
      $this->yCoordinate = $publicKeyData["-3"]->getValue();
    }
  }

  public static function fromJson($jsonData): PublicKey {
    $publicKey = new PublicKey(null);
    $publicKey->keyType = $jsonData["keyType"];
    $publicKey->usedAlgorithm = $jsonData["usedAlgorithm"];
    $publicKey->curveType = $jsonData["curveType"];
    $publicKey->xCoordinate = base64_decode($jsonData["coordinates"]["x"]);
    $publicKey->yCoordinate = base64_decode($jsonData["coordinates"]["y"]);
    return $publicKey;
  }

  public function getUsedAlgorithm(): int {
    return $this->usedAlgorithm;
  }

  public function jsonSerialize(): array {
    return [
      "keyType" => $this->keyType,
      "usedAlgorithm" => $this->usedAlgorithm,
      "curveType" => $this->curveType,
      "coordinates" => [
        "x" => base64_encode($this->xCoordinate),
        "y" => base64_encode($this->yCoordinate)
      ],
    ];
  }

  public function getNormalizedData(): array {
    return [
      "1"  => $this->keyType,
      "3"  => $this->usedAlgorithm,
      "-1" => $this->curveType,
      "-2" => $this->xCoordinate,
      "-3" => $this->yCoordinate,
    ];
  }

  public function getU2F(): string {
    return bin2hex("\x04" . $this->xCoordinate . $this->yCoordinate);
  }
}