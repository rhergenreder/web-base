<?php

namespace Core\Elements;

use Core\Configuration\Settings;
use Core\Driver\Logger\Logger;
use Core\Driver\SQL\SQL;
use Core\Objects\Captcha\GoogleRecaptchaProvider;
use Core\Objects\Captcha\HCaptchaProvider;
use Core\Objects\Context;
use Core\Objects\Router\DocumentRoute;
use Core\Objects\Router\Router;
use Core\Objects\DatabaseEntity\User;
use Core\Objects\Search\Searchable;
use Core\Objects\Search\SearchQuery;
use Core\Objects\Search\SearchResult;

abstract class Document {

  protected Router $router;
  private Logger $logger;
  protected bool $databaseRequired;
  private bool $cspEnabled;
  private ?string $cspNonce;
  private array $cspWhitelist;
  protected bool $searchable;
  protected array $languageModules;

  public function __construct(Router $router) {
    $this->router = $router;
    $this->cspEnabled = false;
    $this->cspNonce = null;
    $this->databaseRequired = true;
    $this->cspWhitelist = [];
    $this->logger = new Logger("Document", $this->getSQL());
    $this->searchable = false;
    $this->languageModules = ["general"];
  }

  public abstract function getTitle(): string;

  public function isSearchable(): bool {
    return $this->searchable;
  }

  public function getLogger(): Logger {
    return $this->logger;
  }

  public function getUser(): ?User {
    return $this->getContext()->getUser();
  }

  public function getContext(): Context {
    return $this->router->getContext();
  }

  public function getSQL(): ?SQL {
    return $this->getContext()->getSQL();
  }

  public function getSettings(): Settings {
    return $this->getContext()->getSettings();
  }

  public function getCSPNonce(): ?string {
    return $this->cspNonce;
  }

  public function isCSPEnabled(): bool {
    return $this->cspEnabled;
  }

  public function enableCSP() {
    $this->cspEnabled = true;
    $this->cspNonce = generateRandomString(16, "base62");
  }

  public function getRouter(): Router {
    return $this->router;
  }

  public function addCSPWhitelist(string $path): void {
    $urlParts = parse_url($path);
    if (!$urlParts || !isset($urlParts["host"])) {
      $this->cspWhitelist[] = getProtocol() . "://" . getCurrentHostName() . $path;
    } else {
      $this->cspWhitelist[] = $path;
    }
  }

  public function sendHeaders(): void {
    if ($this->cspEnabled) {
      $frameSrc = [];

      $captchaProvider = $this->getSettings()->getCaptchaProvider();
      if ($captchaProvider instanceof GoogleRecaptchaProvider) {
        $frameSrc[] = "https://www.google.com/recaptcha/";
        $frameSrc[] = "https://recaptcha.google.com/recaptcha/";
        $this->cspWhitelist[] = "https://www.google.com/recaptcha/";
        $this->cspWhitelist[] = "https://www.gstatic.com/recaptcha/";
      } else if ($captchaProvider instanceof HCaptchaProvider) {
        $frameSrc[] = "https://hcaptcha.com";
        $frameSrc[] = "https://*.hcaptcha.com";
        $this->cspWhitelist[] = "https://hcaptcha.com";
        $this->cspWhitelist[] = "https://*.hcaptcha.com";
      }

      $cspWhiteList = implode(" ", $this->cspWhitelist);
      $frameSrc = implode(" ", $frameSrc);
      $csp = [
        "default-src $cspWhiteList 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' 'unsafe-inline' data: https:;",
        "script-src $cspWhiteList 'nonce-$this->cspNonce'",
        "frame-ancestors 'self'",
        "frame-src $frameSrc 'self'",
      ];

      $compiledCSP = implode("; ", $csp);
      header("Content-Security-Policy: $compiledCSP;");
    }

    // additional security headers
    header("X-XSS-Protection: 0"); // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-XSS-Protection
    header("X-Content-Type-Options: nosniff");

    if (getProtocol() === "https") {
      $maxAge = 365 * 24 * 60 * 60; // 1 year in seconds
      header("Strict-Transport-Security: max-age=$maxAge; includeSubDomains; preload");
    }
  }

  public abstract function getCode(array $params = []);

  public function load(array $params = []): string {

    if ($this->databaseRequired) {
      $sql = $this->getSQL();
      if (is_null($sql)) {
        return "Database is not configured yet.";
      } else if (!$sql->isConnected()) {
        return "Database is not connected: " . $sql->getLastError();
      }
    }

    $language = $this->getContext()->getLanguage();
    foreach ($this->languageModules as $module) {
      $language->loadModule($module);
    }

    $code = $this->getCode($params);
    $this->sendHeaders();
    return $code;
  }

  public function doSearch(SearchQuery $query, DocumentRoute $route): array {
    $code = $this->getCode();
    $results = Searchable::searchHtml($code, $query);
    return array_map(function ($res) use ($route) {
      return new SearchResult($route->getUrl(), $this->getTitle(), $res["text"]);
    }, $results);
  }

  public function createScript($type, $src, $content = ""): Script {
    $script = new Script($type, $src, $content);

    if ($this->isCSPEnabled()) {
      $script->setNonce($this->getCSPNonce());
    }

    return $script;
  }
}