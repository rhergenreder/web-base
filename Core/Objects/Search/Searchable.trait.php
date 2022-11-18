<?php

namespace Core\Objects\Search;

use Html2Text\Html2Text;
use Core\Objects\Context;
use function PHPUnit\Framework\stringContains;

trait Searchable {

  public static function searchArray(array $arr, SearchQuery $query): array {
    $results = [];
    foreach ($arr as $key => $value) {
      $results = array_merge($results, self::searchHtml($key, $query));
      if (is_array($value)) {
        $results = array_merge($results, self::searchArray($value, $query));
      } else {
        $results = array_merge($results, self::searchHtml(strval($value), $query));
      }
    }

    return $results;
  }

  public abstract function doSearch(Context $context, SearchQuery $query): array;

  public static function searchHtml(string $document, SearchQuery $query): array {
    if (stringContains($document, "<")) {
      $converter = new Html2Text($document);
      $text = trim($converter->getText());
    } else {
      $text = $document;
    }

    $text = trim(preg_replace('!\s+!', ' ', $text));
    return self::searchText($text, $query);
  }

  public static function searchText(string $content, SearchQuery $query): array {
    $offset = 0;
    $searchTerm = $query->getQuery();
    $stringLength = strlen($searchTerm);
    $contentLength = strlen($content);
    $lastPos = 0;

    $results = [];
    do {
      $pos = stripos($content, $searchTerm, $offset);
      if ($pos !== false) {
        if ($lastPos === 0 || $pos > $lastPos + 192 + $stringLength) {
          $extract = self::viewExtract($content, $pos, $searchTerm);
          $results[] = array(
            "text" => $extract,
            "pos" => $pos,
            "lastPos" => $lastPos
          );
          $lastPos = $pos;
        }

        $offset = $pos + $stringLength;
      }
    } while ($pos !== false && $offset < $contentLength);

    return $results;
  }

  private static function viewExtract(string $content, int $pos, $string): array|string|null {
    $length = strlen($string);
    $start = max(0, $pos - 32);
    $end = min(strlen($content) - 1, $pos + $length + 192);

    if ($start > 0) {
      if (!ctype_space($content[$start - 1]) &&
        !ctype_space($content[$start])) {
        $start = $start + strpos(substr($content, $start, $end), ' ');
      }
    }

    if ($end < strlen($content) - 1) {
      if (!ctype_space($content[$end + 1]) &&
        !ctype_space($content[$end])) {
        $end = $start + strrpos(substr($content, $start, $end - $start), ' ');
      }
    }

    $extract = trim(substr($content, $start, $end - $start + 1));
    if ($start > 0) $extract = ".. " . $extract;
    if ($end < strlen($content) - 1) $extract .= " ..";
    return preg_replace("/" . preg_quote($string) . "(?=[^>]*(<|$))/i", "<span class=\"highlight\">\$0</span>", $extract);
  }
}