<?php

use Core\API\Parameter\Parameter;
use Core\Driver\SQL\Query\CreateTable;
use Core\Driver\SQL\SQL;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntityHandler;
use Core\Objects\DatabaseEntity\User;

class DatabaseEntityTest extends \PHPUnit\Framework\TestCase {

  static User $USER;
  static SQL $SQL;
  static Context $CONTEXT;
  static DatabaseEntityHandler $HANDLER;

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    self::$CONTEXT = new Context();
    if (!self::$CONTEXT->initSQL()) {
      throw new Exception("Could not establish database connection");
    }

    self::$SQL = self::$CONTEXT->getSQL();
    self::$HANDLER = TestEntity::getHandler(self::$SQL);
    self::$HANDLER->getLogger()->unitTestMode();
  }

  public function testCreateTable() {
    $query = self::$HANDLER->getTableQuery(self::$CONTEXT->getSQL());
    $this->assertInstanceOf(CreateTable::class, $query);
    $this->assertTrue($query->execute());
  }

  public function testInsertEntity() {

    $entity = new TestEntity();
    $entity->a = 1;
    $entity->b = "test";
    $entity->c = true;
    $entity->d = 1.23;
    $entity->e = new DateTime();
    $entity->f = null;

    // insert
    $this->assertTrue($entity->save(self::$SQL));
    $entityId = $entity->getId();
    $this->assertNotNull($entityId);

    // fetch
    $entity2 = TestEntity::find(self::$SQL, $entityId);
    $this->assertNotNull($entity2);
    $this->assertEquals($entity2->a, $entity->a);
    $this->assertEquals($entity2->b, $entity->b);
    $this->assertEquals($entity2->c, $entity->c);
    $this->assertEquals($entity2->d, $entity->d);
    $this->assertNotNull($entity2->e);
    $this->assertEquals(
      $entity2->e->format(Parameter::DATE_TIME_FORMAT),
      $entity->e->format(Parameter::DATE_TIME_FORMAT)
    );
    $this->assertNull($entity2->f);

    // update
    $entity2->a = 100;
    $this->assertTrue($entity2->save(self::$SQL));
    $this->assertEquals($entity2->getId(), $entityId);

    // re-fetch
    $this->assertEquals($entity2->a, TestEntity::find(self::$SQL, $entityId)->a);

    // check table contents
    $allEntities = TestEntity::findAll(self::$SQL);
    $this->assertIsArray($allEntities);
    $this->assertCount(1, $allEntities);
    $this->assertTrue(array_key_exists($entityId, $allEntities));
    $this->assertEquals($entityId, $allEntities[$entityId]->getId());

    // delete
    $this->assertTrue($entity->delete(self::$SQL));
    $this->assertNull($entity->getId());

    // check table contents
    $allEntities = TestEntity::findAll(self::$SQL);
    $this->assertIsArray($allEntities);
    $this->assertCount(0, $allEntities);
  }

  public function testInsertFail() {
    $entity = new TestEntity();
    $this->assertFalse($entity->save(self::$SQL));
    $this->assertTrue(startsWith(
      self::$HANDLER->getLogger()->getLastMessage(),
      "Cannot insert entity: property 'a' was not initialized yet."
    ));
    $this->assertEquals("error", self::$HANDLER->getLogger()->getLastLevel());
  }

  public function testDropTable() {
    $this->assertTrue(self::$SQL->drop(self::$HANDLER->getTableName())->execute());
  }
}

class TestEntity extends DatabaseEntity {
  public int $a;
  public string $b;
  public bool $c;
  public float $d;
  public \DateTime $e;
  public ?int $f;
}