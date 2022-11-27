<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class TemplateAPI extends Request {
    function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
      $this->isPublic = false; // internal API
    }
  }

}

namespace Core\API\Template {

  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\TemplateAPI;
  use Core\Objects\Context;
  use Twig\Environment;
  use Twig\Error\LoaderError;
  use Twig\Error\RuntimeError;
  use Twig\Error\SyntaxError;
  use Twig\Loader\FilesystemLoader;

  class Render extends TemplateAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "file" => new StringType("file"),
        "parameters" => new ArrayType("parameters", Parameter::TYPE_MIXED, false, true, [])
      ]);
    }

    public function _execute(): bool {
      $templateFile = $this->getParam("file");
      $parameters   = $this->getParam("parameters");
      $extension = pathinfo($templateFile, PATHINFO_EXTENSION);
      $allowedExtensions = ["html", "twig"];

      if (!in_array($extension, $allowedExtensions)) {
        return $this->createError("Invalid template file extension. Allowed: " . implode(",", $allowedExtensions));
      }

      $templateCache = WEBROOT . "/Core/Cache/Templates/";
      $baseDirs = ["Site", "Core"];
      $valid = false;

      foreach ($baseDirs as $baseDir) {
        $templateDir = realpath(implode("/", [WEBROOT, $baseDir, "Templates"]));
        if ($templateDir) {
          $path = realpath(implode("/", [$templateDir, $templateFile]));
          if ($path && is_file($path)) {
            $valid = true;
            break;
          }
        }
      }

      if (!$valid) {
        return $this->createError("Template file not found or not inside template directory");
      }

      $twigLoader = new FilesystemLoader($templateDir);
      $twigEnvironment = new Environment($twigLoader, [
        'cache' => $templateCache,
        'auto_reload' => true
      ]);

      try {
        $this->result["html"] = $twigEnvironment->render($templateFile, $parameters);
      } catch (LoaderError | RuntimeError | SyntaxError $e) {
        return $this->createError("Error rendering twig template: " . $e->getMessage());
      }

      return true;
    }

  }

}