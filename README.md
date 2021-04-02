# Web-Base

Web-Base is a php framework which provides basic web functionalities and a modern ReactJS Admin Dashboard.

### Requirements
- PHP >= 7.4
- One of these php extensions: mysqli, postgres

### Current Functionalities:
- Installation Guide with automatic database setup
- REST API
- Account management
- Supporting MySQL + PostgreSQL
- Dynamic Page Routing
- Admin Dashboard
- Account & User functions
- File Sharing Dashboard

### Upcoming:
I actually don't know what i want to implement here. There are quite to many CMS out there with alot of vulnerabilities. There also exist some frameworks already. This project is meant to provide a stable project base to implement what ever a developer wants to: Dynamic Homepages, Webshops, ..

## Installation

1. `git clone https://git.romanh.de/Projekte/web-base` (or `https://github.com/rhergenreder/web-base`)
2. Create a [mysql](https://dev.mysql.com/doc/refman/5.7/en/creating-database.html) or [postgresql](https://www.postgresql.org/docs/9.0/sql-createdatabase.html) database
    or use an existing empty database (e.g. test or public)
3. Open the webapp in your browser and follow the installation guide

For any changes made in [/adminPanel](/adminPanel) or [/fileControlPanel](/fileControlPanel), run:
1. once: `npm i`
2. build: `npm run build`
The compiled dist files will be automatically moved to `/js`.

## Extending the Base

### Adding API-Endpoints

Each API endpoint has usually one overlying category, for example all user and authorization endpoints belong to the [UserAPI](/core/Api/UserAPI.class.php).
These endpoints can be accessed by requesting URLs starting with `/api/user`, for example: `/api/user/login`. There are also endpoints, which don't have
a category, e.g. [PatchSQL](/core/Api/PatchSQL.class.php). These functions can be called directly, for example with `/api/patchSQL`. Both methods have one thing in common:
Each endpoint is represented by a class inheriting the [Request Class](/core/Api/Request.class.php). An example endpoint looks like this:

```php
namespace Api;
use Api\Parameter\Parameter;
use Objects\User;

class SingleEndpoint extends Request {

  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      "someParameter" => new Parameter("someParameter", Parameter::TYPE_INT, true, 100)
    ));
    $this->forbidMethod("POST");
  }

  public function execute($values = array()): bool {
    if (!parent::execute($values)) {
       return false;
    }
    $this->result['someAttribute'] = $this->getParam("someParameter") * 2;
    return true;
  }
}
```

An endpoint consists of two important functions:
1. the constructor defining the expected parameters as well as some restrictions and endpoint configuration.
2. the execute function, checking all requirements by calling the parent, and then executing the own method.

To create an API category containing multiple endpoints, a parent class inheriting from `Request`, e.g. `class MultipleAPI extends Request` is required.
All endpoints inside this category then inherit from the `MultipleAPI` class.

The classes must be present inside the [Api](/core/Api) directory according to the other endpoints.

### Access Control

By default, and if not further specified or restricted, all endpoints have the following access rules:
1. Allowed methods: GET and POST (`$this->allowedMethods`)
2. No login is required (`$this->loginRequired`)
3. CSRF-Token is required, if the user is logged in (`$this->csrfTokenRequired`)
4. The function can be called from outside (`$this->isPublic`)
5. An API-Key can be used to access this method (`$this->apiKeyAllowed`)
6. All user groups can access the method (Database, Table: `ApiPermission`)

The first five restrictions can be modified inside the constructor, while the group permissions are changed using
the [Admin Dashboard](/adminPanel). It's default values are set inside the [database script](/core/Configuration/CreateDatabase.class.php).

### Using the API internally

Some endpoints are set to private, which means, they can be only accessed inside the backend. These functions, as well as the public ones,
can be used by creating the desired request object, and calling the execute function with our parameters like shown below:

```php
$req = new \Api\Mail\Send($user);
$success = $req->execute(array(
  "to" => "mail@example.org", 
  "subject" => "Example Mail", 
  "body" => "This is an example mail"
));

if (!$success) {
   echo $req->getLastError();
}
```

The user object is usually obtained from the api (`$this->user`) or from the frontend document (`$document->getUser()`).
If any result is expected from the api call, the `$req->getResult()` method can be used, which returns an array of all field.

### Modifying the database

This step is not really required, as and changes made to the database must not be presented inside the code.
On the other hand, it is recommended to keep track of any modifications for later use or to deploy the application
to other systems. Therefore, either the [default installation script](/core/Configuration/CreateDatabase.class.php) or
an additional patch file, which can be executed using the API (`/api/PatchSQL`), can be created. The patch files are usually
located in [/core/Configuration/Patch](/core/Configuration/Patch) and have the following structure:

```php
namespace Configuration\Patch;

use Configuration\DatabaseScript;
use Driver\SQL\SQL;

class example_patch extends DatabaseScript {
  public static function createQueries(SQL $sql): array {
    $queries = [];
    $queries[] = $sql->createTable("ExampleTable")
        ->addSerial("exampleCol")
        ->addString("someString", 32)
        ->primaryKey("exampleCol");
    return $queries;
  }
}
```

### Routing

To access and view any frontend pages, the internal router is used. Available routes can be customized on the admin dashboard. There are four types of routes:

1. Permanent redirect (http status code: 308)
2. Temporary redirect (http status code: 307)
3. Static Route
4. Dynamic Content

A static route targets a file, usually located in [/static](/static) and does nothing more, than returning its content. A dynamic route is usually the way to go:
It takes two parameters, firstly the target document and secondly, an optional view. For example, take the following routing table:

| Route | Action | Target | Extra |
| ----- | ------ | ------ | ----- |
| `/funnyCatImage` | `Serve Static` | `/static/cat.jpg` | |
| `/someRoute(/(.*))?` | `Redirect Dynamic` | `\Documents\MyDocument\` | `$2` |

The first route would return the cat image, if the case-insensitive path `/funnyCatImage` is requested.
The second route is more interesting, as it firstly contains regex, which means, any route starting with `/someRoute/` or just `/someRoute` is accepted.
Secondly, it passes the second group (`$2`), which is all the text after the last slash (or `null`) to the dynamically loaded document `MyDocument`.

### Creating and Modifying documents

A frontend page consists of a document, which again consists of a head and a body. Furthermore, a document can have various views, which have to be implemented
programmatically. Usually, all pages inside a document look somehow similar, for example share a common side- or navbar, a header or a footer. If we think of a web-shop,
we could have one document, when showing different articles and products, and a view for various pages, e.g. the dashboard with all the products, a single product view and so on.
To create a new document, a class inside [/core/Documents](/core/Documents) is created with the following scheme:

```php
namespace Documents {

  use Elements\Document;
  use Objects\User;
  use Documents\Example\ExampleHead;
  use Documents\Example\ExampleBody;

  class ExampleDocument extends Document {
    public function __construct(User $user, ?string $view = NULL) {
      parent::__construct($user, ExampleHead::class, ExampleBody::class, $view);
    }
  }
}

namespace Documents\Example {

  use Elements\Head;
  use Elements\Body;

  class ExampleHead extends Head {
  
    public function __construct($document) {
      parent::__construct($document);
    }
  
    protected function initSources() {
      $this->loadJQuery();
      $this->loadBootstrap();
      $this->loadFontawesome();
    }
  
    protected function initMetas() : array {
      return array(
        array('charset' => 'utf-8'),
      );
    }
  
    protected function initRawFields() : array {
      return array();
    }
  
    protected function initTitle() : string {
      return "Example Document";
    }
  }
  
  class ExampleBody extends Body {
    public function __construct($document) {
      parent::__construct($document);
    }
    
    public function getCode(): string {
       $view = $this->getDocument()->getView() ?? "<Empty>";
       return "<b>Requested View:</b> " . htmlspecialchars($view);
    }
  }
}
```

Of course, the head and body classes can be placed in any file, as the code might get big and complex.


## Anything more?

Feel free to contact me regarding this project and any other questions.