<?php

namespace Core\Objects\TwoFactor;

use CBOR\StringStream;

trait CBORDecoder {

  protected function decode(string $buffer): \CBOR\CBORObject {
    $objectManager = new \CBOR\OtherObject\OtherObjectManager();
    $tagManager = new \CBOR\Tag\TagObjectManager();
    $decoder = new \CBOR\Decoder($tagManager, $objectManager);
    return $decoder->decode(new StringStream($buffer));
  }

}