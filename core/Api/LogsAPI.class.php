<?php

namespace Api {

  use Objects\User;

  abstract class LogsAPI extends Request {
    public function __construct(User $user, bool $externalCall = false, array $params = array()) {
      parent::__construct($user, $externalCall, $params);
    }
  }

}

namespace Api\Logs {

  use Api\LogsAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\Logger\Logger;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Objects\User;

  class Get extends LogsAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "since" => new Parameter("since", Parameter::TYPE_DATE_TIME, true),
        "severity" => new StringType("severity", 32, true, "debug")
      ]);
    }

    protected function _execute(): bool {
      $since = $this->getParam("since");
      $sql = $this->user->getSQL();
      $severity = strtolower(trim($this->getParam("severity")));
      $shownLogLevels = Logger::LOG_LEVELS;

      $logLevel = array_search($severity, Logger::LOG_LEVELS, true);
      if ($logLevel === false) {
        return $this->createError("Invalid severity. Allowed values: " . implode(",", Logger::LOG_LEVELS));
      } else if ($logLevel > 0) {
        $shownLogLevels = array_slice(Logger::LOG_LEVELS, $logLevel);
      }

      $query = $sql->select("id", "module", "message", "severity", "timestamp")
        ->from("SystemLog")
        ->orderBy("timestamp")
        ->descending();

      if ($since !== null) {
        $query->where(new Compare("timestamp", $since, ">="));
      }

      if ($logLevel > 0) {
        $query->where(new CondIn(new Column("severity"), $shownLogLevels));
      }

      $res = $query->execute();
      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["logs"] = $res;
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
      $logPath = realpath(implode(DIRECTORY_SEPARATOR, [WEBROOT, "core", "Logs"]));
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