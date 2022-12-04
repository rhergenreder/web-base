<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class LogsAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }
  }

}

namespace Core\API\Logs {

  use Core\API\LogsAPI;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\Driver\Logger\Logger;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\SystemLog;

  class Get extends LogsAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "since" => new Parameter("since", Parameter::TYPE_DATE_TIME, true),
        "severity" => new StringType("severity", 32, true, "debug")
      ]);
      $this->csrfTokenRequired = false;
    }

    protected function _execute(): bool {
      $since = $this->getParam("since");
      $sql = $this->context->getSQL();
      $severity = strtolower(trim($this->getParam("severity")));
      $shownLogLevels = Logger::LOG_LEVELS;

      $logLevel = array_search($severity, Logger::LOG_LEVELS, true);
      if ($logLevel === false) {
        return $this->createError("Invalid severity. Allowed values: " . implode(",", Logger::LOG_LEVELS));
      } else if ($logLevel > 0) {
        $shownLogLevels = array_slice(Logger::LOG_LEVELS, $logLevel);
      }


      $query = SystemLog::createBuilder($sql, false)
        ->orderBy("timestamp")
        ->descending();

      if ($since !== null) {
        $query->where(new Compare("timestamp", $since, ">="));
      }

      if ($logLevel > 0) {
        $query->where(new CondIn(new Column("severity"), $shownLogLevels));
      }

      $logEntries = SystemLog::findBy($query);
      $this->success = $logEntries !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["logs"] = [];
        foreach ($logEntries as $logEntry) {
          $this->result["logs"][] = $logEntry->jsonSerialize();
        }
      } else {
        // we couldn't fetch logs from database, return a message and proceed to log files
        $this->result["logs"] = [
          [
            "id" => "fetch-fail",
            "module" => "LogsAPI",
            "message" => "Failed retrieving logs from database: " . $this->lastError,
            "severity" => "error",
            "timestamp" => (new \DateTime())->format(Parameter::DATE_TIME_FORMAT)
          ]
        ];
      }

      // get all log entries from filesystem (if database failed)
      $logPath = realpath(implode(DIRECTORY_SEPARATOR, [WEBROOT, "Site", "Logs"]));
      if ($logPath) {
        $index = 1;
        foreach (scandir($logPath) as $fileName) {
          $logFile = $logPath . DIRECTORY_SEPARATOR . $fileName;
          // {module}_{severity}_{date}_{time}_{ms}.log
          if (preg_match("/^(\w+)_(\w+)_((\d+-\d+-\d+_){2}\d+)\.log$/", $fileName, $matches) && is_file($logFile)) {
            $content = @file_get_contents($logFile);
            $date = \DateTime::createFromFormat(Logger::LOG_FILE_DATE_FORMAT, $matches[3]);
            if ($content && $date) {

              // filter log date
              if ($since !== null && datetimeDiff($date, $since) < 0) {
                continue;
              }

              // filter log level
              if (!in_array(trim(strtolower($matches[2])), $shownLogLevels)) {
                continue;
              }

              $this->result["logs"][] = [
                "id" => "file-" . ($index++),
                "module" => $matches[1],
                "severity" => $matches[2],
                "message" => $content,
                "timestamp" => $date->format(Parameter::DATE_TIME_FORMAT)
              ];
            }
          }
        }
      }

      return true;
    }
  }

}