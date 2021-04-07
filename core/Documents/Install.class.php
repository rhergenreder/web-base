<?php

namespace Documents {

  use Documents\Install\InstallBody;
  use Documents\Install\InstallHead;
  use Elements\Document;

  class Install extends Document {
    public function __construct($user) {
      parent::__construct($user, InstallHead::class, InstallBody::class);
      $this->databaseRequired = false;
    }
  }
}

namespace Documents\Install {

  use Configuration\CreateDatabase;
  use Driver\SQL\SQL;
  use Elements\Body;
  use Elements\Head;
  use Elements\Link;
  use Elements\Script;
  use External\PHPMailer\Exception;
  use External\PHPMailer\PHPMailer;
  use Objects\ConnectionData;

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
      return array(
        array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0'),
        array('name' => 'format-detection', 'content' => 'telephone=yes'),
        array('charset' => 'utf-8'),
        array("http-equiv" => 'expires', 'content' => '0'),
        array("name" => 'robots', 'content' => 'noarchive'),
      );
    }

    protected function initRawFields(): array {
      return array();
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
    const DATABASE_CONFIGURATION = 2;
    const CREATE_USER = 3;
    const ADD_MAIL_SERVICE = 4;
    const FINISH_INSTALLATION = 5;

    //
    private string $errorString;
    private int $currentStep;
    private array $steps;

    function __construct($document) {
      parent::__construct($document);
      $this->errorString = "";
      $this->currentStep = InstallBody::CHECKING_REQUIREMENTS;
      $this->steps = array();
    }

    function isDocker(): bool {
      return file_exists("/.dockerenv");
    }

    private function getParameter($name): ?string {
      if (isset($_REQUEST[$name]) && is_string($_REQUEST[$name])) {
        return trim($_REQUEST[$name]);
      }

      return NULL;
    }

    private function getCurrentStep(): int {

      if (!$this->checkRequirements()["success"]) {
        return self::CHECKING_REQUIREMENTS;
      }

      $user = $this->getDocument()->getUser();
      $config = $user->getConfiguration();

      // Check if database configuration exists
      if (!$config->getDatabase()) {
        return self::DATABASE_CONFIGURATION;
      }

      $sql = $user->getSQL();
      if (!$sql || !$sql->isConnected()) {
        return self::DATABASE_CONFIGURATION;
      }

      $countKeyword = $sql->count();
      $res = $sql->select($countKeyword)->from("User")->execute();
      if ($res === FALSE) {
        return self::DATABASE_CONFIGURATION;
      } else {
        if ($res[0]["count"] > 0) {
          $step = self::ADD_MAIL_SERVICE;
        } else {
          return self::CREATE_USER;
        }
      }

      if ($step === self::ADD_MAIL_SERVICE) {
        $req = new \Api\Settings\Get($user);
        $success = $req->execute(array("key" => "^mail_enabled$"));
        if (!$success) {
          $this->errorString = $req->getLastError();
          return self::DATABASE_CONFIGURATION;
        } else if (isset($req->getResult()["settings"]["mail_enabled"])) {
          $step = self::FINISH_INSTALLATION;

          $req = new \Api\Settings\Set($user);
          $success = $req->execute(array("settings" => array("installation_completed" => "1")));
          if (!$success) {
            $this->errorString = $req->getLastError();
          } else {
            $req = new \Api\Notifications\Create($user);
            $req->execute(array(
                "title" => "Welcome",
                "message" => "Your Web-base was successfully installed. Check out the admin dashboard. Have fun!",
                "groupId" => USER_GROUP_ADMIN
              )
            );
            $this->errorString = $req->getLastError();
          }
        }
      }

      return $step;
    }

    private function checkRequirements(): array {

      $msg = $this->errorString;
      $success = true;
      $failedRequirements = array();

      $configDir = "core/Configuration/";
      if (!is_writeable($configDir)) {
        $failedRequirements[] = "<b>$configDir</b> is not writeable. Try running <b>chmod 700 $configDir</b>";
        $success = false;
      }

      if (function_exists("posix_getuid")) {
        $userId = posix_getuid();
        if (fileowner($configDir) !== $userId) {
          $username = posix_getpwuid($userId)['name'];
          $failedRequirements[] = "<b>$configDir</b> is not owned by current user: $username ($userId). Try running <b>chown -R $username $configDir</b>";
          $success = false;
        }
      }

      if (version_compare(PHP_VERSION, '7.4', '<')) {
        $failedRequirements[] = "PHP Version <b>>= 7.4</b> is required. Got: <b>" . PHP_VERSION . "</b>";
        $success = false;
      }

      if (!$success) {
        $msg = "The following requirements failed the check:<br>" .
          $this->createUnorderedList($failedRequirements);
        $this->errorString = $msg;
      }

      return array("success" => $success, "msg" => $msg);
    }

    private function databaseConfiguration(): array {

      $host = $this->getParameter("host");
      $port = $this->getParameter("port");
      $username = $this->getParameter("username");
      $password = $this->getParameter("password");
      $database = $this->getParameter("database");
      $type = $this->getParameter("type");
      $encoding = $this->getParameter("encoding");
      $encoding = ($encoding ? $encoding : "UTF-8");
      $success = true;

      $missingInputs = array();
      if (is_null($host) || empty($host)) {
        $success = false;
        $missingInputs[] = "Host";
      }

      if (is_null($port) || empty($port)) {
        $success = false;
        $missingInputs[] = "Port";
      }

      if (is_null($username) || empty($username)) {
        $success = false;
        $missingInputs[] = "Username";
      }

      if (is_null($password)) {
        $success = false;
        $missingInputs[] = "Password";
      }

      if (is_null($database) || empty($database)) {
        $success = false;
        $missingInputs[] = "Database";
      }

      if (is_null($type) || empty($type)) {
        $success = false;
        $missingInputs[] = "Type";
      }

      $supportedTypes = array("mysql", "postgres");
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
          foreach ($queries as $query) {
            if (!($res = $query->execute())) {
              $msg = "Error creating tables: " . $sql->getLastError();
              $success = false;
              break;
            }
          }

          $config = $this->getDocument()->getUser()->getConfiguration();
          if (!$config->create("Database", $connectionData)) {
            $success = false;
            $msg = "Unable to write file";
          }

          $sql->close();
        }
      }

      return array("success" => $success, "msg" => $msg);
    }

    private function createUser(): array {

      $user = $this->getDocument()->getUser();
      if ($this->getParameter("prev") === "true") {
        $success = $user->getConfiguration()->delete("Database");
        $msg = $success ? "" : error_get_last();
        return array("success" => $success, "msg" => $msg);
      }

      $username = $this->getParameter("username");
      $password = $this->getParameter("password");
      $confirmPassword = $this->getParameter("confirmPassword");
      $email = $this->getParameter("email") ?? "";

      $success = true;
      $missingInputs = array();

      if (is_null($username) || empty($username)) {
        $success = false;
        $missingInputs[] = "Username";
      }

      if (is_null($password) || empty($password)) {
        $success = false;
        $missingInputs[] = "Password";
      }

      if (is_null($confirmPassword) || empty($confirmPassword)) {
        $success = false;
        $missingInputs[] = "Confirm Password";
      }

      if (!$success) {
        $msg = "Please fill out the following inputs:<br>" .
          $this->createUnorderedList($missingInputs);
      } else {
        $sql = $user->getSQL();
        $req = new \Api\User\Create($user);
        $success = $req->execute(array(
          'username' => $username,
          'email' => $email,
          'password' => $password,
          'confirmPassword' => $confirmPassword,
        ));

        $msg = $req->getLastError();
        if ($success) {
          $success = $sql->insert("UserGroup", array("group_id", "user_id"))
            ->addRow(USER_GROUP_ADMIN, $req->getResult()["userId"])
            ->execute();
          $msg = $sql->getLastError();
        }
      }

      return array("msg" => $msg, "success" => $success);
    }

    private function addMailService(): array {

      $user = $this->getDocument()->getUser();
      if ($this->getParameter("prev") === "true") {
        $sql = $user->getSQL();
        $success = $sql->delete("User")->execute();
        $msg = $sql->getLastError();
        return array("success" => $success, "msg" => $msg);
      }

      $success = true;
      $msg = $this->errorString;
      if ($this->getParameter("skip") === "true") {
        $req = new \Api\Settings\Set($user);
        $success = $req->execute(array("settings" => array("mail_enabled" => "0")));
        $msg = $req->getLastError();
      } else {

        $address = $this->getParameter("address");
        $port = $this->getParameter("port");
        $username = $this->getParameter("username");
        $password = $this->getParameter("password");
        $success = true;

        $missingInputs = array();
        if (is_null($address) || empty($address)) {
          $success = false;
          $missingInputs[] = "SMTP Address";
        }

        if (is_null($port) || empty($port)) {
          $success = false;
          $missingInputs[] = "Port";
        }

        if (is_null($username) || empty($username)) {
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
            $req = new \Api\Settings\Set($user);
            $success = $req->execute(array("settings" => array(
              "mail_enabled" => "1",
              "mail_host" => "$address",
              "mail_port" => "$port",
              "mail_username" => "$username",
              "mail_password" => "$password",
            )));
            $msg = $req->getLastError();
          }
        }
      }

      return array("success" => $success, "msg" => $msg);
    }

    private function performStep(): array {

      switch ($this->currentStep) {

        case self::CHECKING_REQUIREMENTS:
          return $this->checkRequirements();

        case self::DATABASE_CONFIGURATION:
          return $this->databaseConfiguration();

        case self::CREATE_USER:
          return $this->createUser();

        case self::ADD_MAIL_SERVICE:
          return $this->addMailService();

        default:
          return array(
            "success" => false,
            "msg" => "Invalid step number"
          );
      }
    }

    private function createProgressSidebar(): string {
      $items = array();
      foreach ($this->steps as $num => $step) {

        $title = $step["title"];
        $status = $step["status"];
        $currentStep = ($num == $this->currentStep) ? " id=\"currentStep\"" : "";

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

        $items[] = "
          <li class=\"list-group-item d-flex justify-content-between lh-condensed\"$currentStep>
            <div>
              <h6 class=\"my-0\">$title</h6>
              <small class=\"text-$statusColor\">$statusText</small>
            </div>
            <span class=\"text-$statusColor\">$statusIcon</span>
          </li>";
      }

      return implode("", $items);
    }

    private function createFormItem($formItem, $inline = false): string {

      $title = $formItem["title"];
      $name = $formItem["name"];
      $type = $formItem["type"];

      $attributes = array(
        "name" => $name,
        "id" => $name,
        "class" => "form-control"
      );

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

      $replacements = array("+" => " ", "&" => "\" ", "=" => "=\"");
      $attributes = http_build_query($attributes) . "\"";
      foreach ($replacements as $key => $val) {
        $attributes = str_replace($key, $val, $attributes);
      }

      if ($type === "select") {
        $items = $formItem["items"] ?? array();
        $element = "<select $attributes>";
        foreach ($items as $key => $val) {
          $element .= "<option value=\"$key\">$val</option>";
        }
        $element .= "</select>";
      } else {
        $element = "<input $attributes>";
      }

      if (!$inline) {
        return
          "<div class=\"d-block my-3\">
            <label for=\"$name\">$title</label>
            $element
          </div>";
      } else {
        return
          "<div class=\"col-md-6 mb-3\">
            <label for=\"$name\">$title</label>
            $element
          </div>";
      }
    }

    private function createProgessMainview(): string {

      $isDocker = $this->isDocker();
      $defaultHost = ($isDocker ? "db" : "");
      $defaultUsername = ($isDocker ? "root" : "");
      $defaultPassword = ($isDocker ? "webbasedb" : "");
      $defaultDatabase = ($isDocker ? "webbase" : "");

      $views = array(
        self::CHECKING_REQUIREMENTS => array(
          "title" => "Application Requirements",
          "progressText" => "Checking requirements, please wait a moment…"
        ),
        self::DATABASE_CONFIGURATION => array(
          "title" => "Database configuration",
          "form" => array(
            array("title" => "Database Type", "name" => "type", "type" => "select", "required" => true, "items" => array(
              "mysql" => "MySQL", "postgres" => "PostgreSQL"
            )),
            array("title" => "Username", "name" => "username", "type" => "text", "required" => true, "default" => $defaultUsername),
            array("title" => "Password", "name" => "password", "type" => "password", "default" => $defaultPassword),
            array("title" => "Database", "name" => "database", "type" => "text", "required" => true, "default" => $defaultDatabase),
            array("type" => "row", "items" => array(
              array(
                "title" => "Address", "name" => "host", "type" => "text", "required" => true,
                "value" => "localhost", "row" => true, "default" => $defaultHost
              ),
              array(
                "title" => "Port", "name" => "port", "type" => "number", "required" => true,
                "value" => "3306", "min" => "1", "max" => "65535", "row" => true
              )
            )),
            array(
              "title" => "Encoding", "name" => "encoding", "type" => "text", "required" => false,
              "value" => "UTF-8"
            ),
          )
        ),
        self::CREATE_USER => array(
          "title" => "Create a User",
          "form" => array(
            array("title" => "Username", "name" => "username", "type" => "text", "required" => true),
            array("title" => "Email", "name" => "email", "type" => "text"),
            array("title" => "Password", "name" => "password", "type" => "password", "required" => true),
            array("title" => "Confirm Password", "name" => "confirmPassword", "type" => "password", "required" => true),
          ),
          "previousButton" => true
        ),
        self::ADD_MAIL_SERVICE => array(
          "title" => "Optional: Add Mail Service",
          "form" => array(
            array("title" => "Username", "name" => "username", "type" => "text", "required" => true),
            array("title" => "Password", "name" => "password", "type" => "password"),
            array("type" => "row", "items" => array(
              array(
                "title" => "SMTP Address", "name" => "address", "type" => "text", "required" => true,
                "value" => "localhost", "row" => true
              ),
              array(
                "title" => "Port", "name" => "port", "type" => "number", "required" => true,
                "value" => "587", "min" => "1", "max" => "65535", "row" => true
              )
            )),
          ),
          "skip" => true,
          "previousButton" => true
        ),
        self::FINISH_INSTALLATION => array(
          "title" => "Finish Installation",
          "text" => "Installation finished, you can now customize your own website, check the source code and stuff."
        )
      );

      if (!isset($views[$this->currentStep])) {
        return "";
      }

      $currentView = $views[$this->currentStep];
      $prevDisabled = !isset($currentView["previousButton"]) || !$currentView["previousButton"];
      $spinnerIcon = $this->createIcon("spinner");
      $title = $currentView["title"];

      $html = "<h4 class=\"mb-3\">$title</h4><hr class=\"mb-4\">";

      if (isset($currentView["text"])) {
        $text = $currentView["text"];
        $html .= "<div class=\"my-3\">$text</i></div>";
      }

      if (isset($currentView["progressText"])) {
        $progressText = $currentView["progressText"];
        $html .= "<div id=\"progressText\" style=\"display:none\" class=\"my-3\">$progressText$spinnerIcon</i></div>";
      }

      if (isset($currentView["form"])) {
        $html .= "<form id=\"installForm\">";

        foreach ($currentView["form"] as $formItem) {

          if ($formItem["type"] === "row") {
            $html .= "<div class=\"row\">";
            foreach ($formItem["items"] as $item) {
              $html .= $this->createFormItem($item, true);
            }
            $html .= "</div>";
          } else {
            $html .= $this->createFormItem($formItem);
          }
        }

        $html .= "
          </form>";
      }

      $buttons = array(
        array("title" => "Go Back", "type" => "info", "id" => "btnPrev", "float" => "left", "disabled" => $prevDisabled)
      );

      if ($this->currentStep != self::FINISH_INSTALLATION) {
        if ($this->currentStep == self::CHECKING_REQUIREMENTS) {
          $buttons[] = array("title" => "Retry", "type" => "success", "id" => "btnRetry", "float" => "right");
        } else {
          $buttons[] = array("title" => "Submit", "type" => "success", "id" => "btnSubmit", "float" => "right");
        }
      } else {
        $buttons[] = array("title" => "Finish", "type" => "success", "id" => "btnFinish", "float" => "right");
      }

      if (isset($currentView["skip"])) {
        $buttons[] = array("title" => "Skip", "type" => "secondary", "id" => "btnSkip", "float" => "right");
      }

      $buttonsLeft = "";
      $buttonsRight = "";

      foreach ($buttons as $button) {
        $title = $button["title"];
        $type = $button["type"];
        $id = $button["id"];
        $float = $button["float"];
        $disabled = (isset($button["disabled"]) && $button["disabled"]) ? " disabled" : "";
        $button = "<button type=\"button\" id=\"$id\" class=\"btn btn-$type m-1\"$disabled>$title</button>";

        if ($float === "left") {
          $buttonsLeft .= $button;
        } else {
          $buttonsRight .= $button;
        }
      }

      $html .=
        "<div class=\"row\">
          <div class=\"col-6 float-left text-left\">$buttonsLeft</div>
          <div class=\"col-6 float-right text-right\">$buttonsRight</div>
        </div>";

      return $html;
    }

    function getCode(): string {
      $html = parent::getCode();

      $this->steps = array(
        self::CHECKING_REQUIREMENTS => array(
          "title" => "Checking requirements",
          "status" => self::ERROR
        ),
        self::DATABASE_CONFIGURATION => array(
          "title" => "Database configuration",
          "status" => self::NOT_STARTED
        ),
        self::CREATE_USER => array(
          "title" => "Create User",
          "status" => self::NOT_STARTED
        ),
        self::ADD_MAIL_SERVICE => array(
          "title" => "Add Mail Service",
          "status" => self::NOT_STARTED
        ),
        self::FINISH_INSTALLATION => array(
          "title" => "Finish Installation",
          "status" => self::NOT_STARTED
        ),
      );

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
        $response = $this->performStep();
        $response["step"] = $this->currentStep;
        die(json_encode($response));
      }

      $progressSidebar = $this->createProgressSidebar();
      $progressMainview = $this->createProgessMainview();

      $errorStyle = ($this->errorString ? '' : ' style="display:none"');
      $errorClass = ($this->errorString ? ' alert-danger' : '');

      $html .= "
        <body class=\"bg-light\">
          <div class=\"container\">
            <div class=\"py-5 text-center\">
              <h2>WebBase - Installation</h2>
              <p class=\"lead\">
                Process the following steps and fill out the required forms to install your WebBase-Installation.
              </p>
            </div>

          <div class=\"row\">
            <div class=\"col-md-4 order-md-2 mb-4\">
              <h4 class=\"d-flex justify-content-between align-items-center mb-3\">
                <span class=\"text-muted\">Progress</span>
              </h4>

              <ul class=\"list-group mb-3\">
                $progressSidebar
              </ul>
            </div>
            <div class=\"col-md-8 order-md-1\">
              $progressMainview
              <div class=\"alert$errorClass mt-4\" id=\"status\"$errorStyle>$this->errorString</div>
            </div>
          </div>
        </div>
      </body>";

      return $html;
    }
  }
}
