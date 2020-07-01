<?php

namespace Views\Account;

use Elements\Document;
use Elements\View;

abstract class AccountView extends View {

  protected string $description;
  protected string $icon;

  public function __construct(Document $document, $loadView = true) {
    parent::__construct($document, $loadView);
    $this->description = "";
    $this->icon = "image";
  }

  public function loadView() {
    parent::loadView();

    $document = $this->getDocument();
    $settings = $document->getUser()->getConfiguration()->getSettings();
    if ($settings->isRecaptchaEnabled()) {
      $document->getHead()->loadGoogleRecaptcha($settings->getRecaptchaSiteKey());
    }
  }

  public function getCode() {
    $html = parent::getCode();

    $content = $this->getAccountContent();
    $icon = $this->createIcon($this->icon, "fas", "fa-3x");

    $html .= "<div class=\"container mt-5\">
        <div class=\"row\">
          <div class=\"col-md-4 py-5 bg-primary text-white text-center\" style='border-top-left-radius:.4em;border-bottom-left-radius:.4em'>
            <div class=\"card-body\">
              $icon
              <h2 class=\"py-3\">$this->title</h2>
              <p>$this->description</p>
            </div>
          </div>
          <div class=\"col-md-8 pt-5 pb-2 border border-info\" style='border-top-right-radius:.4em;border-bottom-right-radius:.4em'>
            $content
            <div class='alert mt-2' style='display:none' id='alertMessage'></div>
          </div>
        </div>
      </div>";

    $settings = $this->getDocument()->getUser()->getConfiguration()->getSettings();
    if ($settings->isRecaptchaEnabled()) {
      $siteKey = $settings->getRecaptchaSiteKey();
      $html .= "<input type='hidden' value='$siteKey' id='siteKey' />";
    }

    return $html;
  }

  protected abstract function getAccountContent();
}