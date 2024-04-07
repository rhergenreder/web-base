<?php

namespace Core\Objects\TwoFactor;

use CBOR\Decoder;
use CBOR\StringStream;

trait CBORDecoder {

  protected function decode(string $buffer): \CBOR\CBORObject {
    $decoder = Decoder::create();
    return $decoder->decode(new StringStream($buffer));
  }

}