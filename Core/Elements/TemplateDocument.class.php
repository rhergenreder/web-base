<?php

namespace Core\Elements;

use Core\Objects\CustomTwigFunctions;
use Core\Objects\Router\DocumentRoute;
use Core\Objects\Router\Router;
use Core\Objects\Search\Searchable;
use Core\Objects\Search\SearchQuery;
use Core\Objects\Search\SearchResult;
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

  private static function getTemplatePaths(): array {
    return [
      implode(DIRECTORY_SEPARATOR, [WEBROOT, 'Site', 'Templates']),
      implode(DIRECTORY_SEPARATOR, [WEBROOT, 'Core', 'Templates']),
    ];
  }

  private static function getTemplatePath(string $templateName): ?string {
    foreach (self::getTemplatePaths() as $path) {
      $filePath = implode(DIRECTORY_SEPARATOR, [$path, $templateName]);
      if (is_file($filePath)) {
        return $filePath;
      }
    }
    return null;
  }

  public function __construct(Router $router, string $templateName, array $params = []) {
    parent::__construct($router);
    $this->title = "Untitled Document";
    $this->templateName = $templateName;
    $this->parameters = $params;
    $this->twigLoader = new FilesystemLoader(self::getTemplatePaths());
    $this->twigEnvironment = new Environment($this->twigLoader, [
      'cache' => WEBROOT . '/Site/Cache/Templates/',
      'auto_reload' => true
    ]);
    $this->twigEnvironment->addExtension(new CustomTwigFunctions());
  }

  public function getTitle(): string {
    return $this->title;
  }

  protected function getTemplateName(): string {
    return $this->templateName;
  }

  protected function loadParameters() {

  }

  protected function setTemplate(string $file) {
    $this->templateName = $file;
  }

  public function getCode(array $params = []): string {
    $this->loadParameters();
    return $this->renderTemplate($this->templateName, $this->parameters);
  }

  public function renderTemplate(string $name, array $params = []): string {
    try {

      $context = $this->getContext();
      $session = $context->getSession();
      $settings = $this->getSettings();
      $language = $context->getLanguage();
      $captchaProvider = $settings->getCaptchaProvider();

      $urlParts = parse_url($this->getRouter()->getRequestedUri());

      $params = array_replace_recursive([
        "user" => [
          "lang" => $language->getShortCode(),
          "loggedIn" => $session !== null,
          "session" => ($session ? [
            "csrfToken" => $session->getCsrfToken()
          ] : null)
        ],
        "site" => [
          "name" => $settings->getSiteName(),
          "url" => [
            "base" => $settings->getBaseUrl(),
            "path" => $urlParts["path"] ?? "" ,
            "query" => $urlParts["query"] ?? "",
            "fragment" => $urlParts["fragment"] ?? ""
          ],
          "lastModified" => date(L('general.date_time_format'), @filemtime(self::getTemplatePath($name))),
          "registrationEnabled" => $settings->isRegistrationAllowed(),
          "title" => $this->title,
          "captcha" => [
            "provider" => $captchaProvider?->getName(),
            "site_key" => $captchaProvider?->getSiteKey(),
            "enabled" => $captchaProvider !== null,
          ],
          "csp" => [
            "nonce" => $this->getCSPNonce(),
            "enabled" => $this->isCSPEnabled()
          ],
          "language" => [
            "code" => $language->getCode(),
            "shortCode" => $language->getShortCode(),
            "name" => $language->getName(),
            "entries" => $language->getEntries()
          ]
        ]
      ], $params);
      return $this->twigEnvironment->render($name, $params);
    } catch (LoaderError | RuntimeError | SyntaxError $e) {
      return "<b>Error rendering twig template: " . htmlspecialchars($e->getMessage()) . "</b>";
    }
  }

  protected function loadView(string $class): array {
    $view = new $class($this);
    $view->loadParameters($this->parameters);
    if ($view->getTitle()) {
      $this->title = $view->getTitle();
    }
    return $this->parameters;
  }

  public function doSearch(SearchQuery $query, DocumentRoute $route): array {
    $this->loadParameters();
    $viewParams = $this->parameters["view"] ?? [];
    $siteTitle = $this->parameters["site"]["title"] ?? $this->title;
    $results = Searchable::searchArray($viewParams, $query);
    return array_map(function ($res) use ($siteTitle, $route) {
      return new SearchResult($route->getUrl(), $siteTitle, $res["text"]);
    }, $results);
  }
}