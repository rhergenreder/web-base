<?php

namespace Core\Objects\TwoFactor;

use Base32\Base32;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\TwoFactorToken;

class TimeBasedTwoFactorToken extends TwoFactorToken {

  const TYPE = "totp";
  private string $secret;

  public function __construct(string $secret) {
    parent::__construct(self::TYPE);
    $this->secret = $secret;
  }

  protected function readData(string $data) {
    $this->secret = $data;
  }

  public function getUrl(Context $context): string {
    $otpType = self::TYPE;
    $name = rawurlencode($context->getUser()->getUsername());
    $settings = $context->getSettings();
    $urlArgs = [
      "secret" => $this->secret,
      "issuer" => $settings->getSiteName(),
    ];

    $urlArgs = http_build_query($urlArgs);
    return "otpauth://$otpType/$name?$urlArgs";
  }

  public function generateQRCode(Context $context) {
    $options = new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, "imageBase64" => false]);
    $qrcode = new QRCode($options);
    return $qrcode->render($this->getUrl($context));
  }

  public function generate(?int $at = null, int $length = 6, int $period = 30): string {
    if ($at === null) {
      $at = time();
    }

    $seed = intval($at / $period);
    $secret =  Base32::decode($this->secret);
    $hmac = hash_hmac('sha1', pack("J", $seed), $secret, true);
    $offset = ord($hmac[-1]) & 0xF;
    $code = (unpack("N", substr($hmac, $offset, 4))[1] & 0x7fffffff) % intval(pow(10, $length));
    return substr(str_pad(strval($code), $length, "0", STR_PAD_LEFT), -1 * $length);
  }

  public function verify(string $code): bool {
    return $this->generate() === $code;
  }

  public function getData(): string {
    return $this->secret;
  }
}