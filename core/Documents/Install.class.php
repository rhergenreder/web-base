<?php

namespace Documents {
  class Install extends \Elements\Document {
    public function __construct($user) {
      parent::__construct($user, Install\Head::class, Install\Body::class);
      $this->databseRequired = false;
    }
  }
}

namespace Documents\Install {

  class Head extends \Elements\Head {

    public function __construct($document) {
      parent::__construct($document);
    }

    protected function initSources() {
      $this->loadJQuery();
      $this->loadBootstrap();
      $this->loadFontawesome();
      $this->addJS(\Elements\Script::CORE);
      $this->addCSS(\Elements\Link::CORE);
      $this->addJS(\Elements\Script::INSTALL);
    }

    protected function initMetas() {
      return array(
        array('name' => 'viewport', 'content' => 'width=device-width, initial-scale=1.0'),
        array('name' => 'format-detection', 'content' => 'telephone=yes'),
        array('charset' => 'utf-8'),
        array("http-equiv" => 'expires', 'content' => '0'),
        array("name" => 'robots', 'content' => 'noarchive'),
      );
    }

    protected function initRawFields() {
      return array();
    }

    protected function initTitle() {
      return "WebBase - Installation";
    }

  }

  class Body extends \Elements\Body {

    // Status enum
    const NOT_STARTED = 0;
    const PENDING = 1;
    const SUCCESFULL = 2;
    const ERROR = 3;

    // Step enum
    const CHECKING_REQUIRMENTS = 1;
    const DATABASE_CONFIGURATION = 2;
    const CREATE_USER = 3;
    const ADD_MAIL_SERVICE = 4;
    const ADD_GOOGLE_SERVICE = 5;

    //
    private $configDirectory;
    private $databaseScript;

    function __construct($document) {
      parent::__construct($document);

      // TODO: make better
      $this->configDirectory = getWebRoot() . '/core/Configuration';
      $this->databaseScript  = getWebRoot() . '/core/Configuration/database.sql';
    }

    private function getParameter($name) {
      if(isset($_REQUEST[$name]) && is_string($_REQUEST[$name])) {
        return trim($_REQUEST[$name]);
      }

      return NULL;
    }

    private function getCurrentStep() {

      if(!$this->checkRequirements()) {
        return self::CHECKING_REQUIRMENTS;
      }

      $user = $this->getDocument()->getUser();
      $config = $user->getConfiguration();

      // Check if database configuration exists
      if(!$config->getDatabase()) {
        return self::DATABASE_CONFIGURATION;
      }

      $query = "SELECT * FROM User";
      $sql = $user->getSQL();
      if(!is_null($sql) && $sql->isConnected()) {
        $this->getDocument()->getUser()->setSql($sql);
        $res = $sql->query($query);
        if($res) {
          if($res->num_rows === 0) {
            $step = self::CREATE_USER;
          } else {
            $step = self::ADD_MAIL_SERVICE;
          }
        }
      } else {
        $step = self::DATABASE_CONFIGURATION;
      }

      if($step == self::ADD_MAIL_SERVICE && $config->isFilePresent("Mail")) {
        $step = self::ADD_GOOGLE_SERVICE;
      }

      return $step;
    }

    private function checkRequirements() {

      $msg = "";
      $success = true;
      $failedRequirements = array();

      if(!is_writeable($this->configDirectory)) {
        $failedRequirements[] = "<b>$this->configDirectory</b> is not writeable. Try running <b>chmod 600</b>";
        $success = false;
      }

      if(!is_readable($this->databaseScript)) {
        $failedRequirements[] = "<b>$this->databaseScript</b> is not readable.";
        $success = false;
      }

      if(version_compare(PHP_VERSION, '7.1', '<')) {
          $failedRequirements[] = "PHP Version <b>>= 7.1</b> is required. Got: <b>" . PHP_VERSION . "</b>";
          $success = false;
      }

      if(!function_exists('mysqli_connect')) {
        $link = $this->createExternalLink("https://secure.php.net/manual/en/mysqli.setup.php");
        $failedRequirements[] = "mysqli is not enabled yet. See: $link";
        $success = false;
      }

      if(!$success) {
        $msg = "The following requirements failed the check:<br>" .
          $this->createUnorderedList($failedRequirements);
      }

      return array("success" => $success, "msg" => $msg);
    }

