<?php

namespace Views\Admin;

// Source: https://adminlte.io/themes/v3/

use Documents\Document404\Body404;
use Elements\Body;
use Elements\Script;
use Elements\View;
use Views\View404;

class AdminDashboardBody extends Body {

  private array $errorMessages;
  private array $notifications;

  public function __construct($document) {
    parent::__construct($document);
    $this->errorMessages = array();
    $this->notifications = array();
  }

  private function getNotifications() : array {
    $req = new \Api\Notifications\Fetch($this->getDocument()->getUser());
    if(!$req->execute()) {
      $this->errorMessages[] = $req->getLastError();
      return array();
    } else {
      return $req->getResult()['notifications'];
    }
  }

  private function getHeader() {

    // Locale
    $home = L("Home");
    $search = L("Search");

    // Icons
    $iconMenu = $this->createIcon("bars");
    $iconNotification = $this->createIcon("bell", "far");
    $iconSearch = $this->createIcon("search");
    $iconMail = $this->createIcon("envelope", "fas");

    // Notifications
    $numNotifications = count($this->notifications);
    if ($numNotifications === 0) {
      $notificationText = L("No new notifications");
    } else if($numNotifications === 1) {
      $notificationText = L("1 new notification");
    } else {
      $notificationText = sprintf(L("%d new notification"), $numNotifications);
    }

    $html =
      "<nav class=\"main-header navbar navbar-expand navbar-white navbar-light\">

        <!-- Left navbar links -->
        <ul class=\"navbar-nav\">
          <li class=\"nav-item\">
            <a class=\"nav-link\" data-widget=\"pushmenu\" href=\"#\" role=\"button\">$iconMenu</a>
          </li>
          <li class=\"nav-item d-none d-sm-inline-block\">
            <a href=\"/\" class=\"nav-link\">$home</a>
          </li>
        </ul>

        <!-- SEARCH FORM -->
        <form class=\"form-inline ml-3\">
          <div class=\"input-group input-group-sm\">
            <input class=\"form-control form-control-navbar\" type=\"search\" placeholder=\"$search\" aria-label=\"$search\">
            <div class=\"input-group-append\">
              <button class=\"btn btn-navbar\" type=\"submit\">
                $iconSearch
              </button>
            </div>
          </div>
        </form>

        <!-- Right navbar links -->
        <ul class=\"navbar-nav ml-auto\">
          <!-- Notifications Dropdown Menu -->
          <li class=\"nav-item dropdown\">
            <a class=\"nav-link\" data-toggle=\"dropdown\" href=\"#\">
              $iconNotification
              <span class=\"badge badge-warning navbar-badge\">$numNotifications</span>
            </a>
            <div class=\"dropdown-menu dropdown-menu-lg dropdown-menu-right\">
              <span class=\"dropdown-item dropdown-header\">$notificationText</span>
              <div class=\"dropdown-divider\"></div>";

    // Notifications
    $i = 0;
    foreach($this->notifications as $notification) {

      $title = $notification["title"];
      $notificationId = $notification["uid"];
      $createdAt = getPeriodString($notification["created_at"]);

      if ($i > 0) {
        $html .= "<div class=\"dropdown-divider\"></div>";
      }

      $html .=
        "<a href=\"#\" class=\"dropdown-item\" data-id=\"$notificationId\">
            $iconMail<span class=\"ml-2\">$title</span>
            <span class=\"float-right text-muted text-sm\">$createdAt</span>
        </a>";

      $i++;
      if ($i >= 5) {
        break;
      }
    }

    $html .= "<a href=\"#\" class=\"dropdown-item dropdown-footer\">See All Notifications</a>
            </div>
          </li>
        </ul>
      </nav>";

    return $html;
  }

