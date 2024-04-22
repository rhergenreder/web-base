<?php

namespace Core\Documents;


use Core\Configuration\Settings;
use Core\Elements\Document;
use Core\Objects\DatabaseEntity\GpgKey;
use Core\Objects\DatabaseEntity\Language;
use Core\Objects\Router\Router;
use DateTimeInterface;

// Source: https://www.rfc-editor.org/rfc/rfc9116
class Security extends Document {

  public function __construct(Router $router) {
    parent::__construct($router);
    $this->searchable = false;
  }

  public function getTitle(): string {
    return "security.txt";
  }

  public function getCode(array $params = []) {

    $activeRoute = $this->router->getActiveRoute();

    $sql = $this->getContext()->getSQL();
    $settings = $this->getSettings();
    $mailSettings = Settings::getAll($sql, "^mail_");

    if ($activeRoute->getPattern() === "/.well-known/security.txt") {

      // The order in which they appear is not an indication of priority; the listed languages are intended to have equal priority.
      $languageCodes = implode(", ", array_map(function (Language $language) {
        return $language->getShortCode();
      }, Language::findAll($sql)));

      $expires = (new \DateTime())->setTime(0, 0, 0)->modify("+3 months");
      $baseUrl = $settings->getBaseUrl();
      $gpgKey = null;

      $lines = [
        "# This project is based on the open-source framework hosted on https://github.com/rhergenreder/web-base",
        "# Any non site-specific issues can be reported via the github security reporting feature:",
        "# https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing/privately-reporting-a-security-vulnerability",
        "# or by contacting me directly: mail(at)romanh(dot)de",
        "",
        "Canonical: $baseUrl/.well-known/security.txt",
        "Preferred-Languages: $languageCodes",
        "Expires: " . $expires->format(DateTimeInterface::ATOM),
        "",
      ];

      if (isset($mailSettings["mail_contact"])) {
        $lines[] = "Contact: " . $mailSettings["mail_contact"];

        if (isset($mailSettings["mail_contact_gpg_key_id"])) {
          $gpgKey = GpgKey::find($sql, $mailSettings["mail_contact_gpg_key_id"]);
          if ($gpgKey) {
            $lines[] = "Encryption: $baseUrl/.well-known/gpg-key.txt";
          }
        }
      }

      $code = implode("\n", $lines);

      if ($gpgKey !== null) {
        $res = GpgKey::sign($code, $gpgKey->getFingerprint());
        if ($res["success"]) {
          $code = $res["data"];
        }
      }

      return $code;
    } else if ($activeRoute->getPattern() === "/.well-known/gpg-key.txt") {

      if (isset($mailSettings["mail_contact_gpg_key_id"])) {
        $gpgKey = GpgKey::find($sql, $mailSettings["mail_contact_gpg_key_id"]);
        if ($gpgKey !== null) {
          header("Content-Type: text/plain");
          $res = $gpgKey->_export(true);
          if ($res["success"]) {
            return $res["data"];
          } else {
            return "Error exporting public key: " . $res["msg"];
          }
        }
      } else {
        http_response_code(412);
        return "No gpg key configured yet.";
      }
    }

    http_response_code(404);
    return "";
  }

  public function sendHeaders(): void {
    parent::sendHeaders();
    header("Content-Type: text/plain");
  }
}