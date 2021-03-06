<?php

namespace Objects;

class GpgKey extends ApiObject {

  const GPG2 = "/usr/bin/gpg2";

  private int $id;
  private bool $confirmed;
  private string $fingerprint;
  private string $algorithm;
  private \DateTime $expires;

  public function __construct(int $id, bool $confirmed, string $fingerprint, string $algorithm, string $expires) {
    $this->id = $id;
    $this->confirmed = $confirmed;
    $this->fingerprint = $fingerprint;
    $this->algorithm = $algorithm;
    $this->expires = new \DateTime($expires);
  }

  public static function encrypt(string $body, string $gpgFingerprint): array {
    $gpgFingerprint = escapeshellarg($gpgFingerprint);
    $cmd = self::GPG2 . " --encrypt --output - --recipient $gpgFingerprint --trust-model always --batch --armor";
    list($out, $err) = self::proc_exec($cmd, $body, true);
    if ($out === null) {
      return self::createError("Error while communicating with GPG agent");
    } else if ($err) {
      return self::createError($err);
    } else {
      return ["success" => true, "data" => $out];
    }
  }

  public function jsonSerialize(): array {
    return array(
      "fingerprint" => $this->fingerprint,
      "algorithm" => $this->algorithm,
      "expires" => $this->expires->getTimestamp(),
      "confirmed" => $this->confirmed
    );
  }

  private static function proc_exec(string $cmd, ?string $stdin = null, bool $raw = false): ?array {
    $descriptorSpec = array(0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]);
    $process = proc_open($cmd, $descriptorSpec,$pipes);
    if (!is_resource($process)) {
      return null;
    }

    if ($stdin) {
      fwrite($pipes[0], $stdin);
      fclose($pipes[0]);
    }

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return [($raw ? $out : trim($out)), $err];
  }

  private static function createError(string $error) : array {
    return ["success" => false, "error" => $error];
  }

  public static function getKeyInfo(string $key): array {
    list($out, $err) = self::proc_exec(self::GPG2 . " --show-key", $key);
    if ($out === null) {
      return self::createError("Error while communicating with GPG agent");
    }

    if ($err) {
      return self::createError($err);
    }

    $lines = explode("\n", $out);
    if (count($lines) > 4) {
      return self::createError("It seems like you have uploaded more than one GPG-Key");
    } else if (count($lines) !== 4 || !preg_match("/(\S+)\s+(\w+)\s+.*\[expires: ([0-9-]+)]/", $lines[0], $matches)) {
      return self::createError("Error parsing GPG output");
    }

    $keyType = $matches[1];
    $keyAlg  = $matches[2];
    $expires = \DateTime::createFromFormat("Y-m-d", $matches[3]);
    $fingerprint = trim($lines[1]);
    $keyData = ["type" => $keyType, "algorithm" => $keyAlg, "expires" => $expires, "fingerprint" => $fingerprint];
    return ["success" => true, "data" => $keyData];
  }

  public static function importKey(string $key): array {
    list($out, $err) = self::proc_exec(self::GPG2 . " --import", $key);
    if ($out === null) {
      return self::createError("Error while communicating with GPG agent");
    }

    if (preg_match("/gpg:\s+Total number processed:\s+(\d+)/", $err, $matches) && intval($matches[1]) > 0) {
      if ((preg_match("/.*\s+unchanged:\s+(\d+)/", $err, $matches) && intval($matches[1]) > 0) ||
          (preg_match("/.*\s+imported:\s+(\d+)/", $err, $matches) && intval($matches[1]) > 0)) {
        return ["success" => true];
      }
    }

    return self::createError($err);
  }

  public static function export($gpgFingerprint, bool $armored): array {
    $cmd = self::GPG2 . " --export ";
    if ($armored) {
      $cmd .= "--armor ";
    }
    $cmd .= escapeshellarg($gpgFingerprint);
    list($out, $err) = self::proc_exec($cmd);
    if ($err) {
      return self::createError($err);
    }

    return ["success" => true, "data" => $out];
  }

  public function isConfirmed(): bool {
    return $this->confirmed;
  }

  public function getId(): int {
    return $this->id;
  }

  public function getFingerprint(): string {
    return $this->fingerprint;
  }

}