    private function databaseConfiguration() {

      $host = $this->getParameter("host");
      $port = $this->getParameter("port");
      $username = $this->getParameter("username");
      $password = $this->getParameter("password");
      $database = $this->getParameter("database");
      $success = true;

      $missingInputs = array();
      if(is_null($host) || empty($host)) {
        $success = false;
        $missingInputs[] = "Host";
      }

      if(is_null($port) || empty($port)) {
        $success = false;
        $missingInputs[] = "Port";
      }

      if(is_null($username) || empty($username)) {
        $success = false;
        $missingInputs[] = "Username";
      }

      if(is_null($password)) {
        $success = false;
        $missingInputs[] = "Password";
      }

      if(is_null($database) || empty($database)) {
        $success = false;
        $missingInputs[] = "Database";
      }

      if(!$success) {
        $msg = "Please fill out the following inputs:<br>" .
          $this->createUnorderedList($missingInputs);
      } else if(!is_numeric($port) || ($port = intval($port)) < 1 || $port > 65535) {
        $msg = "Port must be in range of 1-65535.";
        $success = false;
      } else {
        $connectionData = new \Objects\ConnectionData($host, $port, $username, $password);
        $connectionData->setProperty('database', $database);
        $connectionData->setProperty('encoding', 'utf8');
        $sql = new \Driver\SQL($connectionData);
        $success = $sql->connect();

        if(!$success) {
          $msg = "Error connecting to database:<br>" . $sql->getLastError();
        } else {
          try {
            $msg = "Error loading database script $this->databaseScript";
            $commands = file_get_contents($this->databaseScript);
            $success = $sql->executeMulti($commands);
            if(!$success) {
              $msg = $sql->getLastError();
            } else if(!$this->getDocument()->getUser()->getConfiguration()->create("Database", $connectionData)) {
              $success = false;
              $msg = "Unable to write file";
            } else {
              $msg = "";
            }
          } catch(Exception $e) {
            $success = false;
            $msg .= ": " . $e->getMessage();
          }

          if($sql) {
            $sql->close();
          }
        }
      }

      return array("success" => $success, "msg" => $msg);
    }

    private function createUser() {

      $user = $this->getDocument()->getUser();
      if($this->getParameter("prev") === "true") {
        $success = $user->getConfiguration()->delete("Database");
        $msg = $success ? "" : error_get_last();
        return array("success" => $success, "msg" => $msg);
      }

      $username = $this->getParameter("username");
      $password = $this->getParameter("password");
      $confirmPassword = $this->getParameter("confirmPassword");

      $msg = "";
      $success = true;
      $missingInputs = array();

      if(is_null($username) || empty($username)) {
        $success = false;
        $missingInputs[] = "Username";
      }

      if(is_null($password) || empty($password)) {
        $success = false;
        $missingInputs[] = "Password";
      }

      if(is_null($confirmPassword) || empty($confirmPassword)) {
        $success = false;
        $missingInputs[] = "Confirm Password";
      }

      if(!$success) {
        $msg = "Please fill out the following inputs:<br>" .
          $this->createUnorderedList($missingInputs);
      } else if(strlen($username) < 5 || strlen($username) > 32) {
        $msg = "The username should be between 5 and 32 characters long";
        $success = false;
      } else if(strcmp($password, $confirmPassword) !== 0) {
        $msg = "The given passwords do not match";
        $success = false;
      } else if(strlen($password) < 6) {
        $msg = "The password should be at least 6 characters long";
        $success = false;
      } else {
        $salt = generateRandomString(16);
        $hash = hash('sha256', $password . $salt);
        $query = "INSERT INTO User (name, salt, password) VALUES (?,?,?)";
        $req = new \Api\ExecuteStatement($user);
        $success = $req->execute(array("query" => $query, $username, $salt, $hash));
        $nsg = $req->getLastError();
      }

      return array("msg" => $msg, "success" => $success);
    }

