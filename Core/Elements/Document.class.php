<?php

namespace Core\Elements;

use Core\Configuration\Settings;
use Core\Driver\Logger\Logger;
use Core\Driver\SQL\SQL;
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
  private string $domain;
  protected bool $searchable;
  protected array $languageModules;

  public function __construct(Router $router) {
    $this->router = $router;
    $this->cspEnabled = false;
    $this->cspNonce = null;
    $this->databaseRequired = true;
    $this->cspWhitelist = [];
    $this->domain = $this->getSettings()->getBaseUrl();
    $this->logger = new Logger("Document", $this->getSQL());
    $this->searchable = false;
    $this->languageModules = [];
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

  public function addCSPWhitelist(string $path) {
    $urlParts = parse_url($path);
    if (!$urlParts || !isset($urlParts["host"])) {
      $this->cspWhitelist[] = $this->domain . $path;
    } else {
      $this->cspWhitelist[] = $path;
    }
  }

  public function sendHeaders() {
    if ($this->cspEnabled) {
      $cspWhiteList = implode(" ", $this->cspWhitelist);
      $csp = [
        "default-src $cspWhiteList 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' 'unsafe-inline' data: https:;",
        "script-src $cspWhiteList 'nonce-$this->cspNonce'"
      ];
      if ($this->getSettings()->isRecaptchaEnabled()) {
        $csp[] = "frame-src https://www.google.com/ 'self'";
      }

      $compiledCSP = implode("; ", $csp);
      header("Content-Security-Policy: $compiledCSP;");
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