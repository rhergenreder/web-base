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
  use Core\API\Traits\Pagination;
  use Core\Driver\Logger\Logger;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondAnd;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Driver\SQL\Condition\CondLike;
  use Core\Driver\SQL\Condition\CondOr;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;
  use Core\Objects\DatabaseEntity\SystemLog;

  // TODO: how to handle pagination here for log entries stored in files?
  class Get extends LogsAPI {

    use Pagination;

    protected array $shownLogLevels;
    protected ?\DateTime $since;

    public function __construct(Context $context, bool $externalCall = false) {
      $params =  self::getPaginationParameters(['id', 'timestamp', "module", "severity"],
        'timestamp', 'desc');
      $params["since"] = new Parameter("since", Parameter::TYPE_DATE_TIME, true);
      $params["severity"] = new StringType("severity", 32, true, "debug", array_values(Logger::LOG_LEVELS));
      $params["query"] = new StringType("query", 64, true, null);
      parent::__construct($context, $externalCall, $params);
      $this->shownLogLevels = Logger::LOG_LEVELS;
      $this->since = null;
    }

    protected function getFilter(): CondIn|CondAnd|bool {
      $this->since = $this->getParam("since");
      $severity = strtolower(trim($this->getParam("severity")));
      $query = $this->getParam("query");

      $logLevel = array_search($severity, Logger::LOG_LEVELS, true);
      if ($logLevel === false) {
        return $this->createError("Invalid severity. Allowed values: " . implode(",", Logger::LOG_LEVELS));
      } else if ($logLevel > 0) {
        $this->shownLogLevels = array_slice(Logger::LOG_LEVELS, $logLevel);
      }

      $condition = new CondIn(new Column("severity"), $this->shownLogLevels);
      if ($this->since !== null) {
        $condition = new CondAnd($condition, new Compare("timestamp", $this->since, ">="));
      }

      if ($query) {
        $condition = new CondAnd($condition, new CondOr(
          new CondLike(new Column("message"), "%$query%"),
          new CondLike(new Column("module"), "%$query%"),
        ));
      }

      return $condition;
    }

    protected function loadFromFileSystem(array &$logs): void {
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
              if ($this->since !== null && datetimeDiff($date, $this->since) > 0) {
                continue;
              }

              // filter log level
              if (!in_array(trim(strtolower($matches[2])), $this->shownLogLevels)) {
                continue;
              }

              $logs[] = [
                "id" => "file-" . ($index++),
                "module" => $matches[1],
                "severity" => $matches[2],
                "message" => $content,
                "timestamp" => $date->format(Parameter::DATE_TIME_FORMAT)
              ];

              $this->result["pagination"]["total"] += 1;
            }
          }
        }
      }
    }

    protected function _execute(): bool {
      $sql = $this->context->getSQL();
      $condition = $this->getFilter();
      if (!$this->initPagination($sql, SystemLog::class, $condition)) {
        return false;
      }

      $query = $this->createPaginationQuery($sql);
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
        $this->result["pagination"]["total"] += 1;
      }

      $this->loadFromFileSystem($this->result["logs"]);
      return true;
    }

    public static function getDescription(): string {
      return "Allows users to fetch system logs";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }
}