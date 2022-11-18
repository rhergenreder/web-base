<?php

use Core\API\Parameter\ArrayType;
use Core\API\Parameter\StringType;
use Core\API\Parameter\Parameter;

class ParameterTest extends \PHPUnit\Framework\TestCase {

  public function testStringType() {

    // test various string sizes
    $unlimited = new StringType("test_unlimited");
    $this->assertTrue($unlimited->parseParam(str_repeat("A", 1024)));

    $empty     = new StringType("test_empty", 0);
    $this->assertTrue($empty->parseParam(""));
    $this->assertTrue($empty->parseParam("A"));

    $one       = new StringType("test_one", 1);
    $this->assertTrue($one->parseParam(""));
    $this->assertTrue($one->parseParam("A"));
    $this->assertFalse($one->parseParam("AB"));

    $randomSize = rand(1, 64);
    $random    = new StringType("test_empty", $randomSize);
    $data      = str_repeat("A", $randomSize);
    $this->assertTrue($random->parseParam(""));
    $this->assertTrue($random->parseParam("A"));
    $this->assertTrue($random->parseParam($data));
    $this->assertEquals($data, $random->value);

    // test data types
    $this->assertFalse($random->parseParam(null));
    $this->assertFalse($random->parseParam(1));
    $this->assertFalse($random->parseParam(2.5));
    $this->assertFalse($random->parseParam(true));
    $this->assertFalse($random->parseParam(false));
    $this->assertFalse($random->parseParam(["key" => 1]));
  }

  public function testArrayType() {

    // int array type
    $arrayType = new ArrayType("int_array", Parameter::TYPE_INT);
    $this->assertTrue($arrayType->parseParam([1,2,3]));
    $this->assertTrue($arrayType->parseParam([1]));
    $this->assertTrue($arrayType->parseParam(["1"]));
    $this->assertTrue($arrayType->parseParam([1.0]));
    $this->assertTrue($arrayType->parseParam([]));
    $this->assertTrue($arrayType->parseParam(["1.0"]));
    $this->assertFalse($arrayType->parseParam([1.2]));
    $this->assertFalse($arrayType->parseParam(["1.5"]));
    $this->assertFalse($arrayType->parseParam([true]));
    $this->assertFalse($arrayType->parseParam(1));

    // optional single value
    $arrayType = new ArrayType("int_array_single", Parameter::TYPE_INT, true);
    $this->assertTrue($arrayType->parseParam(1));

    // mixed values
    $arrayType = new ArrayType("mixed_array", Parameter::TYPE_MIXED);
    $this->assertTrue($arrayType->parseParam([1, 2.5, "test", false]));
  }

  public function testParseType() {
    // int
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType(1));
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType(1.0));
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType("1"));
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType("1.0"));

    // array
    $this->assertEquals(Parameter::TYPE_ARRAY, Parameter::parseType([1, true]));

    // float
    $this->assertEquals(Parameter::TYPE_FLOAT, Parameter::parseType(1.5));
    $this->assertEquals(Parameter::TYPE_FLOAT, Parameter::parseType(1.234e2));
    $this->assertEquals(Parameter::TYPE_FLOAT, Parameter::parseType("1.75"));

    // boolean
    $this->assertEquals(Parameter::TYPE_BOOLEAN, Parameter::parseType(true));
    $this->assertEquals(Parameter::TYPE_BOOLEAN, Parameter::parseType(false));
    $this->assertEquals(Parameter::TYPE_BOOLEAN, Parameter::parseType("true"));
    $this->assertEquals(Parameter::TYPE_BOOLEAN, Parameter::parseType("false"));

    // date
    $this->assertEquals(Parameter::TYPE_DATE, Parameter::parseType("2021-11-13"));
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("2021-13-11")); # invalid date

    // time
    $this->assertEquals(Parameter::TYPE_TIME, Parameter::parseType("10:11:12"));
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("25:11:12")); # invalid time

    // datetime
    $this->assertEquals(Parameter::TYPE_DATE_TIME, Parameter::parseType("2021-11-13 10:11:12"));
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("2021-13-13 10:11:12")); # invalid date
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("2021-13-11 10:61:12")); # invalid time

    // email
    $this->assertEquals(Parameter::TYPE_EMAIL, Parameter::parseType("a@b.com"));
    $this->assertEquals(Parameter::TYPE_EMAIL, Parameter::parseType("test.123@example.com"));
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("@example.com")); # invalid email
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("test@")); # invalid email

    // string, everything else
    $this->assertEquals(Parameter::TYPE_STRING, Parameter::parseType("test"));
  }
}