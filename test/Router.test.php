<?php

use Core\Objects\Context;
use Core\Objects\Router\EmptyRoute;
use Core\Objects\Router\Router;

class RouterTest extends \PHPUnit\Framework\TestCase {

  private static Router $ROUTER;
  private static Context $CONTEXT;

  public static function setUpBeforeClass(): void {
    RouterTest::$CONTEXT = new Context();
    RouterTest::$ROUTER = new Router(RouterTest::$CONTEXT);
  }

  public function testSimpleRoutes() {
    $this->assertNotFalse((new EmptyRoute("/"))->match("/"));
    $this->assertNotFalse((new EmptyRoute("/a"))->match("/a"));
    $this->assertNotFalse((new EmptyRoute("/b/"))->match("/b/"));
    $this->assertNotFalse((new EmptyRoute("/c/d"))->match("/c/d"));
    $this->assertNotFalse((new EmptyRoute("/e/f/"))->match("/e/f/"));
  }

  public function testParamRoutes() {
    $paramsEmpty = (new EmptyRoute("/"))->match("/");
    $this->assertEquals([], $paramsEmpty);

    $params1 = (new EmptyRoute("/{param}"))->match("/test");
    $this->assertEquals(["param" => "test"], $params1);

    $params2 = (new EmptyRoute("/{param1}/{param2}"))->match("/test/123");
    $this->assertEquals(["param1" => "test", "param2" => "123"], $params2);

    $paramOptional1 = (new EmptyRoute("/{optional1:?}"))->match("/");
    $this->assertEquals(["optional1" => null], $paramOptional1);

    $paramOptional2 = (new EmptyRoute("/{optional2:?}"))->match("/yes");
    $this->assertEquals(["optional2" => "yes"], $paramOptional2);

    $paramOptional3 = (new EmptyRoute("/{optional3:?}/{optional4:?}"))->match("/1/2");
    $this->assertEquals(["optional3" => "1", "optional4" => "2"], $paramOptional3);

    $mixedRoute = new EmptyRoute("/{optional5:?}/{notOptional}");
    $paramMixed1 = $mixedRoute->match("/3/4");
    $this->assertEquals(["optional5" => "3", "notOptional" => "4"], $paramMixed1);
  }

  public function testMixedRoute() {
    $mixedRoute1 = new EmptyRoute("/{param}/static");
    $this->assertEquals(["param" => "yes"], $mixedRoute1->match("/yes/static"));

    $mixedRoute2 = new EmptyRoute("/static/{param}");
    $this->assertEquals(["param" => "yes"], $mixedRoute2->match("/static/yes"));
  }

  public function testEmptyRoute() {
    $emptyRoute = new EmptyRoute("/");
    $this->assertEquals("", $emptyRoute->call(RouterTest::$ROUTER, []));
  }

  public function testTypedParamRoutes() {
    $intParamRoute = new EmptyRoute("/{param:int}");
    $this->assertFalse($intParamRoute->match("/test"));
    $this->assertEquals(["param" => 123], $intParamRoute->match("/123"));

    $floatRoute = new EmptyRoute("/{param:float}");
    $this->assertFalse($floatRoute->match("/test"));
    $this->assertEquals(["param" => 1.23], $floatRoute->match("/1.23"));

    $boolRoute = new EmptyRoute("/{param:bool}");
    $this->assertFalse($boolRoute->match("/test"));
    $this->assertEquals(["param" => true],  $boolRoute->match("/true"));
    $this->assertEquals(["param" => false], $boolRoute->match("/false"));

    $mixedRoute = new EmptyRoute("/static/{param:int}/{optional:float?}");
    $this->assertFalse($mixedRoute->match("/static"));
    $this->assertFalse($mixedRoute->match("/static/abc"));
    $this->assertEquals(["param" => 123, "optional" => null], $mixedRoute->match("/static/123"));
    $this->assertEquals(["param" => 123, "optional" => 4.56], $mixedRoute->match("/static/123/4.56"));
  }
}