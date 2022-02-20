<?php

namespace Objects\TwoFactor;

use Objects\ApiObject;

class PublicKey extends ApiObject {

  use \Objects\TwoFactor\CBORDecoder;

  private int $keyType;
  private int $usedAlgorithm;
  private int $curveType;
  private string $xCoordinate;
  private string $yCoordinate;

  public function __construct(?string $cborData = null) {
    if ($cborData) {
      $data = $this->decode($cborData)->getNormalizedData();
      $this->keyType = $data["1"];
      $this->usedAlgorithm = $data["3"];
      $this->curveType = $data["-1"];
      $this->xCoordinate = $data["-2"];
      $this->yCoordinate = $data["-3"];
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
      "1" => $this->keyType,
      "3" => $this->usedAlgorithm,
      "-1" => $this->curveType,
      "-2" => $this->xCoordinate,
      "-3" => $this->yCoordinate,
    ];
  }

  public function getU2F(): string {
    return bin2hex("\x04" . $this->xCoordinate . $this->yCoordinate);
  }
}