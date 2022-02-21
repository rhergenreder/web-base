<?php

namespace Elements;

use Objects\User;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class TemplateDocument extends Document {

  private string $templateName;
  protected array $parameters;
  private Environment $twigEnvironment;
  private FilesystemLoader $twigLoader;
  protected string $title;

  public function __construct(User $user, string $templateName, array $initialParameters = []) {
    parent::__construct($user);
    $this->title = "";
    $this->templateName = $templateName;
    $this->parameters = $initialParameters;
    $this->twigLoader = new FilesystemLoader(WEBROOT . '/core/Templates');
    $this->twigEnvironment = new Environment($this->twigLoader, [
      'cache' => WEBROOT . '/core/TemplateCache',
      'auto_reload' => true
    ]);
  }

  protected function getTemplateName(): string {
    return $this->templateName;
  }

  protected function loadParameters() {

  }

  public function getCode(): string {
    parent::getCode();
    $this->loadParameters();
    return $this->renderTemplate($this->templateName, $this->parameters);
  }

  public function renderTemplate(string $name, array $params = []): string {
    try {

      $params["user"] = [
        "lang" => $this->user->getLanguage()->getShortCode(),
        "loggedIn" => $this->user->isLoggedIn(),
        "session" => (!$this->user->isLoggedIn() ? null : [
          "csrfToken" => $this->user->getSession()->getCsrfToken()
        ])
      ];

      $settings = $this->user->getConfiguration()->getSettings();
      $params["site"] = [
        "name" => $settings->getSiteName(),
        "baseUrl" => $settings->getBaseUrl(),
        "registrationEnabled" => $settings->isRegistrationAllowed(),
        "title" => $this->title,
        "recaptcha" => [
          "key" => $settings->isRecaptchaEnabled() ? $settings->getRecaptchaSiteKey() : null,
          "enabled" => $settings->isRecaptchaEnabled(),
        ],
        "csp" => [
          "nonce" => $this->getCSPNonce(),
          "enabled" => $this->isCSPEnabled()
        ]
      ];

      return $this->twigEnvironment->render($name, $params);
    } catch (LoaderError | RuntimeError | SyntaxError $e) {
      return "<b>Error rendering twig template: " . htmlspecialchars($e->getMessage()) . "</b>";
    }
  }
}