<?php

namespace Objects;

class AesStream {

  private string $key;
  private string $iv;
  private $callback;
  private ?string $outputFile;
  private ?string $inputFile;

  public function __construct(string $key, string $iv) {
    $this->key = $key;
    $this->iv  = $iv;
    $this->inputFile = null;
    $this->outputFile = null;
    $this->callback = null;

    if (!in_array(strlen($key), [16, 24, 32])) {
      throw new \Exception("Invalid Key Size");
    } else if (strlen($iv) !== 16) {
      throw new \Exception("Invalid IV Size");
    }
  }

  public function setInputFile(string $file) {
    $this->inputFile = $file;
  }

  public function setOutput(callable $callback) {
    $this->callback = $callback;
  }

  public function setOutputFile(string $file) {
    $this->outputFile = $file;
  }

  public function start(): bool {
    if (!$this->inputFile) {
      return false;
    }

    $blockSize = 16;
    $aesMode   = $this->getCipherMode();

    $outputHandle = null;
    $inputHandle = fopen($this->inputFile, "rb");
    if (!$inputHandle) {
      return false;
    }

    if ($this->outputFile !== null) {
      $outputHandle = fopen($this->outputFile, "wb");
      if (!$outputHandle) {
        fclose($inputHandle);
        return false;
      }
    }

    set_time_limit(0);

    $ivCounter = $this->iv;
    $modulo = \gmp_init("0x1" . str_repeat("00", $blockSize), 16);

    while (!feof($inputHandle)) {
      $chunk = fread($inputHandle, 65536);
      $chunkSize = strlen($chunk);
      if ($chunkSize > 0) {
        $blockCount = intval(ceil($chunkSize / $blockSize));
        $encrypted = openssl_encrypt($chunk, $aesMode, $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $ivCounter);

        $ivNumber = \gmp_init(bin2hex($ivCounter), 16);
        $ivNumber = \gmp_add($ivNumber, $blockCount);
        $ivNumber = \gmp_mod($ivNumber, $modulo);
        $ivNumber = str_pad(\gmp_strval($ivNumber, 16), $blockSize * 2, "0", STR_PAD_LEFT);
        $ivCounter = hex2bin($ivNumber);

        if ($this->callback !== null) {
          call_user_func($this->callback, $encrypted);
        }

        if ($outputHandle !== null) {
          fwrite($outputHandle, $encrypted);
        }
      }
    }

    fclose($inputHandle);
    if ($outputHandle) fclose($outputHandle);
    return true;
  }

  public function getCipherMode(): string {
    $bitStrength = strlen($this->key) * 8;
    return "aes-$bitStrength-ctr";
  }

  public function getKey(): string {
    return $this->key;
  }

  public function getIV(): string {
    return $this->iv;
  }
}