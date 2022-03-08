<?php

namespace Api {

  use Objects\User;

  abstract class TemplateAPI extends Request {
    function __construct(User $user, bool $externalCall = false, array $params = array()) {
      parent::__construct($user, $externalCall, $params);
      $this->isPublic = false; // internal API
    }
  }

}

namespace Api\Template {

  use Api\Parameter\ArrayType;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\TemplateAPI;
  use Objects\User;
  use Twig\Environment;
  use Twig\Error\LoaderError;
  use Twig\Error\RuntimeError;
  use Twig\Error\SyntaxError;
  use Twig\Loader\FilesystemLoader;

  class Render extends TemplateAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
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

      $templateDir = WEBROOT . "/core/Templates/";
      $templateCache = WEBROOT . "/core/TemplateCache/";
      $path = realpath($templateDir . $templateFile);
      if (!startsWith($path, realpath($templateDir))) {
        return $this->createError("Template file not in template directory");
      } else if (!is_file($path)) {
        return $this->createError("Template file not found");
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