    private function addMailService() {

      $user = $this->getDocument()->getUser();
      if($this->getParameter("prev") === "true") {
        $req = new \Api\ExecuteStatement($user);
        $success = $req->execute(array("query" => "TRUNCATE User"));
        $msg = $req->getLastError();
        return array("success" => $success, "msg" => $msg);
      }

      $success = true;
      $msg = "";
      if($this->getParameter("skip") === "true") {
        if(!$user->getConfiguration()->create("Mail", null)) {
          $success = false;
          $msg = "Unable to create file";
        }
      } else {

        $address = $this->getParameter("address");
        $port = $this->getParameter("port");
        $username = $this->getParameter("username");
        $password = $this->getParameter("password");
        $success = true;

        $missingInputs = array();
        if(is_null($address) || empty($address)) {
          $success = false;
          $missingInputs[] = "SMTP Address";
        }

        if(is_null($port) || empty($port)) {
          $success = false;
          $missingInputs[] = "Port";
        }

        if(is_null($username) || empty($username)) {
          $success = false;
          $missingInputs[] = "Username";
        }

        if(is_null($password)) {
          $success = false;
          $missingInputs[] = "Password";
        }

        if(!$success) {
          $msg = "Please fill out the following inputs:<br>" .
            $this->createUnorderedList($missingInputs);
        } else if(!is_numeric($port) || ($port = intval($port)) < 1 || $port > 65535) {
          $msg = "Port must be in range of 1-65535.";
          $success = false;
        } else {
          $success = false;

          $mail = new \External\PHPMailer\PHPMailer(true);
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
            if(!$success) {
              $error = empty($mail->ErrorInfo) ? "Unknown Error" : $mail->ErrorInfo;
              $msg = "Could not connect to SMTP Server: $error";
            } else {
              $success = true;
              $msg = "";
              $mail->smtpClose();
            }
          } catch(\External\PHPMailer\Exception $error) {
            $msg = "Could not connect to SMTP Server: " . $error->errorMessage();
          }

          if($success) {
            $connectionData = new \Objects\ConnectionData($address, $port, $username, $password);
            if(!$user->getConfiguration()->create("Mail", $connectionData)) {
              $success = false;
              $msg = "Unable to create file";
            }
          }
        }
      }

      return array("success" => $success, "msg" => $msg);
    }

    private function addGoogleService() {
        // return array("success" => $success, "msg" => $msg);
    }

    private function performStep() {

      switch($this->currentStep) {

        case self::CHECKING_REQUIRMENTS:
          return $this->checkRequirements();

        case self::DATABASE_CONFIGURATION:
          return $this->databaseConfiguration();

        case self::CREATE_USER:
          return $this->createUser();

        case self::ADD_MAIL_SERVICE:
          return $this->addMailService();

        case self::ADD_GOOGLE_SERVICE:
          return $this->addGoogleService();

        default:
          return array(
            "success" => false,
            "msg" => "Invalid step number"
          );
      }
    }

