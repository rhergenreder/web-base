<?php


namespace Views\Account;


use Elements\Document;
use Elements\Script;

class ConfirmEmail extends AccountView {

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Confirm Email";
    $this->description = "Request a password reset, once you got the e-mail address, you can choose a new password";
    $this->icon = "user-check";
  }

  public function loadView() {
    parent::loadView();
    $this->getDocument()->getHead()->addScript(Script::MIME_TEXT_JAVASCRIPT, "", '
      $(document).ready(function() {
         var token = jsCore.getParameter("token");
         if (token) {
           jsCore.apiCall("/user/confirmEmail", { token: token }, (res) => {
              $("#confirm-status").removeClass("alert-info");
              if (!res.success) {
                  $("#confirm-status").addClass("alert-danger");
                  $("#confirm-status").text("Error confirming e-mail address: " + res.msg);
              } else {
                  $("#confirm-status").addClass("alert-success");
                  $("#confirm-status").text("Your e-mail address was successfully confirmed, you may now log in.");
              }
          });
        } else {
          $("#confirm-status").removeClass("alert-info");
          $("#confirm-status").addClass("alert-danger");
          $("#confirm-status").text("The link you visited is no longer valid");
        }
      });'
    );
  }

  protected function getAccountContent() {

    $spinner = $this->createIcon("spinner");
    $html = "<noscript><div class=\"alert alert-danger\">Javascript is required</div></noscript>
             <div class=\"alert alert-info\" id=\"confirm-status\">
                Confirming email… $spinner
             </div>";

    $html .= "<a href='/login'><button class='btn btn-primary' style='position: absolute; bottom: 10px' type='button'>Proceed to Login</button></a>";
    return $html;
  }
}