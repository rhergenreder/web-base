<?php

namespace External\ZipStream {

  use HashContext;
  use Objects\AesStream;

  class FileStream extends File {

    private AesStream $stream;
    private HashContext $crc32ctx;
    private HashContext $sha256ctx;

    public function __construct(AesStream $stream, string $name) {
      parent::__construct($name);
      $this->stream = $stream;
      $this->crc32ctx = hash_init('crc32b');
      $this->sha256ctx = hash_init('sha256');
    }

    public function getStream(): AesStream {
      return $this->stream;
    }

    public function finalize() {
      $this->crc32 = hash_final($this->crc32ctx, true);
      $this->sha256 = hash_final($this->sha256ctx);
      return $this->compress(null);
    }

    public function processChunk($chunk) {

      hash_update($this->crc32ctx, $chunk);
      hash_update($this->sha256ctx, $chunk);
      $this->fileSize += strlen($chunk);

      if ($this->useCompression) {
        $chunk = $this->compress($chunk);
      }

      return $chunk;
    }

  }

}