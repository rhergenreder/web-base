<?php

namespace Core\Driver\Logger;

use Core\Driver\SQL\SQL;

class Logger {

  public const LOG_FILE_DATE_FORMAT = "Y-m-d_H-i-s_v";
  public const LOG_LEVEL_NONE = -1;
  public const LOG_LEVEL_DEBUG = 0;
  public const LOG_LEVEL_INFO = 1;
  public const LOG_LEVEL_WARNING = 2;
  public const LOG_LEVEL_ERROR = 3;
  public const LOG_LEVEL_SEVERE = 4;

  public const LOG_LEVELS = [
    self::LOG_LEVEL_DEBUG => "debug",
    self::LOG_LEVEL_INFO => "info",
    self::LOG_LEVEL_WARNING => "warning",
    self::LOG_LEVEL_ERROR => "error",
    self::LOG_LEVEL_SEVERE => "severe"
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

  protected function getStackTrace(int $pop = 2, ?array $debugTrace = null): string {
    if ($debugTrace === null) {
      $debugTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

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

  public function log(string|\Throwable $logEntry, string $severity, bool $appendStackTrace = true): void {

    $debugTrace = null;
    $message = $logEntry;
    if ($message instanceof \Throwable) {
      $message = $logEntry->getMessage();
      $debugTrace = $logEntry->getTrace();
    }

    if ($appendStackTrace) {
      $message .= "\n" . $this->getStackTrace(2, $debugTrace);
    }

    $this->lastMessage = $message;
    $this->lastLevel = $severity;
    if ($this->unitTestMode) {
      return;
    }

    if ($severity >= self::LOG_LEVEL_WARNING) {
      error_log($message);
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
    $logFile = implode("_", [$date, $module, $severity]) . ".log";
    $logPath = implode(DIRECTORY_SEPARATOR, [WEBROOT, "Site", "Logs", $logFile]);
    @file_put_contents($logPath, $message);
  }

  public function error(string|\Throwable $message): string {
    $this->log($message, "error");
    return $message;
  }

  public function severe(string|\Throwable $message): string {
    $this->log($message, "severe");
    return $message;
  }

  public function warning(string|\Throwable $message): string {
    $this->log($message, "warning", false);
    return $message;
  }

  public function info(string|\Throwable $message): string {
    $this->log($message, "info", false);
    return $message;
  }

  public function debug(string|\Throwable $message, bool $appendStackTrace = false): string {
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
  public function unitTestMode(): void {
    $this->unitTestMode = true;
  }
}