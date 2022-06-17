<?php

class DatabaseEntityTest extends \PHPUnit\Framework\TestCase {

  static \Objects\User $USER;
  static \Driver\SQL\SQL $SQL;
  static \Objects\DatabaseEntity\DatabaseEntityHandler $HANDLER;

  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    self::$USER = new Objects\User(new \Configuration\Configuration());
    self::$SQL = self::$USER->getSQL();
    self::$HANDLER = TestEntity::getHandler(self::$SQL);
    self::$HANDLER->getLogger()->unitTestMode();
  }

  public function testCreateTable() {
    $this->assertInstanceOf(\Driver\SQL\Query\CreateTable::class, self::$HANDLER->getTableQuery());
    $this->assertTrue(self::$HANDLER->createTable());
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
      $entity2->e->format(\Api\Parameter\Parameter::DATE_TIME_FORMAT),
      $entity->e->format(\Api\Parameter\Parameter::DATE_TIME_FORMAT)
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
    $this->assertEquals($entityId, $allEntities[0]->getId());

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

class TestEntity extends \Objects\DatabaseEntity\DatabaseEntity {
  public int $a;
  public string $b;
  public bool $c;
  public float $d;
  public \DateTime $e;
  public ?int $f;
}