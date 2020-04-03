<?php


namespace Views\Admin;

use DateTime;
use Elements\Document;

class UserOverview extends AdminView {

  private array $users;
  private int $page;
  private int $pageCount;

  public function __construct(Document $document) {
    parent::__construct($document);
    $this->users = array();
    $this->pageCount = 0;
    $this->page = 1;
  }

  public function loadView() {
    parent::loadView();
    $this->title = L("User Control");
    $this->requestUsers();
  }

  private function requestUsers() {

    if(isset($_GET["page"]) && is_numeric($_GET["page"])) {
      $this->page = intval($_GET["page"]);
    } else {
      $this->page = 1;
    }

    $req = new \Api\User\Fetch($this->getDocument()->getUser());
    if (!$req->execute(array("page" => $this->page))) {
      $this->errorMessages[] = $req->getLastError();
    } else {
      $result = $req->getResult();
      $this->users = $result["users"];
      $this->pageCount = $result["pages"];
    }
  }

  private function getGroups($groups) {
    $badges = [];

    foreach($groups as $groupId => $group) {
      $badgeClass = "secondary";
      if ($groupId === USER_GROUP_ADMIN) {
        $badgeClass = "danger";
      }

      $badges[] = $this->createBadge($badgeClass, $group);
    }

    return implode("&nbsp;", $badges);
  }

  private function getPagination() {

    $userPageNavigation = L("User page navigation");
    $previousDisabled = ($this->page == 1 ? " disabled" : "");
    $nextDisabled = ($this->page >= $this->pageCount ? " disabled" : "");

    $html =
      "<nav aria-label=\"$userPageNavigation\" id=\"userPageNavigation\">
        <ul class=\"pagination p-2 m-0 justify-content-end\">
          <li class=\"page-item$previousDisabled\"><a class=\"page-link\" href=\"#\">Previous</a></li>";

    for($i = 1; $i <= $this->pageCount; $i++) {
      $active = $i === $this->page ? " active" : "";
      $html .=
          "<li class=\"page-item$active\"><a class=\"page-link\" href=\"#\">$i</a></li>";
    }

    $html .=
          "<li class=\"page-item$nextDisabled\"><a class=\"page-link\" href=\"#\">Next</a></li>
        </ul>
      </nav>";

    return $html;
  }

  private function getUserRows() {

    $dateFormat = L("Y/m/d");
    $userRows = array();

    foreach($this->users as $uid => $user) {
      $name = $user["name"];
      $email = $user["email"] ?? "";
      $registeredAt = (new DateTime($user["created_at"]))->format($dateFormat);
      $groups = $this->getGroups($user["groups"]);

      $userRows[] =
        "<tr data-id=\"$uid\">
           <td>$name</td>
           <td>$email</td>
           <td>$groups</td>
           <td>$registeredAt</td>
        </tr>";
    }

    return implode("", $userRows);
  }

  public function getCode() {
    $html = parent::getCode();

    // Icons
    $iconRefresh = $this->createIcon("sync");

    // Locale
    $users = L("Users");
    $name = L("Name");
    $email = L("Email");
    $groups = L("Groups");
    $registeredAt = L("Registered At");

    // Content
    $pagination = $this->getPagination();
    $userRows = $this->getUserRows();

    $html .=
      "<div class=\"content\">
        <div class=\"container-fluid\">
          <div class=\"row\">
            <div class=\"col-lg-12\">
               <div class=\"card\">
                <div class=\"card-header border-0\">
                  <h3 class=\"card-title\">$users</h3>
                  <div class=\"card-tools\">
                    <a href=\"#\" class=\"btn btn-tool btn-sm\" id=\"userTableRefresh\">
                      $iconRefresh
                    </a>
                  </div>
                </div>
                <div class=\"card-body table-responsive p-0\">
                  <table class=\"table table-striped table-valign-middle\" id=\"userTable\">
                    <thead>
                    <tr>
                      <th>$name</th>
                      <th>$email</th>
                      <th>$groups</th>
                      <th>$registeredAt</th>
                    </tr>
                    </thead>
                    <tbody>
                      $userRows
                    </tbody>
                  </table>
                  $pagination
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>";

    return $html;
  }
}