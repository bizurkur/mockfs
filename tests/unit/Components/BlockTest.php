<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\Block;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Tests\Components\RegularFileTestCase;

class BlockTest extends RegularFileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new Block(uniqid());
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(RegularFile::class, $this->fixture);
    }

    public function testSetsNameOnConstruction(): void
    {
        $name = uniqid();

        $fixture = new Block($name);

        self::assertEquals($name, $fixture->getName());
    }

    public function testSetsPermissionsOnConstruction(): void
    {
        $permissions = rand();

        $fixture = new Block(uniqid(), $permissions);

        self::assertEquals($permissions, $fixture->getPermissions());
    }

    public function testSetsPermissionsWhenNullOnConstruction(): void
    {
        $fixture = new Block(uniqid(), null);

        self::assertEquals(-1, $fixture->getPermissions());
    }

    public function testSetsType(): void
    {
        self::assertEquals(FileInterface::TYPE_BLOCK, $this->fixture->getType());
    }
}
