<?php

use Core\Objects\AesStream;

class AesStreamTest extends PHPUnit\Framework\TestCase {

  static string $TEMP_FILE;

  public static function setUpBeforeClass(): void {
    AesStreamTest::$TEMP_FILE = tempnam(sys_get_temp_dir(), 'aesTest');
  }

  public static function tearDownAfterClass(): void {
    unlink(AesStreamTest::$TEMP_FILE);
  }

  public function testConstructorInvalidKey1() {
    $this->expectExceptionMessage("Invalid Key Size");
    $this->expectException(\Exception::class);
    new AesStream("", "");
  }

  public function testConstructorInvalidKey2() {
    $this->expectExceptionMessage("Invalid Key Size");
    $this->expectException(\Exception::class);
    new AesStream(str_repeat("A",15), "");
  }

  public function testConstructorInvalidKey3() {
    $this->expectExceptionMessage("Invalid Key Size");
    $this->expectException(\Exception::class);
    new AesStream(str_repeat("A",33), "");
  }

  public function testConstructorInvalidIV1() {
    $this->expectExceptionMessage("Invalid IV Size");
    $this->expectException(\Exception::class);
    new AesStream(str_repeat("A",32), str_repeat("B", 17));
  }

  public function testConstructorValid() {
    $key = str_repeat("A",32);
    $iv = str_repeat("B", 16);
    $aesStream = new AesStream($key, $iv);
    $this->assertInstanceOf(AesStream::class, $aesStream);
    $this->assertEquals($key, $aesStream->getKey());
    $this->assertEquals($iv, $aesStream->getIV());
    $this->assertEquals("aes-256-ctr", $aesStream->getCipherMode());
  }

  private function getOutput(string $chunk, string &$data) {
    $data .= $chunk;
  }

  public function testEncrypt() {
    $key = str_repeat("A", 32);
    $iv  = str_repeat("B", 16);
    $aesStream = new AesStream($key, $iv);

    $data = [
      "43"   => "8c",   # small block test 1 (1 byte)
      "abcd" => "6424", # small block test 2 (2 byte)
      "a37c599429cfdefde6546ad6d7082a" => "6c9539264abc8cae39308dbc86e768",     # small block test 3 (15 byte)
      "43b3504077482bd9bf8c3c08ad3c937f" => "8c5a30f2143b798a60e8db62fcd3d1f7", # one block (16 byte)
      "9b241a3d7e9f03f6e66a8fa0cba3221008eda86f465e3fbfb0f3a4d3527cffb7"
        => "54cd7a8f1dec51a5390e68ca9a4c60986aaafadd42b6960a09deedfa7f2cf1c3"   # two blocks (16 byte)
    ];

    foreach ($data as $pt => $ct) {
      $output = "";
      file_put_contents(AesStreamTest::$TEMP_FILE, hex2bin($pt));
      $aesStream->setInputFile(AesStreamTest::$TEMP_FILE);
      $aesStream->setOutput(function($chunk) use (&$output) { $this->getOutput($chunk, $output); });
      $aesStream->start();
      $this->assertEquals($ct, bin2hex($output), $ct . " != " . bin2hex($output));
    }
  }

  private function openssl(AesStream $aesStream) {
    // check if openssl util produce the same output
    $cmd = ["/usr/bin/openssl", $aesStream->getCipherMode(), "-K", bin2hex($aesStream->getKey()), "-iv", bin2hex($aesStream->getIV()), "-in", AesStreamTest::$TEMP_FILE];
    $proc = proc_open($cmd, [1 => ["pipe", "w"]], $pipes);
    $this->assertTrue(is_resource($proc));
    $this->assertTrue(is_resource($pipes[1]));
    $output = stream_get_contents($pipes[1]);
    proc_close($proc);
    return $output;
  }

  private function testEncryptDecrypt($key, $iv, $inputData) {
    $aesStream = new AesStream($key, $iv);
    $inputSize = strlen($inputData);
    file_put_contents(AesStreamTest::$TEMP_FILE, $inputData);

    $output = "";
    $aesStream->setInputFile(AesStreamTest::$TEMP_FILE);
    $aesStream->setOutput(function($chunk) use (&$output) { $this->getOutput($chunk, $output); });
    $aesStream->start();

    $this->assertEquals($inputSize, strlen($output));
    $this->assertNotEquals($inputData, $output);

    // check if openssl util produce the same output
    $this->assertEquals($this->openssl($aesStream), $output);

    file_put_contents(AesStreamTest::$TEMP_FILE, $output);
    $output = "";
    $aesStream->setInputFile(AesStreamTest::$TEMP_FILE);
    $aesStream->setOutput(function($chunk) use (&$output) { $this->getOutput($chunk, $output); });
    $aesStream->start();
    $this->assertEquals($inputData, $output);

    // check if openssl util produce the same output
    $this->assertEquals($this->openssl($aesStream), $output);
  }

  public function testEncryptDecryptRandom() {
    $chunkSize = 65536;
    $key = random_bytes(32);
    $iv  = random_bytes(16);
    $inputSize = 10 * $chunkSize;
    $inputData = random_bytes($inputSize);
    $this->testEncryptDecrypt($key, $iv, $inputData);
  }

  public function testEncryptDecryptLargeIV() {
    $chunkSize = 65536;
    $key = random_bytes(32);
    $iv  = hex2bin(str_repeat("FF", 16));
    $inputSize = 10 * $chunkSize;
    $inputData = random_bytes($inputSize);
    $this->testEncryptDecrypt($key, $iv, $inputData);
  }

  public function testEncryptDecryptZeroIV() {
    $chunkSize = 65536;
    $key = random_bytes(32);
    $iv  = hex2bin(str_repeat("00", 16));
    $inputSize = 10 * $chunkSize;
    $inputData = random_bytes($inputSize);
    $this->testEncryptDecrypt($key, $iv, $inputData);
  }

  public function testEncryptDecryptPartial() {
    $key = random_bytes(32);
    $iv  = hex2bin(str_repeat("00", 16));
    $chunkSize = 65536;

    $ranges = [[500,100,200],[10*$chunkSize,100,5*$chunkSize+100],[10*$chunkSize,0,10*$chunkSize],[10*$chunkSize,$chunkSize-1,3*$chunkSize-1]];
    foreach ($ranges as $range) {
      list ($total, $offset, $length) = $range;
      $inputData = random_bytes($total);
      file_put_contents(AesStreamTest::$TEMP_FILE, $inputData);

      $output = "";
      $aesStream = new AesStream($key, $iv);
      $aesStream->setRange($offset, $length);
      $aesStream->setInputFile(AesStreamTest::$TEMP_FILE);
      $aesStream->setOutput(function($chunk) use (&$output) { $this->getOutput($chunk, $output); });
      $aesStream->start();

      $outputComplete = "";
      $aesStream = new AesStream($key, $iv);
      $aesStream->setInputFile(AesStreamTest::$TEMP_FILE);
      $aesStream->setOutput(function($chunk) use (&$outputComplete) { $this->getOutput($chunk, $outputComplete); });
      $aesStream->start();

      $this->assertEquals($length, strlen($output), "total=$total offset=$offset length=$length");
      $this->assertEquals(substr($outputComplete, $offset, $length), $output, "total=$total offset=$offset length=$length");
    }
  }
}