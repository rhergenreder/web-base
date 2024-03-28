<?php

namespace Core\Objects\Router;

use Core\Driver\SQL\SQL;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Route;
use Core\Objects\Search\Searchable;
use Core\Objects\Search\SearchQuery;
use Core\Objects\Search\SearchResult;
use JetBrains\PhpStorm\Pure;

class StaticFileRoute extends Route {

  use Searchable;

  #[Transient]
  private int $code;

  public function __construct(string $pattern, bool $exact, string $path, int $code = 200) {
    parent::__construct("static", $pattern, $path, $exact);
    $this->code = $code;
    $this->extra = json_encode($this->code);
  }

  protected function readExtra() {
    parent::readExtra();
    $this->code = json_decode($this->extra);
  }

  public function preInsert(array &$row) {
    parent::preInsert($row);
    $this->extra = json_encode($this->code);
  }

  public function call(Router $router, array $params): string {
    http_response_code($this->code);
    $this->serveStatic($this->getAbsolutePath(), $router);
    return "";
  }

  #[Pure] private function getPath(): string {
    return $this->getTarget();
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->getPath(), $this->code]);
  }

  public function getAbsolutePath(): string {
    return WEBROOT . DIRECTORY_SEPARATOR . $this->getPath();
  }

  public static function serveStatic(string $path, ?Router $router = null) {
    if (!startsWith($path, WEBROOT . DIRECTORY_SEPARATOR)) {
      http_response_code(406);
      echo "<b>Access restricted, requested file outside web root:</b> " . htmlspecialchars($path);
    }

    if (!file_exists($path) || !is_file($path) || !is_readable($path)) {
      http_response_code(500);
      echo "<b>Unable to read file:</b> " . htmlspecialchars($path);
    }

    $pathInfo = pathinfo($path);
    if ($router !== null) {
      $ext = $pathInfo["extension"] ?? "";
      if (!$router->getContext()->getSettings()->isExtensionAllowed($ext)) {
        http_response_code(406);
        echo "<b>Access restricted:</b> Extension '" . htmlspecialchars($ext) . "' not allowed to serve.";
      }
    }

    $size = filesize($path);
    $mimeType = mime_content_type($path);
    header("Content-Type: $mimeType");
    header("Content-Length: $size");
    header('Accept-Ranges: bytes');

    if (strcasecmp($_SERVER["REQUEST_METHOD"], "HEAD") !== 0) {
      $handle = fopen($path, "rb");
      if ($handle === false) {
        http_response_code(500);
        echo "<b>Unable to read file:</b> " . htmlspecialchars($path);
      }

      $offset = 0;
      $length = $size;

      if (isset($_SERVER['HTTP_RANGE'])) {
        preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
        $offset = intval($matches[1]);
        $length = intval($matches[2]) - $offset;
        http_response_code(206);
        header('Content-Range: bytes ' . $offset . '-' . ($offset + $length) . '/' . $size);
      }

      downloadFile($handle, $offset, $length);
    }
  }

  public function doSearch(Context $context, SearchQuery $query): array {

    $results = [];
    $path = $this->getAbsolutePath();
    if (is_file($path) && is_readable($path)) {
      $pathInfo = pathinfo($path);
      $extension = $pathInfo["extension"] ?? "";
      $fileName = $pathInfo["filename"] ?? "";
      if ($context->getSettings()->isExtensionAllowed($extension)) {
        $mimeType = mime_content_type($path);
        if (startsWith($mimeType, "text/")) {
          $document = @file_get_contents($path);
          if ($document) {
            if ($mimeType === "text/html") {
              $results = Searchable::searchHtml($document, $query);
            } else {
              $results = Searchable::searchText($document, $query);
            }
          }
        }
      }

      $results = array_map(function ($res) use ($fileName) {
        return new SearchResult($this->getPattern(), $fileName, $res["text"]);
      }, $results);
    }

    return $results;
  }
}