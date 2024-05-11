<?php

namespace Core\Documents {

  use Documents\Install\InstallBody;
  use Documents\Install\InstallHead;
  use Core\Elements\HtmlDocument;
  use Core\Objects\Router\Router;

  class Install extends HtmlDocument {
    public function __construct(Router $router) {
      parent::__construct($router, InstallHead::class, InstallBody::class);
      $this->databaseRequired = false;
    }
  }
}

namespace Documents\Install {

  use Core\Configuration\Configuration;
  use Core\Configuration\CreateDatabase;
  use Core\Driver\SQL\Expression\Count;
  use Core\Driver\SQL\SQL;
  use Core\Elements\Body;
  use Core\Elements\Head;
  use Core\Elements\Link;
  use Core\Elements\Script;
  use Core\External\PHPMailer\Exception;
  use Core\External\PHPMailer\PHPMailer;
  use Core\Objects\ConnectionData;
  use Core\Objects\DatabaseEntity\Group;
  use Core\Objects\DatabaseEntity\User;

  class InstallHead extends Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      $this->loadJQuery();
      $this->loadBootstrap();
      $this->loadFontawesome();
      $this->addJS(Script::CORE);
      $this->addCSS(Link::CORE);
      $this->addJS(Script::INSTALL);
    }

    protected function initMetas(): array {
      return [
        ['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0'],
        ['name' => 'format-detection', 'content' => 'telephone=yes'],
        ['charset' => 'utf-8'],
        ["http-equiv" => 'expires', 'content' => '0'],
        ["name" => 'robots', 'content' => 'noarchive'],
      ];
    }

    protected function initRawFields(): array {
      return [];
    }

    protected function initTitle(): string {
      return "WebBase - Installation";
    }

  }

  class InstallBody extends Body {

    // Status enum
    const NOT_STARTED = 0;
    const PENDING = 1;
    const SUCCESSFUL = 2;
    const ERROR = 3;

    // Step enum
    const CHECKING_REQUIREMENTS = 1;
    const INSTALL_DEPENDENCIES = 2;
    const DATABASE_CONFIGURATION = 3;
    const CREATE_USER = 4;
    const ADD_MAIL_SERVICE = 5;
    const FINISH_INSTALLATION = 6;

    //
    private string $errorString;
    private int $currentStep;
    private array $steps;

    function __construct($document) {
      parent::__construct($document);
      $this->errorString = "";
      $this->currentStep = InstallBody::CHECKING_REQUIREMENTS;
      $this->steps = [];
    }

    private function getParameter($name): ?string {
      if (isset($_REQUEST[$name]) && is_string($_REQUEST[$name])) {
        return trim($_REQUEST[$name]);
      }

      return NULL;
    }

    private function yarnInstall(string $reactDir): array {
      $fds = [
        "1" => ["pipe", "w"],
        "2" => ["pipe", "w"],
      ];
      $proc = proc_open("yarn install --frozen-lockfile --non-interactive", $fds, $pipes, $reactDir);
      $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
      $status = proc_close($proc);
      return [$status, $output];
    }

    private function yarnBuild(string $reactDir): array {
      $fds = [
        "1" => ["pipe", "w"],
        "2" => ["pipe", "w"],
      ];
      $proc = proc_open("yarn run build", $fds, $pipes, $reactDir);
      $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
      $status = proc_close($proc);
      return [$status, $output];
    }

    private function composerInstall(bool $dryRun = false): array {
      $command = "composer install";
      if ($dryRun) {
        $command .= " --dry-run";
      }

      $fds = [
        "1" => ["pipe", "w"],
        "2" => ["pipe", "w"],
      ];

      $dir = $this->getExternalDirectory();
      $env = null;
      if (!getenv("HOME")) {
        $env = ["COMPOSER_HOME" => $dir];
      }

      $proc = proc_open($command, $fds, $pipes, $dir, $env);
      $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
      $status = proc_close($proc);
      return [$status, $output];
    }

    private function getExternalDirectory(bool $absolute = true): string {
      if ($absolute) {
        return implode(DIRECTORY_SEPARATOR, [WEBROOT, "Core", "External"]);
      } else {
        return implode(DIRECTORY_SEPARATOR, ["Core", "External"]);
      }
    }

    private function getCurrentStep(): int {

      if (!$this->checkRequirements()["success"]) {
        return self::CHECKING_REQUIREMENTS;
      }

      // TODO: also check the presence of react dist?
      $externalDir = $this->getExternalDirectory();
      $autoload = implode(DIRECTORY_SEPARATOR, [$externalDir, "vendor", "autoload.php"]);
      if (!is_file($autoload)) {
        return self::INSTALL_DEPENDENCIES;
      } else {
        list ($status, $output) = $this->composerInstall(true);
        if ($status !== 0) {
          $this->errorString = "Error executing 'composer install --dry-run'. Please verify that the command succeeds locally and then try again. Status Code: $status, Output: $output";
          return self::CHECKING_REQUIREMENTS;
        } else {
          if (!contains($output, "Nothing to install, update or remove")) {
            return self::INSTALL_DEPENDENCIES;
          }
        }
      }

      $context = $this->getDocument()->getContext();
      $config = $context->getConfig();

      // Check if database configuration exists
      if (!$config->getDatabase()) {
        return self::DATABASE_CONFIGURATION;
      }

      $sql = $context->getSQL();
      if (!$sql || !$sql->isConnected()) {
        return self::DATABASE_CONFIGURATION;
      }

      $userCount = User::count($sql);
      if ($userCount === FALSE) {
        return self::DATABASE_CONFIGURATION;
      } else {
        if ($userCount > 0) {
          $step = self::ADD_MAIL_SERVICE;
        } else {
          return self::CREATE_USER;
        }
      }

      if ($step === self::ADD_MAIL_SERVICE) {
        $req = new \Core\API\Settings\Get($context);
        $success = $req->execute(["key" => "^mail_enabled$"]);
        if (!$success) {
          $this->errorString = $req->getLastError();
          return self::DATABASE_CONFIGURATION;
        } else if (isset($req->getResult()["settings"]["mail_enabled"])) {
          $step = self::FINISH_INSTALLATION;

          $req = new \Core\API\Settings\Set($context);
          $success = $req->execute(["settings" => ["installation_completed" => true]]);
          if (!$success) {
            $this->errorString = $req->getLastError();
          }
        }
      }

      return $step;
    }

    private function command_exist(string $cmd): bool {
      $return = shell_exec(sprintf("which %s 2>/dev/null", escapeshellarg($cmd)));
      return !empty($return);
    }

    private function checkRequirements(): array {

      $msg = $this->errorString;
      $success = true;
      $failedRequirements = [];

      $requiredDirectories = [
        "/Site/Cache",
        "/Site/Logs",
        "/Site/Configuration",
        "/Core/External/vendor",
        "/files/uploaded",
        "/react",
      ];

      $nonWritableDirectories = [];
      foreach ($requiredDirectories as $directory) {
        if (!is_writeable(WEBROOT . $directory)) {
          $nonWritableDirectories[] = $directory;
        }
      }

      if (!empty($nonWritableDirectories)) {
        $currentUser = getCurrentUsername();
        if (function_exists("posix_getuid")) {
          $currentUser .= " (uid: " . posix_getuid() . ")";
        }

        $failedRequirements[] = "One or more directories are not writable. " .
          "Make sure the current user $currentUser has write-access to following locations:" .
          $this->createUnorderedList($nonWritableDirectories);

        $success = false;
      }

      if (!class_exists("Redis")) {
        $failedRequirements[] = "<b>redis</b> extension is not installed.";
        $success = false;
      }

      if (!function_exists("yaml_emit")) {
        $failedRequirements[] = "<b>YAML</b> extension is not installed.";
        $success = false;
      }

      $requiredVersion = "8.2";
      if (version_compare(PHP_VERSION, $requiredVersion, '<')) {
        $failedRequirements[] = "PHP Version <b>>= $requiredVersion</b> is required. Got: <b>" . PHP_VERSION . "</b>";
        $success = false;
      }

      if (!$this->command_exist("composer")) {
        $failedRequirements[] = "<b>Composer</b> is not installed or cannot be found.";
        $success = false;
      }

      if (!$this->command_exist("yarn")) {
        $failedRequirements[] = "<b>Yarn</b> is not installed or cannot be found.";
        $success = false;
      }

      if (!$success) {
        $msg = "The following requirements failed the check:<br>" .
          $this->createUnorderedList($failedRequirements);
        $this->errorString = $msg;
      }

      return ["success" => $success, "msg" => $msg];
    }

    private function installDependencies(): array {
      list ($status, $output) = $this->composerInstall();
      if ($status === 0) {
        $reactDir = implode(DIRECTORY_SEPARATOR, [WEBROOT, "react"]);
        list ($status, $output) = $this->yarnInstall($reactDir);
        if ($status === 0) {
          list ($status, $output) = $this->yarnBuild($reactDir);
        }
      }

      return ["success" => $status === 0, "msg" => $output];
    }

    private function databaseConfiguration(): array {

      $host = $this->getParameter("host");
      $port = $this->getParameter("port");
      $username = $this->getParameter("username");
      $password = $this->getParameter("password");
      $database = $this->getParameter("database");
      $type = $this->getParameter("type");
      $encoding = $this->getParameter("encoding") ?? "UTF8";
      $success = true;

      $missingInputs = [];
      if (empty($host)) {
        $success = false;
        $missingInputs[] = "Host";
      }

      if (empty($port)) {
        $success = false;
        $missingInputs[] = "Port";
      }

      if (empty($username)) {
        $success = false;
        $missingInputs[] = "Username";
      }

      if (is_null($password)) {
        $success = false;
        $missingInputs[] = "Password";
      }

      if (empty($database)) {
        $success = false;
        $missingInputs[] = "Database";
      }

      if (empty($type)) {
        $success = false;
        $missingInputs[] = "Type";
      }

      $supportedTypes = ["mysql", "postgres"];
      if (!$success) {
        $msg = "Please fill out the following inputs:<br>" .
          $this->createUnorderedList($missingInputs);
      } else if (!is_numeric($port) || ($port = intval($port)) < 1 || $port > 65535) {
        $msg = "Port must be in range of 1-65535.";
        $success = false;
      } else if (!in_array($type, $supportedTypes)) {
        $msg = "Unsupported database type. Must be one of: " . implode(", ", $supportedTypes);
        $success = false;
      } else {
        $connectionData = new ConnectionData($host, $port, $username, $password);
        $connectionData->setProperty('database', $database);
        $connectionData->setProperty('encoding', $encoding);
        $connectionData->setProperty('type', $type);
        $connectionData->setProperty('isDocker', isDocker());
        $sql = SQL::createConnection($connectionData);
        $success = false;
        if (is_string($sql)) {
          $msg = "Error connecting to database: $sql";
        } else if (!$sql->isConnected()) {
          if (!$sql->checkRequirements()) {
            $driverName = $sql->getDriverName();
            $installLink = "https://www.php.net/manual/en/$driverName.setup.php";
            $link = $this->createExternalLink($installLink);
            $msg = "$driverName is not enabled yet. See: $link";
          } else {
            $msg = "Error connecting to database:<br>" . $sql->getLastError();
          }
        } else {

          $msg = "";
          $success = true;
          $queries = CreateDatabase::createQueries($sql);
          try {
            $sql->startTransaction();
            foreach ($queries as $query) {
              if (!$query->execute()) {
                $msg = "Error creating tables: " . $sql->getLastError();
                $success = false;
              }

              if (!$success) {
                break;
              }
            }
          } finally {
            if (!$success) {
              $sql->rollback();
            } else {
              $sql->commit();
            }
          }

          if ($success) {
            $context = $this->getDocument()->getContext();
            $config = $context->getConfig();
            if (Configuration::create(\Site\Configuration\Database::class, $connectionData) === false) {
              $success = false;
              $msg = "Unable to write database file";
            } else {
              $config->setDatabase($connectionData);
              if (!$context->initSQL()) {
                $success = false;
                $msg = "Unable to verify database connection after installation";
              } else {
                $req = new \Core\API\Routes\GenerateCache($context);
                if (!$req->execute()) {
                  $success = false;
                  $msg = "Unable to write route file: " . $req->getLastError();
                }
              }
            }
          }

          $sql->close();
        }
      }

      return ["success" => $success, "msg" => $msg];
    }

    private function createUser(): array {

      $context = $this->getDocument()->getContext();
      if ($this->getParameter("prev") === "true") {
        // TODO: drop the previous database here?
        $success = $context->getConfig()->delete("\\Site\\Configuration\\Database");
        $msg = $success ? "" : error_get_last();
        return ["success" => $success, "msg" => $msg];
      }

      $username = $this->getParameter("username");
      $password = $this->getParameter("password");
      $confirmPassword = $this->getParameter("confirmPassword");
      $email = $this->getParameter("email") ?? "";

      $success = true;
      $missingInputs = [];

      if (empty($username)) {
        $success = false;
        $missingInputs[] = "Username";
      }

      if (empty($password)) {
        $success = false;
        $missingInputs[] = "Password";
      }

      if (empty($confirmPassword)) {
        $success = false;
        $missingInputs[] = "Confirm Password";
      }

      if (!$success) {
        $msg = "Please fill out the following inputs:<br>" .
          $this->createUnorderedList($missingInputs);
      } else {
        $req = new \Core\API\User\Create($context);
        $success = $req->execute([
          'username' => $username,
          'email' => $email,
          'password' => $password,
          'confirmPassword' => $confirmPassword,
          'groups' => [Group::ADMIN]
        ]);

        $msg = $req->getLastError();
      }

      return ["msg" => $msg, "success" => $success];
    }

    private function addMailService(): array {

      $context = $this->getDocument()->getContext();
      if ($this->getParameter("prev") === "true") {
        $sql = $context->getSQL();
        $success = $sql->delete("User")->execute();
        $msg = $sql->getLastError();
        return ["success" => $success, "msg" => $msg];
      }

      if ($this->getParameter("skip") === "true") {
        $req = new \Core\API\Settings\Set($context);
        $success = $req->execute(["settings" => ["mail_enabled" => false]]);
        $msg = $req->getLastError();
      } else {

        $address = $this->getParameter("address");
        $port = $this->getParameter("port");
        $username = $this->getParameter("username");
        $password = $this->getParameter("password");
        $success = true;

        $missingInputs = [];
        if (empty($address)) {
          $success = false;
          $missingInputs[] = "SMTP Address";
        }

        if (empty($port)) {
          $success = false;
          $missingInputs[] = "Port";
        }

        if (empty($username)) {
          $success = false;
          $missingInputs[] = "Username";
        }

        if (is_null($password)) {
          $success = false;
          $missingInputs[] = "Password";
        }

        if (!$success) {
          $msg = "Please fill out the following inputs:<br>" .
            $this->createUnorderedList($missingInputs);
        } else if (!is_numeric($port) || ($port = intval($port)) < 1 || $port > 65535) {
          $msg = "Port must be in range of 1-65535.";
          $success = false;
        } else {
          $success = false;

          $mail = new PHPMailer(true);
          $mail->IsSMTP();
          $mail->SMTPAuth = true;
          $mail->Username = $username;
          $mail->Password = $password;
          $mail->Host = $address;
          $mail->Port = $port;
          $mail->SMTPSecure = 'tls';
          $mail->Timeout = 10;

          try {
            $success = $mail->SmtpConnect();
            if (!$success) {
              $error = empty($mail->ErrorInfo) ? "Unknown Error" : $mail->ErrorInfo;
              $msg = "Could not connect to SMTP Server: $error";
            } else {
              $success = true;
              $msg = "";
              $mail->smtpClose();
            }
          } catch (Exception $error) {
            $msg = "Could not connect to SMTP Server: " . $error->errorMessage();
          }

          if ($success) {
            $req = new \Core\API\Settings\Set($context);
            $success = $req->execute(["settings" => [
              "mail_enabled" => true,
              "mail_host" => $address,
              "mail_port" => $port,
              "mail_username" => $username,
              "mail_password" => $password,
            ]]);
            $msg = $req->getLastError();
          }
        }
      }

      return ["success" => $success, "msg" => $msg];
    }

    private function performStep(): array {
      return match ($this->currentStep) {
        self::CHECKING_REQUIREMENTS => $this->checkRequirements(),
        self::INSTALL_DEPENDENCIES => $this->installDependencies(),
        self::DATABASE_CONFIGURATION => $this->databaseConfiguration(),
        self::CREATE_USER => $this->createUser(),
        self::ADD_MAIL_SERVICE => $this->addMailService(),
        default => [
          "success" => false,
          "msg" => "Invalid step number"
        ],
      };
    }

    private function createProgressSidebar(): array {
      $items = [];
      foreach ($this->steps as $num => $step) {

        $title = $step["title"];
        $status = $step["status"];

        switch ($status) {
          case self::PENDING:
            $statusIcon = $this->createIcon("spinner");
            $statusText = "Loading…";
            $statusColor = "muted";
            break;

          case self::SUCCESSFUL:
            $statusIcon = $this->createIcon("check-circle");
            $statusText = "Successful";
            $statusColor = "success";
            break;

          case self::ERROR:
            $statusIcon = $this->createIcon("times-circle");
            $statusText = "Failed";
            $statusColor = "danger";
            break;

          case self::NOT_STARTED:
          default:
            $statusIcon = $this->createIcon("circle", "far");
            $statusText = "Pending";
            $statusColor = "muted";
            break;
        }

        $attr = ["class" => "list-group-item d-flex justify-content-between lh-condensed"];
        if ($num == $this->currentStep) {
          $attr["id"] = "currentStep";
        }

        $items[] = html_tag("li", $attr, [
          html_tag("div", [], [
            html_tag("h6", ["class" => "my-0"], $title),
            html_tag("small", ["class" => "text-$statusColor"], $statusText),
          ], false),
          html_tag("span", ["class" => "text-$statusColor"], $statusIcon, false)
        ], false);
      }

      return $items;
    }

    private function createFormItem($formItem, $inline = false): string {

      $title = $formItem["title"];
      $name = $formItem["name"];
      $type = $formItem["type"];

      $attributes = [
        "name" => $name,
        "id" => $name,
        "class" => "form-control"
      ];

      if (isset($formItem["required"]) && $formItem["required"]) {
        $attributes["required"] = "";
      }

      if ($type !== "select") {
        $attributes["type"] = $type;

        if (isset($formItem["value"]) && $formItem["value"]) {
          $attributes["value"] = $formItem["value"];
        }

        if ($type === "number") {
          if (isset($formItem["min"]) && is_numeric($formItem["min"]))
            $attributes["min"] = $formItem["min"];
          if (isset($formItem["max"]) && is_numeric($formItem["max"]))
            $attributes["max"] = $formItem["max"];
          if (isset($formItem["step"]) && is_numeric($formItem["step"]))
            $attributes["step"] = $formItem["step"];
        } else {
          if (isset($formItem["default"])) {
            $attributes["value"] = $formItem["default"];
          }
        }
      }

      if ($type === "select") {
        $items = $formItem["items"] ?? [];
        $options = [];
        foreach ($items as $key => $val) {
          $options[] = html_tag_ex("option", ["value" => $key], $val, true, false);
        }

        $element = html_tag_ex("select", $attributes, $options, false);
      } else {
        $element = html_tag_short("input", $attributes);
      }

      $label = html_tag_ex("label", ["for" => $name], $title, true, false);
      $className = ($inline ? "col-md-6 mb-3" : "d-block my-3");
      return html_tag_ex("div", ["class" => $className], $label . $element, false);
    }

    private function createProgressMainView(): string {

      if (isDocker()) {
        $env = loadEnv();
        $defaultHost = "db";
        $defaultUsername = "root";
        $defaultDatabase = "webbase";
        $defaultPassword = $env && array_key_exists("MYSQL_ROOT_PASSWORD", $env) ? $env["MYSQL_ROOT_PASSWORD"] : "";
      } else {
        $defaultHost = "localhost";
        $defaultUsername = "";
        $defaultDatabase = "";
        $defaultPassword = "";
      }

      $views = [
        self::CHECKING_REQUIREMENTS => [
          "title" => "Application Requirements",
          "progressText" => "Checking requirements, please wait a moment…"
        ],
        self::INSTALL_DEPENDENCIES => [
          "title" => "Installing Dependencies",
          "progressText" => "Please wait while required dependencies are being installed…",
        ],
        self::DATABASE_CONFIGURATION => [
          "title" => "Database configuration",
          "form" => [
            ["title" => "Database Type", "name" => "type", "type" => "select", "required" => true, "items" => [
              "mysql" => "MySQL", "postgres" => "PostgreSQL"
            ]],
            ["title" => "Username", "name" => "username", "type" => "text", "required" => true, "default" => $defaultUsername],
            ["title" => "Password", "name" => "password", "type" => "password", "default" => $defaultPassword],
            ["title" => "Database", "name" => "database", "type" => "text", "required" => true, "default" => $defaultDatabase],
            ["type" => "row", "items" => [
              [
                "title" => "Address", "name" => "host", "type" => "text", "required" => true,
                "value" => "localhost", "row" => true, "default" => $defaultHost
              ],
              [
                "title" => "Port", "name" => "port", "type" => "number", "required" => true,
                "value" => "3306", "min" => "1", "max" => "65535", "row" => true
              ]
            ]],
            [
              "title" => "Encoding", "name" => "encoding", "type" => "text", "required" => false,
              "value" => "UTF8"
            ],
          ]
        ],
        self::CREATE_USER => [
          "title" => "Create a User",
          "form" => [
            ["title" => "Username", "name" => "username", "type" => "text", "required" => true],
            ["title" => "Email", "name" => "email", "type" => "text"],
            ["title" => "Password", "name" => "password", "type" => "password", "required" => true],
            ["title" => "Confirm Password", "name" => "confirmPassword", "type" => "password", "required" => true],
          ],
          "previousButton" => true
        ],
        self::ADD_MAIL_SERVICE => [
          "title" => "Optional: Add Mail Service",
          "form" => [
            ["title" => "Username", "name" => "username", "type" => "text", "required" => true],
            ["title" => "Password", "name" => "password", "type" => "password"],
            ["type" => "row", "items" => [
              [
                "title" => "SMTP Address", "name" => "address", "type" => "text", "required" => true,
                "value" => "localhost", "row" => true
              ],
              [
                "title" => "Port", "name" => "port", "type" => "number", "required" => true,
                "value" => "587", "min" => "1", "max" => "65535", "row" => true
              ]
            ]],
          ],
          "skip" => true,
          "previousButton" => true
        ],
        self::FINISH_INSTALLATION => [
          "title" => "Finish Installation",
          "text" => "Installation finished, you can now customize your own website, check the source code and stuff."
        ]
      ];

      if (!isset($views[$this->currentStep])) {
        return "";
      }

      $currentView = $views[$this->currentStep];
      $prevDisabled = !isset($currentView["previousButton"]) || !$currentView["previousButton"];
      $spinnerIcon = $this->createIcon("spinner");
      $title = $currentView["title"];

      $html  = html_tag("h4", ["class" => "mb-3"], $title);
      $html .= html_tag_short("h4", ["class" => "mb-4"]);

      if (isset($currentView["text"])) {
        $text = $currentView["text"];
        $html .= html_tag("div", ["class" => "my-3"], $text);
      }

      if (isset($currentView["progressText"])) {
        $progressText = htmlspecialchars($currentView["progressText"]);
        $class = ["my-3"];
        if (!in_array($this->currentStep, [self::CHECKING_REQUIREMENTS, self::INSTALL_DEPENDENCIES])) {
          $class[] = "d-none";
        }

        $html .= html_tag("div", ["class" => $class, "id" => "progressText"], [$progressText, $spinnerIcon], false);
      }

      if (isset($currentView["form"])) {
        $rows = [];

        foreach ($currentView["form"] as $formItem) {
          if ($formItem["type"] === "row") {
            $rows[] = html_tag("div", ["class" => "row"], array_map(function ($item) {
              return $this->createFormItem($item, true);
            }, $formItem["items"]), false);
          } else {
            $rows[] = $this->createFormItem($formItem);
          }
        }

        $html .= html_tag("form", ["id" => "installForm"], $rows, false);
      }

      $buttons = [
        ["title" => "Go Back", "type" => "info", "id" => "btnPrev", "float" => "left", "disabled" => $prevDisabled]
      ];

      if ($this->currentStep != self::FINISH_INSTALLATION) {
        if (in_array($this->currentStep, [self::CHECKING_REQUIREMENTS, self::INSTALL_DEPENDENCIES])) {
          $buttons[] = ["title" => "Retry", "type" => "success", "id" => "btnRetry", "float" => "right", "hidden" => true];
        } else {
          $buttons[] = ["title" => "Submit", "type" => "success", "id" => "btnSubmit", "float" => "right"];
        }
      } else {
        $buttons[] = ["title" => "Finish", "type" => "success", "id" => "btnFinish", "float" => "right"];
      }

      if (isset($currentView["skip"])) {
        $buttons[] = ["title" => "Skip", "type" => "secondary", "id" => "btnSkip", "float" => "right"];
      }

      $buttonsLeft = [];
      $buttonsRight = [];
      foreach ($buttons as $button) {
        $title = $button["title"];
        $type = $button["type"];
        $id = $button["id"];
        $float = $button["float"];

        $attrs = ["id" => $id, "class" => ["m-1", "btn", "btn-$type"]];
        if (isset($button["hidden"]) && $button["hidden"]) {
          $attrs["class"][] = "d-none";
        }

        if (isset($button["disabled"]) && $button["disabled"]) {
          $attrs["class"][] = "disabled";
        }

        $button = html_tag("button", $attrs, $title, false);
        if ($float === "left") {
          $buttonsLeft[] = $button;
        } else {
          $buttonsRight[] = $button;
        }
      }

      $html .= html_tag("div", ["class" => "row"], [
        html_tag("div", ["class" => "col-6 float-left text-left"], $buttonsLeft, false),
        html_tag("div", ["class" => "col-6 float-right text-right"], $buttonsRight, false),
      ], false);

      return $html;
    }

    function getCode(): string {
      $html = parent::getCode();

      $this->steps = [
        self::CHECKING_REQUIREMENTS => [
          "title" => "Checking requirements",
          "status" => self::ERROR
        ],
        self::INSTALL_DEPENDENCIES => [
          "title" => "Install dependencies",
          "status" => self::NOT_STARTED
        ],
        self::DATABASE_CONFIGURATION => [
          "title" => "Database configuration",
          "status" => self::NOT_STARTED
        ],
        self::CREATE_USER => [
          "title" => "Create User",
          "status" => self::NOT_STARTED
        ],
        self::ADD_MAIL_SERVICE => [
          "title" => "Add Mail Service",
          "status" => self::NOT_STARTED
        ],
        self::FINISH_INSTALLATION => [
          "title" => "Finish Installation",
          "status" => self::NOT_STARTED
        ],
      ];

      $this->currentStep = $this->getCurrentStep();

      // set status
      for ($step = self::CHECKING_REQUIREMENTS; $step < $this->currentStep; $step++) {
        $this->steps[$step]["status"] = self::SUCCESSFUL;
      }

      if ($this->currentStep == self::FINISH_INSTALLATION) {
        $this->steps[$this->currentStep]["status"] = self::SUCCESSFUL;
      }

      // POST
      if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_REQUEST['status'])) {
          $response = $this->performStep();
        } else {
          $response = ["error" => $this->errorString];
        }
        $response["step"] = $this->currentStep;
        die(json_encode($response));
      }

      $progressSidebar = $this->createProgressSidebar();
      $progressMainView = $this->createProgressMainView();

      $errorAttrs = ["class" => ["alert", "alert-danger", "mt-4"], "id" => "status"];
      if ($this->errorString) {
        $errorAttrs["class"][] = "alert-danger";
      } else {
        $errorAttrs["class"][] = "d-none";
      }

      $html .= html_tag("body", ["class" => "bg-light"],
        html_tag("div", ["class" => "container"], [

          // title
          html_tag("div", ["class" => "py-5 text-center"], [
            html_tag("h2", [], "WebBase - Installation"),
            html_tag("p", ["class" => "lead"],
              "Process the following steps and fill out the required forms to install your WebBase-Installation."
            )
          ], false),

          // content
          html_tag("div", ["class" => "row"], [

            // right column
            html_tag("div", ["class" => "col-md-4 order-md-2 mb-4"], [
              html_tag("h4", ["class" => "d-flex justify-content-between align-items-center mb-3"],
                html_tag("span", ["class" => "text-muted"], "Progress"),
                false
              ),
              html_tag("ul", ["class" => "list-group mb-3"], $progressSidebar, false)
            ], false),

            // left column
            html_tag("div", ["class" => "col-md-8 order-md-1"], [
              $progressMainView,
              html_tag("div", $errorAttrs, $this->errorString, false)
            ], false)

          ], false),

        ], false),
        false
      );

      return $html;
    }
  }
}
