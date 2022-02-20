<?php

use Base32\Base32;
use Configuration\Configuration;
use Objects\TwoFactor\TimeBasedTwoFactorToken;
use Objects\User;

class TimeBasedTwoFactorTokenTest extends PHPUnit\Framework\TestCase {

  // https://tools.ietf.org/html/rfc6238
  public function testTOTP() {
    $secret = Base32::encode("12345678901234567890");
    $token = new TimeBasedTwoFactorToken($secret);

    $totp_tests = [
      59 => '94287082',
      1111111109 => '07081804',
      1111111111 => '14050471',
      1234567890 => '89005924',
      2000000000 => '69279037',
      20000000000 => '65353130',
    ];

    $period = 30;
    $totp_length = 8;
    foreach ($totp_tests as $seed => $code) {
      $generated = $token->generate($seed, $totp_length, $period);
      $this->assertEquals($code, $generated, "$code != $generated, at=$seed");
    }
  }

  public function testURL() {
    $secret = Base32::encode("12345678901234567890");
    $configuration = new Configuration();
    $user = new User($configuration);

    $token = new TimeBasedTwoFactorToken($secret);
    $siteName = $configuration->getSettings()->getSiteName();
    $username = $user->getUsername();
    $url = $token->getUrl($user);
    $this->assertEquals("otpauth://totp/$username?secret=$secret&issuer=$siteName", $url);
  }
}