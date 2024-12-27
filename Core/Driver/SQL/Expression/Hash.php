<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;

class Hash extends Expression {

  const SHA_128 = 0;
  const SHA_256 = 1;
  const SHA_512 = 2;

  private int $hashType;
  private mixed $value;

  public function __construct(int $hashType, mixed $value) {
    $this->hashType = $hashType;
    $this->value = $value;
  }

  function getExpression(SQL $sql, array &$params): string {
    if ($sql instanceof MySQL) {
      $val = $sql->addValue($this->value, $params);
      return match ($this->hashType) {
        self::SHA_128 => "SHA2($val, 128)",
        self::SHA_256 => "SHA2($val, 256)",
        self::SHA_512 => "SHA2($val, 512)",
        default => throw new \Exception("HASH() not implemented for hash type: " . $this->hashType),
      };
    } elseif ($sql instanceof PostgreSQL) {
      $val = $sql->addValue($this->value, $params);
      return match ($this->hashType) {
        self::SHA_128 => "digest($val, 'sha128')",
        self::SHA_256 => "digest($val, 'sha256')",
        self::SHA_512 => "digest($val, 'sha512')",
        default => throw new \Exception("HASH() not implemented for hash type: " . $this->hashType),
      };
    } else {
      throw new \Exception("HASH() not implemented for driver type: " . get_class($sql));
    }
  }
}