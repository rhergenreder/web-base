<?php

namespace Core\Objects\TwoFactor;

use CBOR\MapObject;
use Core\Objects\ApiObject;

class AttestationObject extends ApiObject {

  use CBORDecoder;

  private string $format;
  private MapObject $statement;
  private AuthenticationData $authData;

  public function __construct(string $buffer) {
    $data = $this->decode($buffer);
    $this->format = $data["fmt"];
    $this->statement = $data["attStmt"];
    $this->authData = new AuthenticationData($data["authData"]->getValue());
  }

  public function jsonSerialize(): array {
    return [
      "format" => $this->format,
      "statement" => [
        "sig" => base64_encode($this->statement["sig"] ?? ""),
        "x5c" => base64_encode(($this->statement["x5c"] ?? [""])[0]),
      ],
      "authData" => $this->authData->jsonSerialize()
    ];
  }

  public function getAuthData(): AuthenticationData {
    return $this->authData;
  }

}