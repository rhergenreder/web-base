<?php

namespace Core\Objects;

class AesStream {

  private string $key;
  private string $iv;
  private $callback;
  private ?string $outputFile;
  private ?string $inputFile;
  private int $offset;
  private ?int $length;

  //
  private ?string $md5SumIn;
  private ?string $sha1SumIn;
  private ?string $md5SumOut;
  private ?string $sha1SumOut;

  public function __construct(string $key, string $iv) {
    $this->key = $key;
    $this->iv  = $iv;
    $this->inputFile = null;
    $this->outputFile = null;
    $this->callback = null;
    $this->offset = 0;
    $this->length = null;
    $this->md5SumIn = null;
    $this->sha1SumIn = null;
    $this->md5SumOut = null;
    $this->sha1SumOut = null;

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
    $md5ContextIn = hash_init("md5");
    $sha1ContextIn = hash_init("sha1");
    $md5ContextOut = hash_init("md5");
    $sha1ContextOut = hash_init("sha1");

    $ivCounter = $this->iv;
    $modulo = \gmp_init("0x1" . str_repeat("00", $blockSize), 16);
    $written = 0;

    while (!feof($inputHandle)) {
      $chunk = fread($inputHandle, 65536);
      $chunkSize = strlen($chunk);
      if ($chunkSize > 0) {

        hash_update($md5ContextIn, $chunk);
        hash_update($sha1ContextIn, $chunk);

        $blockCount = intval(ceil($chunkSize / $blockSize));
        $encrypted = openssl_encrypt($chunk, $aesMode, $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $ivCounter);

        $ivNumber = \gmp_init(bin2hex($ivCounter), 16);
        $ivNumber = \gmp_add($ivNumber, $blockCount);
        $ivNumber = \gmp_mod($ivNumber, $modulo);
        $ivNumber = str_pad(\gmp_strval($ivNumber, 16), $blockSize * 2, "0", STR_PAD_LEFT);
        $ivCounter = hex2bin($ivNumber);

        // partial content
        $skip = false;
        if ($this->offset > 0 && $written < $this->offset) {
          if ($written + $chunkSize >= $this->offset) {
            $encrypted = substr($encrypted, $this->offset - $written);
          } else {
            $skip = true;
          }
        }

        if ($this->length !== null) {
          $notSkipped = max($written - $this->offset, 0);
          if ($notSkipped + $chunkSize >= $this->length) {
            $encrypted = substr($encrypted, 0, $this->length - $notSkipped);
          }
        }

        if (!$skip) {
          if ($this->callback !== null) {
            call_user_func($this->callback, $encrypted);
          }

          if ($outputHandle !== null) {
            fwrite($outputHandle, $encrypted);
          }

          hash_update($md5ContextOut, $encrypted);
          hash_update($sha1ContextOut, $encrypted);
        }

        $written += $chunkSize;
        if ($this->length !== null && $written - $this->offset >= $this->length) {
          break;
        }
      }
    }

    fclose($inputHandle);
    if ($outputHandle) {
      fclose($outputHandle);
    }

    $this->md5SumIn = hash_final($md5ContextIn, false);
    $this->sha1SumIn = hash_final($sha1ContextIn, false);
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

  public function setRange(int $offset, int $length) {
    $this->offset = $offset;
    $this->length = $length;
  }

  public function getMD5SumIn(): ?string {
    return $this->md5SumIn;
  }

  public function getSHA1SumIn(): ?string {
    return $this->sha1SumIn;
  }

  public function getMD5SumOut(): ?string {
    return $this->md5SumOut;
  }

  public function getSHA1SumOut(): ?string {
    return $this->sha1SumOut;
  }
}