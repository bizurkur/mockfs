<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Content\NullContent;
use MockFileSystem\Content\StreamContent;

class RegularFileTest extends RegularFileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new RegularFile(uniqid());
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(RegularFile::class, $this->fixture);
    }

    public function testSetsNameOnConstruction(): void
    {
        $name = uniqid();

        $fixture = new RegularFile($name);

        self::assertEquals($name, $fixture->getName());
    }

    public function testSetsPermissionsOnConstruction(): void
    {
        $permissions = rand();

        $fixture = new RegularFile(uniqid(), $permissions);

        self::assertEquals($permissions, $fixture->getPermissions());
    }

    public function testSetsPermissionsWhenNullOnConstruction(): void
    {
        $fixture = new RegularFile(uniqid(), null);

        self::assertEquals(-1, $fixture->getPermissions());
    }

    public function testSetsType(): void
    {
        self::assertEquals(FileInterface::TYPE_FILE, $this->fixture->getType());
    }

    public function testSetsContentOnConstruction(): void
    {
        $content = new NullContent();

        $fixture = new RegularFile(uniqid(), rand(), $content);

        self::assertSame($content, $fixture->getContent());
    }

    public function testSetsContentWhenNullOnConstruction(): void
    {
        $fixture = new RegularFile(uniqid());

        self::assertInstanceOf(StreamContent::class, $fixture->getContent());
    }
}
