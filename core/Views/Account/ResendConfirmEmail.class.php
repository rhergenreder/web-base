<?php


namespace Views\Account;


use Elements\Document;

class ResendConfirmEmail extends AccountView {

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->title = "Resend Confirm Email";
    $this->description = "Request a new confirmation email to finalize the account creation";
    $this->icon = "envelope";
  }

  protected function getAccountContent() {
    return  "<p class='lead'>Enter your E-Mail address, to receive a new e-mail to confirm your registration.</p>
          <form>
        <div class=\"input-group\">
          <div class=\"input-group-append\">
            <span class=\"input-group-text\"><i class=\"fas fa-at\"></i></span>  
          </div>
          <input id=\"email\" autocomplete='email' name=\"email\" placeholder=\"E-Mail address\" class=\"form-control\" type=\"email\" maxlength=\"64\" />
        </div>
        <div class=\"input-group mt-2\" style='position: absolute;bottom: 15px'>
          <button id='btnResendConfirmEmail' class='btn btn-primary'>
            Request
          </button>
          <a href='/login' style='margin-left: 10px'>
            <button class='btn btn-secondary' type='button'>
              Back to Login
            </button>
          </a>
        </div>
      ";
  }
}