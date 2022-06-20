<?php

namespace Elements;

use Objects\Router\Router;
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

  public function __construct(Router $router, string $templateName, array $params = []) {
    parent::__construct($router);
    $this->title = "";
    $this->templateName = $templateName;
    $this->parameters = $params;
    $this->twigLoader = new FilesystemLoader(WEBROOT . '/core/Templates');
    $this->twigEnvironment = new Environment($this->twigLoader, [
      'cache' => WEBROOT . '/core/Cache/Templates/',
      'auto_reload' => true
    ]);
  }

  protected function getTemplateName(): string {
    return $this->templateName;
  }

  protected function loadParameters() {

  }

  public function getCode(array $params = []): string {
    parent::getCode($params);
    $this->loadParameters();
    return $this->renderTemplate($this->templateName, $this->parameters);
  }

  public function renderTemplate(string $name, array $params = []): string {
    try {

      $context = $this->getContext();
      $session = $context->getSession();
      $params["user"] = [
        "lang" => $context->getLanguage()->getShortCode(),
        "loggedIn" => $session !== null,
        "session" => ($session ? [
          "csrfToken" => $session->getCsrfToken()
        ] : null)
      ];

      $settings = $this->getSettings();
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