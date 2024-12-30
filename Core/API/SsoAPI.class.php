<?php
      
namespace Core\API {
  
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\SsoProvider;
  use Core\Objects\DatabaseEntity\User;

  abstract class SsoAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = []) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function processLogin(SsoProvider $provider, User $user, ?string $redirectUrl): bool {
      $sql = $this->context->getSQL();
      if ($user->getId() === null) {
        // user didn't exit yet. try to insert into database
        if (!$user->save($sql)) {
          return $this->createError("Could not create user: " . $sql->getLastError());
        }
      } else if (!$user->isActive()) {
        return $this->createError("This user is currently disabled. Contact the server administrator, if you believe this is a mistake.");
      } else if ($user->getSsoProvider()?->getIdentifier() !== $provider->getIdentifier()) {
        return $this->createError("An existing user is not managed by the used identity provider");
      } else if (!$this->createSession($user)) {
        return false;
      }

      if (!empty($redirectUrl)) {
        $this->context->router->redirect(302, $redirectUrl);
      }

      return true;
    }

    protected function validateRedirectURL(string $url): bool {
      // allow only relative paths
      return empty($url) || startsWith($url, "/");
    }
  }
}

namespace Core\API\Sso {

  use Core\API\Parameter\StringType;
  use Core\API\Parameter\UuidType;
  use Core\API\Request;
  use Core\Objects\Context;
  use Core\API\SsoAPI;
  use Core\Objects\DatabaseEntity\Group;
  use Core\Objects\DatabaseEntity\SsoProvider;
  use Core\Objects\RateLimiting;
  use Core\Objects\RateLimitRule;
  use Core\Objects\SSO\SAMLResponse;

  class GetProviders extends SsoAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      $this->csrfTokenRequired = false;
    }
   
    protected function _execute(): bool {

      $sql = $this->context->getSQL();
      $query = SsoProvider::createBuilder($sql, false);

      if (!$this->context->getUser()) {
        $query->whereTrue("active");
      }

      $providers = SsoProvider::findBy($query);
      $this->result["providers"] = SsoProvider::toJsonArray($providers);
      return true;
    }

    public static function getDescription(): string {
      // TODO: auto generated endpoint description
      return "Allows users to get a list of SSO providers. Unauthenticated users will only see active providers.";
    }
  }

  class AddProvider extends SsoAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      // TODO: auto-generated method stub
    }
   
    protected function _execute(): bool {
      // TODO: auto-generated method stub
      return $this->success;
    }

    public static function getDescription(): string {
      // TODO: auto generated endpoint description
      return "Short description, what users are able to do with this endpoint.";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class EditProvider extends SsoAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      // TODO: auto-generated method stub
    }
   
    protected function _execute(): bool {
      // TODO: auto-generated method stub
      return $this->success;
    }

    public static function getDescription(): string {
      // TODO: auto generated endpoint description
      return "Short description, what users are able to do with this endpoint.";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class RemoveProvider extends SsoAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      // TODO: auto-generated method stub
    }
   
    protected function _execute(): bool {
      // TODO: auto-generated method stub
      return $this->success;
    }

    public static function getDescription(): string {
      // TODO: auto generated endpoint description
      return "Short description, what users are able to do with this endpoint.";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class Authenticate extends SsoAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "provider" => new UuidType("provider"),
        "redirect" => new StringType("redirect", StringType::UNLIMITED, true, null)
      ]);
      $this->csrfTokenRequired = false;
      $this->loginRequirements = Request::NOT_LOGGED_IN;
      $this->rateLimiting = new RateLimiting(
        new RateLimitRule(5, 1, RateLimitRule::MINUTE)
      );
    }

    protected function _execute(): bool {
      $redirectUrl = $this->getParam("redirect");
      if (!$this->validateRedirectURL($redirectUrl)) {
        return $this->createError("Invalid redirect URL");
      }

      $sql = $this->context->getSQL();
      $ssoProviderIdentifier = $this->getParam("provider");
      $ssoProvider = SsoProvider::findBy(SsoProvider::createBuilder($sql, true)
        ->whereEq("identifier", $ssoProviderIdentifier)
        ->whereTrue("active")
      );
      if ($ssoProvider === false) {
        return $this->createError("Error fetching SSO Provider: " . $sql->getLastError());
      } else if ($ssoProvider === null) {
        return $this->createError("SSO Provider not found");
      }

      try {
        $ssoProvider->login($this->context, $redirectUrl);
      } catch (\Exception $ex) {
        return $this->createError("There was an error with the SSO provider: " . $ex->getMessage());
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to authenticate with a configured SSO provider.";
    }

    public static function hasConfigurablePermissions(): bool {
      return false;
    }
  }


  class SAML extends SsoAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "SAMLResponse" => new StringType("SAMLResponse")
      ]);

      $this->csrfTokenRequired = false;
      $this->loginRequirements = Request::NOT_LOGGED_IN;
      $this->forbidMethod("GET");
      $this->rateLimiting = new RateLimiting(
        new RateLimitRule(15, 1, RateLimitRule::MINUTE)
      );
    }

    protected function _execute(): bool {


      $samlResponseEncoded = $this->getParam("SAMLResponse");
      if (($samlResponse = @gzinflate(base64_decode($samlResponseEncoded))) === false) {
        $samlResponse = base64_decode($samlResponseEncoded);
      }

      $parsedResponse = SAMLResponse::parseResponse($this->context, $samlResponse);
      if (!$parsedResponse->wasSuccessful()) {
        return $this->createError("Error parsing SAMLResponse: " . $parsedResponse->getError());
      } else {
        return $this->processLogin($parsedResponse->getProvider(), $parsedResponse->getUser(), $parsedResponse->getRedirectURL());
      }

      $sql = $this->context->getSQL();
      $ssoProviderIdentifier = $this->getParam("provider");
      $ssoProvider = SsoProvider::findBy(SsoProvider::createBuilder($sql, true)
        ->whereEq("identifier", $ssoProviderIdentifier)
        ->whereTrue("active")
      );
      if ($ssoProvider === false) {
        return $this->createError("Error fetching SSO Provider: " . $sql->getLastError());
      } else if ($ssoProvider === null) {
        return $this->createError("SSO Provider not found");
      }

      $samlResponseEncoded = $this->getParam("SAMLResponse");
      if (($samlResponse = @gzinflate(base64_decode($samlResponseEncoded))) === false) {
        $samlResponse = base64_decode($samlResponseEncoded);
      }

      $parsedUser = $ssoProvider->parseResponse($this->context, $samlResponse);
      if ($parsedUser === null) {
        return $this->createError("Invalid SAMLResponse");
      } else {
        return $this->processLogin($parsedUser, $redirectUrl);
      }
    }

    public static function getDescription(): string {
      return "Return endpoint for SAML SSO authentication.";
    }

    public static function hasConfigurablePermissions(): bool {
      return false;
    }
  }
}