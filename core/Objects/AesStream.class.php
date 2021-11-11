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

  public function setInput($file) {
    $this->inputFile = $file;
  }

  public function setOutput($callback) {
    $this->callback = $callback;
  }

  public function setOutputFile(string $file) {
    $this->outputFile = $file;
  }

  private function add(string $a, int $b): string {
    // counter $b is n = PHP_INT_SIZE bytes large
    $b_arr = pack('I', $b);
    $b_size = strlen($b_arr);
    $a_size = strlen($a);

    $prefix = "";
    if ($a_size > $b_size) {
      $prefix = substr($a, 0, $a_size - $b_size);
    }

    // xor last n bytes of $a with $b
    $xor = substr($a, strlen($prefix), $b_size);
    if (strlen($xor) !== strlen($b_arr)) {
      var_dump($xor);
      var_dump($b_arr);
      die();
    }
    $xor = $this->xor($xor, $b_arr);
    return $prefix . $xor;
  }

  private function xor(string $a, string $b): string {
    $arr_a = str_split($a);
    $arr_b = str_split($b);
    if (strlen($a) !== strlen($b)) {
      var_dump($a);
      var_dump($b);
      var_dump(range(0, strlen($a) - 1));
      die();
    }

    return implode("", array_map(function($i) use ($arr_a, $arr_b) {
      return chr(ord($arr_a[$i]) ^ ord($arr_b[$i]));
    }, range(0, strlen($a) - 1)));
  }

  public function start(): bool {
    if (!$this->inputFile) {
      return false;
    }

    $blockSize = 16;
    $bitStrength = strlen($this->key) * 8;
    $aesMode   = "AES-$bitStrength-ECB";

    $outputHandle = null;
    $inputHandle = fopen($this->inputFile, "rb");
    if (!$inputHandle) {
      return false;
    }

    if ($this->outputFile !== null) {
      $outputHandle = fopen($this->outputFile, "wb");
      if (!$outputHandle) {
        return false;
      }
    }

    $counter = 0;
    while (!feof($inputHandle)) {
      $chunk = fread($inputHandle, 4096);
      $chunkSize = strlen($chunk);
      for ($offset = 0; $offset < $chunkSize; $offset += $blockSize) {
        $block = substr($chunk, $offset, $blockSize);
        if (strlen($block) !== $blockSize) {
          $padding = ($blockSize - strlen($block));
          $block .= str_repeat(chr($padding), $padding);
        }

        $ivCounter = $this->add($this->iv, $counter + 1);
        $encrypted = substr(openssl_encrypt($ivCounter, $aesMode, $this->key, OPENSSL_RAW_DATA), 0, $blockSize);
        $encrypted = $this->xor($encrypted, $block);
        if (is_callable($this->callback)) {
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
}