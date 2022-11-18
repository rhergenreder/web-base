<?php

namespace Core\Driver\Logger;

use Core\Driver\SQL\SQL;

class Logger {

  public const LOG_FILE_DATE_FORMAT = "Y-m-d_H-i-s_v";
  public const LOG_LEVELS = [
    0 => "debug",
    1 => "info",
    2 => "warning",
    3 => "error",
    4 => "severe"
  ];

  public static Logger $INSTANCE;

  private ?SQL $sql;
  private string $module;

  // unit tests
  private bool $unitTestMode;
  private ?string $lastMessage;
  private ?string $lastLevel;

  public function __construct(string $module = "Unknown", ?SQL $sql = null) {
    $this->module = $module;
    $this->sql = $sql;
    $this->unitTestMode = false;
    $this->lastMessage = null;
    $this->lastLevel = null;
  }

  protected function getStackTrace(int $pop = 2): string {
    $debugTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    if ($pop > 0) {
      array_splice($debugTrace, 0, $pop);
    }
    return implode("\n", array_map(function ($trace) {
      if (isset($trace["file"])) {
        return $trace["file"] . "#" . $trace["line"] . ": " . $trace["function"] . "()";
      } else {
        return $trace["function"] . "()";
      }
    }, $debugTrace));
  }

  public function log(string $message, string $severity, bool $appendStackTrace = true) {

    if ($appendStackTrace) {
      $message .= "\n" . $this->getStackTrace();
    }

    $this->lastMessage = $message;
    $this->lastLevel = $severity;
    if ($this->unitTestMode) {
      return;
    }

    if ($this->sql !== null && $this->sql->isConnected()) {
      $success = $this->sql->insert("SystemLog", ["module", "message", "severity"])
        ->addRow($this->module, $message, $severity)
        ->execute();
      if ($success !== false) {
        return;
      }
    }

    // database logging failed, try to log to file
    $module = preg_replace("/[^a-zA-Z0-9-]/", "-", $this->module);
    $date = (\DateTime::createFromFormat('U.u', microtime(true)))->format(self::LOG_FILE_DATE_FORMAT);
    $logFile = implode("_", [$module, $severity, $date]) . ".log";
    $logPath = implode(DIRECTORY_SEPARATOR, [WEBROOT, "core", "Logs", $logFile]);
    @file_put_contents($logPath, $message);
  }

  public function error(string $message): string {
    $this->log($message, "error");
    return $message;
  }

  public function severe(string $message): string {
    $this->log($message, "severe");
    return $message;
  }

  public function warning(string $message): string {
    $this->log($message, "warning", false);
    return $message;
  }

  public function info(string $message): string {
    $this->log($message, "info", false);
    return $message;
  }

  public function debug(string $message, bool $appendStackTrace = false): string {
    $this->log($message, "debug", $appendStackTrace);
    return $message;
  }

  public static function instance(): Logger {
    if (self::$INSTANCE === null) {
      self::$INSTANCE = new Logger("Global");
    }

    return self::$INSTANCE;
  }

  public function getLastMessage(): ?string {
    return $this->lastMessage;
  }

  public function getLastLevel(): ?string {
    return $this->lastLevel;
  }

  /**
   * Calling this method will prevent the logger from persisting log messages (writing to database/file),
   */
  public function unitTestMode() {
    $this->unitTestMode = true;
  }
}