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

    // test data types: we cast the values to string
    $this->assertTrue($random->parseParam(1));
    $this->assertTrue($random->parseParam(2.5));
    $this->assertTrue($random->parseParam(true));
    $this->assertTrue($random->parseParam(false));

    // null values only allowed, when parameter is optional
    $this->assertFalse($random->parseParam(null));

    // arrays cannot be cast to string (easily)
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
    $this->assertEquals([1], $arrayType->value);

    // mixed values
    $arrayType = new ArrayType("mixed_array", Parameter::TYPE_MIXED);
    $this->assertTrue($arrayType->parseParam([1, 2.5, "test", false]));
  }

  public function testParseType() {
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

  public function testIntegerType() {
    // int
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType(1));
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType(1.0));
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType("1"));
    $this->assertEquals(Parameter::TYPE_INT, Parameter::parseType("1.0"));

    // test min value
    $min = new \Core\API\Parameter\IntegerType("test_has_min_value", 10);
    $this->assertTrue($min->parseParam(10));
    $this->assertTrue($min->parseParam(11));
    $this->assertFalse($min->parseParam(9));

    // test max value
    $max = new \Core\API\Parameter\IntegerType("test_has_min_value", PHP_INT_MIN, 100);
    $this->assertTrue($max->parseParam(99));
    $this->assertTrue($max->parseParam(100));
    $this->assertFalse($max->parseParam(101));

    // test min and max value
    $minmax = new \Core\API\Parameter\IntegerType("test_has_min_value", 10, 100);
    $this->assertTrue($minmax->parseParam(10));
    $this->assertTrue($minmax->parseParam(11));
    $this->assertFalse($minmax->parseParam(9));
    $this->assertTrue($minmax->parseParam(99));
    $this->assertTrue($minmax->parseParam(100));
    $this->assertFalse($minmax->parseParam(101));
  }

  public function testRegexType() {
    $onlyLowercase = new \Core\API\Parameter\RegexType("only_lowercase", "/[a-z]+/");
    $this->assertTrue($onlyLowercase->parseParam("abcdefghiklmnopqrstuvwxyz"));
    $this->assertFalse($onlyLowercase->parseParam("0123456789"));

    $onlyLowercaseOneChar = new \Core\API\Parameter\RegexType("only_lowercase_one_char", "/^[a-z]$/");
    $this->assertFalse($onlyLowercaseOneChar->parseParam("abcdefghiklmnopqrstuvwxyz"));
    $this->assertTrue($onlyLowercaseOneChar->parseParam("a"));

    $regexWithoutSlashes = new \Core\API\Parameter\RegexType("regex_no_slash", "[a-z]+");
    $this->assertTrue($regexWithoutSlashes->parseParam("abcdefghiklmnopqrstuvwxyz"));
    $this->assertFalse($regexWithoutSlashes->parseParam("0123456789"));

    $integerRegex = new \Core\API\Parameter\RegexType("integer_regex", "[1-9][0-9]*");
    $this->assertTrue($integerRegex->parseParam("12"));
    $this->assertTrue($integerRegex->parseParam(12));
    $this->assertFalse($integerRegex->parseParam("012"));
    $this->assertFalse($integerRegex->parseParam("1.2"));

    $uuidRegex = new \Core\API\Parameter\UuidType("uuid_regex");
    $this->assertTrue($uuidRegex->parseParam("e3ad46da-556d-4c61-9d9a-ef85ba7b4053"));
    $this->assertTrue($uuidRegex->parseParam("00000000-0000-0000-0000-000000000000"));
    $this->assertFalse($uuidRegex->parseParam("e3ad46da-556d-4c61-9d9a-ef85ba7b4053123"));
    $this->assertFalse($uuidRegex->parseParam("e3ad46da-556d-4c61-9d9a-ef85ba7"));
    $this->assertFalse($uuidRegex->parseParam("not-a-valid-uuid"));
  }
}