    private function createProgressSidebar() {
      $items = array();
      foreach($this->steps as $num => $step) {

        $title = $step["title"];
        $status = $step["status"];
        $currentStep = ($num == $this->currentStep) ? " id=\"currentStep\"" : "";

        switch($status) {
          case self::PENDING:
            $statusIcon  = '<i class="fas fa-spin fa-spinner"></i>';
            $statusText  = "Loading…";
            $statusColor = "muted";
            break;

          case self::SUCCESFULL:
            $statusIcon  = '<i class="fas fa-check-circle"></i>';
            $statusText  = "Successfull";
            $statusColor = "success";
            break;

          case self::ERROR:
            $statusIcon  = '<i class="fas fa-times-circle"></i>';
            $statusText  = "Failed";
            $statusColor = "danger";
            break;

          case self::NOT_STARTED:
          default:
            $statusIcon = '<i class="far fa-circle"></i>';
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

    private function createFormItem($formItem, $inline=false) {

      $title = $formItem["title"];
      $name  = $formItem["name"];
      $type  = $formItem["type"];

      $attributes = array(
        "name" => $name,
        "id" => $name,
        "class" => "form-control",
        "type" => $type,
      );

      if(isset($formItem["required"]) && $formItem["required"]) {
        $attributes["required"] = "";
      }

      if(isset($formItem["value"]) && $formItem["value"]) {
        $attributes["value"] = $formItem["value"];
      }

      if($type === "number") {
        if(isset($formItem["min"]) && is_numeric($formItem["min"]))
          $attributes["min"] = $formItem["min"];
        if(isset($formItem["max"]) && is_numeric($formItem["max"]))
          $attributes["max"] = $formItem["max"];
        if(isset($formItem["step"]) && is_numeric($formItem["step"]))
          $attributes["step"] = $formItem["step"];
      }

      $attributes = str_replace("+", " ", str_replace("&", "\" ", str_replace("=", "=\"", http_build_query($attributes)))) . "\"";

      if(!$inline) {
        return
          "<div class=\"d-block my-3\">
            <label for=\"$name\">$title</label>
            <input $attributes>
          </div>";
      } else {
        return
          "<div class=\"col-md-6 mb-3\">
            <label for=\"$name\">$title</label>
            <input $attributes>
          </div>";
      }
    }

    private function createProgessMainview() {

      $views = array(
        self::CHECKING_REQUIRMENTS => array(
          "title" => "Application Requirements",
          "progressText" => "Checking requirements, please wait a moment…"
        ),
        self::DATABASE_CONFIGURATION => array(
          "title" => "Database configuration",
          "form" => array(
            array("title" => "Username", "name"  => "username", "type"  => "text", "required" => true),
            array("title" => "Password", "name"  => "password", "type"  => "password"),
            array("title" => "Database", "name"  => "database", "type"  => "text", "required" => true),
            array("type" => "row", "items" => array(
              array(
                "title" => "Address", "name"  => "host", "type"  => "text", "required" => true,
                "value" => "localhost", "row" => true
              ),
              array(
                "title" => "Port", "name"  => "port", "type"  => "number", "required" => true,
                "value" => "3306", "min" => "1", "max" => "65535", "row" => true
              )
            )),
          )
        ),
        self::CREATE_USER => array(
          "title" => "Create a User",
          "form" => array(
            array("title" => "Username", "name"  => "username", "type"  => "text", "required" => true),
            array("title" => "Password", "name"  => "password", "type"  => "password", "required" => true),
            array("title" => "Confirm Password", "name"  => "confirmPassword", "type"  => "password", "required" => true),
          )
        ),
        self::ADD_MAIL_SERVICE => array(
          "title" => "Optional: Add Mail Service",
          "form" => array(
            array("title" => "Username", "name"  => "username", "type"  => "text", "required" => true),
            array("title" => "Password", "name"  => "password", "type"  => "password"),
            array("type" => "row", "items" => array(
              array(
                "title" => "SMTP Address", "name"  => "address", "type"  => "text", "required" => true,
                "value" => "localhost", "row" => true
              ),
              array(
                "title" => "Port", "name"  => "port", "type"  => "number", "required" => true,
                "value" => "587", "min" => "1", "max" => "65535", "row" => true
              )
            )),
          ),
          "skip" => true
        ),
        self::ADD_GOOGLE_SERVICE => array(
          "title" => "Optional: Add Google Services",
        )
      );

      if(!isset($views[$this->currentStep])) {
        return "";
      }

      $prevDisabled = ($this->currentStep <= self::DATABASE_CONFIGURATION);
      $currentView = $views[$this->currentStep];
      $spinnerIcon = $this->createIcon("spinner");
      $title = $currentView["title"];

      $html = "<h4 class=\"mb-3\">$title</h4><hr class=\"mb-4\">";

      if(isset($currentView["progressText"])) {
        $progressText = $currentView["progressText"];
        $html .= "<div id=\"progressText\" class=\"my-3\">$progressText$spinnerIcon</i></div>";
      }

      if(isset($currentView["form"])) {
        $html .= "<form id=\"installForm\">";

        foreach($currentView["form"] as $formItem) {

          if($formItem["type"] === "row") {
            $html .= "<div class=\"row\">";
            foreach($formItem["items"] as $item) {
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
        array("title" => "Go Back", "type" => "info", "id" => "btnPrev", "float" => "left", "disabled" => $prevDisabled),
        array("title" => "Submit", "type" => "success", "id" => "btnSubmit", "float" => "right")
      );

      if(isset($currentView["skip"])) {
        $buttons[] = array("title" => "Skip", "type" => "secondary", "id" => "btnSkip", "float" => "right");
      }

      $buttonsLeft = "";
      $buttonsRight = "";

      foreach($buttons as $button) {
        $title = $button["title"];
        $type = $button["type"];
        $id = $button["id"];
        $float = $button["float"];
        $disabled = (isset($button["disabled"]) && $button["disabled"]) ? " disabled" : "";
        $button = "<button type=\"button\" id=\"$id\" class=\"btn btn-$type margin-xs\"$disabled>$title</button>";

        if($float === "left") {
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


    function getCode() {
      $html = parent::getCode();

      $this->steps = array(
        self::CHECKING_REQUIRMENTS => array(
          "title" => "Checking requirements",
          "status" => self::NOT_STARTED
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
        self::ADD_GOOGLE_SERVICE => array(
          "title" => "Add Google Services",
          "status" => self::NOT_STARTED
        ),
      );

      $this->currentStep = $this->getCurrentStep();

      // set status
      for($step = self::CHECKING_REQUIRMENTS; $step < $this->currentStep; $step++) {
        $this->steps[$step]["status"] = self::SUCCESFULL;
      }

      // POST
      if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $response = $this->performStep();
        die(json_encode($response));
      }

      if($this->currentStep == self::CHECKING_REQUIRMENTS) {
        $this->getDocument()->getHead()->addJSCode("
          $(document).ready(function() {
            retry();
          });
        ");
      }

      $progressSidebar = $this->createProgressSidebar();
      $progressMainview = $this->createProgessMainview();

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
              <div class=\"alert margin-top-m\" id=\"status\" style=\"display:none\"></div>
            </div>
          </div>
        </div>
      </body>";

      return $html;
    }

  }

}

?>