  private function getSidebar() {

    $logout = L("Logout");
    $iconLogout = $this->createIcon("arrow-left", "fas", "nav-icon");

    $menuEntries = array(
      "dashboard" => array(
        "name" => "Dashboard",
        "icon" => "tachometer-alt"
      ),
      "users" => array(
        "name" => "Users",
        "icon" => "users"
      ),
      "settings" => array(
        "name" => "Settings",
        "icon" => "tools"
      ),
      "help" => array(
        "name" => "Help",
        "icon" => "question-circle"
      ),
    );

    $notificationCount = count($this->notifications);
    if ($notificationCount > 0) {
      $menuEntries["dashboard"]["badge"] = array("type" => "warning", "value" => $notificationCount);
    }

    $currentView = $_GET["view"] ?? "dashboard";

    $html =
      "<aside class=\"main-sidebar sidebar-dark-primary elevation-4\">
        <!-- Brand Logo -->
        <a href=\"/admin\" class=\"brand-link\">
          <img src=\"/img/web_base_logo.png\" alt=\"WebBase Logo\" class=\"brand-image img-circle elevation-3\"
               style=\"opacity: .8\">
          <span class=\"brand-text font-weight-light\">WebBase</span>
        </a>

        <!-- Sidebar -->
        <div class=\"sidebar\">

          <!-- Sidebar Menu -->
          <nav class=\"mt-2\">
            <ul class=\"nav nav-pills nav-sidebar flex-column\" data-widget=\"treeview\" role=\"menu\" data-accordion=\"false\">";

    foreach($menuEntries as $view => $menuEntry) {
      $name = L($menuEntry["name"]);
      $icon = $this->createIcon($menuEntry["icon"], "fas", "nav-icon");
      $active = ($currentView === $view) ? " active" : "";
      $badge = $menuEntry["badge"] ?? "";
      if($badge) {
        $badgeType = $badge["type"];
        $badgeValue = $badge["value"];
        $badge = "<span class=\"badge badge-$badgeType right\">$badgeValue</span>";
      }

      $html .=
              "<li class=\"nav-item\">
                <a href=\"?view=$view\" class=\"nav-link$active\">
                  $icon<p>$name$badge</p>
                </a>
              </li>";
    }

    $html .= "<li class=\"nav-item\">
                <a href=\"#\" id=\"btnLogout\" class=\"nav-link\">
                  $iconLogout<p>$logout</p>
                </a>
              </li>
            </ul>
          </nav>
        </div>
      </aside>";

    return $html;
  }

  private function getView() {

    $views = array(
      "dashboard" => Dashboard::class,
      "users" => UserOverview::class,
      "404" => View404::class,
    );

    $currentView = $_GET["view"] ?? "dashboard";
    if (!isset($views[$currentView])) {
      $currentView = "404";
    }

    $view = new $views[$currentView]($this->getDocument());
    assert($view instanceof View);
    $code = $view->getCode();

    if ($view instanceof AdminView) {
      $this->errorMessages = array_merge($this->errorMessages, $view->getErrorMessages());
    }

    return $code;
  }

  public function loadView() {
    parent::loadView();

    $head = $this->getDocument()->getHead();
    // $head->addJS("/js/admin.min.js");
    // $head->loadAdminlte();

    // $this->notifications = $this->getNotifications();
  }

  private function getContent() {

    $view = $this->getView();
    $html = "<div class=\"content-wrapper p-2\">";

    foreach($this->errorMessages as $errorMessage) {
      $html .= $this->createErrorText($errorMessage);
    }

    $html .= $view;
    $html .= "</div>";
    return $html;
  }

  public function getCode() {
    $html = parent::getCode();

    // $this->getDocument()->getHead()->addJS("/js/admin.min.js");

    /*
    $header = $this->getHeader();
    $sidebar = $this->getSidebar();
    $content = $this->getContent();


    $html .=
      "<!-- LICENSE: /docs/LICENSE_ADMINLTE -->
      <body class=\"hold-transition sidebar-mini layout-fixed\">
          <div class=\"wrapper\">
            $header
            $sidebar
            $content
          </div>
      </body>";
    */

    $script = new Script(Script::MIME_TEXT_JAVASCRIPT, "/js/admin.min.js");
    $html .= "<body id=\"root\">$script</body>";
    return $html;
  }
}
