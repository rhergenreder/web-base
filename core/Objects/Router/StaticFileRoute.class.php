<?php

namespace Objects\Router;

class StaticFileRoute extends AbstractRoute {

  private string $path;
  private int $code;

  public function __construct(string $pattern, bool $exact, string $path, int $code = 200) {
    parent::__construct($pattern, $exact);
    $this->path = $path;
    $this->code = $code;
  }

  public function call(Router $router, array $params): string {
    http_response_code($this->code);
    $this->serveStatic($this->path, $router);
    return "";
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->path, $this->code]);
  }

  public static function serveStatic(string $path, ?Router $router = null) {

    $path = realpath(WEBROOT . DIRECTORY_SEPARATOR . $path);
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
}