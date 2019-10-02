<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Config\Config;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\Tests\Components\ComponentTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DirectoryTest extends ComponentTestCase
{
    /**
     * @var Directory
     */
    protected $fixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new Directory(uniqid());
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(AbstractFile::class, $this->fixture);
        self::assertInstanceOf(DirectoryInterface::class, $this->fixture);
    }

    public function testSetsNameOnConstruction(): void
    {
        $name = uniqid();

        $fixture = new Directory($name);

        self::assertEquals($name, $fixture->getName());
    }

    public function testSetsPermissionsOnConstruction(): void
    {
        $permissions = rand();

        $fixture = new Directory(uniqid(), $permissions);

        self::assertEquals($permissions, $fixture->getPermissions());
    }

    public function testSetsPermissionsWhenNullOnConstruction(): void
    {
        $fixture = new Directory(uniqid(), null);

        self::assertEquals(-1, $fixture->getPermissions());
    }

    public function testSetsType(): void
    {
        self::assertEquals(FileInterface::TYPE_DIR, $this->fixture->getType());
    }

    public function testGetSizeWhenEmpty(): void
    {
        self::assertEquals(0, $this->fixture->getSize());
    }

    public function testGetFileCountWhenEmpty(): void
    {
        self::assertEquals(0, $this->fixture->getFileCount());
    }

    public function testGetSizeWhenNotEmpty(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $childA = new Directory(uniqid());
        $childB = new RegularFile(uniqid());

        $this->fixture->addChild($childA);
        $this->fixture->addChild($childB);

        self::assertEquals(0, $this->fixture->getSize());
    }

    public function testGetFileCountWhenNotEmpty(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $childA = new Directory(uniqid());
        $childB = new RegularFile(uniqid());
        $childC = new RegularFile(uniqid());

        $this->fixture->addChild($childA);
        $this->fixture->addChild($childB);
        $childA->addChild($childC);

        self::assertEquals(2, $this->fixture->getFileCount());
    }

    public function testAddChildSetsConfig(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $file = $this->createFile();

        $file->expects(self::once())
            ->method('setConfig')
            ->with(self::identicalTo($config));

        $this->fixture->addChild($file);
    }

    public function testAddChildSetsParent(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $file = $this->createFile();

        $file->expects(self::once())
            ->method('setParent')
            ->with(self::identicalTo($this->fixture));

        $this->fixture->addChild($file);
    }

    public function testAddChildNormalizesName(): void
    {
        $fileA = $this->createFile(['getName' => 'some name']);
        $fileB = $this->createFile(['getName' => 'SoMe NaMe']);

        $config = new Config(['ignoreCase' => true]);
        $this->fixture->setConfig($config);

        $this->fixture->addChild($fileA);
        $this->fixture->addChild($fileB);

        $actual = $this->fixture->getChildren();

        self::assertCount(1, $actual);
        self::assertSame($fileB, $actual[0]);
    }

    public function testAddChildDoesNotNormalizeNames(): void
    {
        $fileA = $this->createFile(['getName' => 'some name']);
        $fileB = $this->createFile(['getName' => 'SoMe NaMe']);

        $config = new Config(['ignoreCase' => false]);
        $this->fixture->setConfig($config);

        $this->fixture->addChild($fileA);
        $this->fixture->addChild($fileB);

        $actual = $this->fixture->getChildren();

        self::assertCount(2, $actual);
        self::assertSame($fileA, $actual[0]);
        self::assertSame($fileB, $actual[1]);
    }

    public function testAddChildSetsLastModifyTime(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $this->fixture->setLastModifyTime(rand());

        $now = time();
        $file = $this->createFile();
        $this->fixture->addChild($file);

        self::assertEqualsWithDelta($now, $this->fixture->getLastModifyTime(), 1);
    }

    public function testGetChildrenEmpty(): void
    {
        self::assertEquals([], $this->fixture->getChildren());
    }

    public function testGetChildrenUpdatesLastAccessedTime(): void
    {
        $now = time();
        $this->fixture->setLastAccessTime(rand());

        $this->fixture->getChildren();

        self::assertEqualsWithDelta($now, $this->fixture->getLastAccessTime(), 1);
    }

    /**
     * @dataProvider sampleChildCase
     */
    public function testHasChild(
        bool $normalize,
        string $realName,
        string $searchName,
        bool $expected
    ): void {
        $config = new Config(['ignoreCase' => $normalize]);
        $this->fixture->setConfig($config);

        $fileA = $this->createFile(['getName' => uniqid()]);
        $fileB = $this->createFile(['getName' => $realName]);
        $fileC = $this->createFile(['getName' => uniqid()]);
        $this->fixture->addChild($fileA);
        $this->fixture->addChild($fileB);
        $this->fixture->addChild($fileC);

        self::assertEquals($expected, $this->fixture->hasChild($searchName));
    }

    public function sampleChildCase(): array
    {
        return [
            'matching case, ignore case' => [
                'normalize' => true,
                'realName' => 'some name',
                'searchName' => 'some name',
                'expected' => true,
            ],
            'matching case, honor case' => [
                'normalize' => false,
                'realName' => 'some name',
                'searchName' => 'some name',
                'expected' => true,
            ],
            'mismatch case, ignore case' => [
                'normalize' => true,
                'realName' => 'SoMe NamE',
                'searchName' => 'some name',
                'expected' => true,
            ],
            'mismatch case, honor case' => [
                'normalize' => false,
                'realName' => 'SoMe NamE',
                'searchName' => 'some name',
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider sampleChildCase
     */
    public function testGetChild(
        bool $normalize,
        string $realName,
        string $searchName,
        bool $expected
    ): void {
        $config = new Config(['ignoreCase' => $normalize]);
        $this->fixture->setConfig($config);

        $fileA = $this->createFile(['getName' => uniqid()]);
        $fileB = $this->createFile(['getName' => $realName]);
        $fileC = $this->createFile(['getName' => uniqid()]);
        $this->fixture->addChild($fileA);
        $this->fixture->addChild($fileB);
        $this->fixture->addChild($fileC);

        if (!$expected) {
            self::expectException(NotFoundException::class);
            self::expectExceptionMessage('Child "'.$searchName.'" does not exist.');
        }

        $actual = $this->fixture->getChild($searchName);

        if ($expected) {
            self::assertSame($fileB, $actual);
        }
    }

    /**
     * @dataProvider sampleChildCase
     */
    public function testRemoveChild(
        bool $normalize,
        string $realName,
        string $searchName,
        bool $expected
    ): void {
        $config = new Config(['ignoreCase' => $normalize]);
        $this->fixture->setConfig($config);

        $fileA = $this->createFile(['getName' => uniqid()]);
        $fileB = $this->createFile(['getName' => $realName]);
        $fileC = $this->createFile(['getName' => uniqid()]);
        $this->fixture->addChild($fileA);
        $this->fixture->addChild($fileB);
        $this->fixture->addChild($fileC);

        self::assertEquals($expected, $this->fixture->removeChild($searchName));
    }

    public function testHasChildWhenNoChildren(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        self::assertFalse($this->fixture->hasChild(uniqid()));
    }

    public function testGetSummaryWhenNoChildren(): void
    {
        $actual = $this->fixture->getSummary(null, null);

        self::assertEquals(0, $actual->getSize());
        self::assertEquals(0, $actual->getFileCount());
    }

    /**
     * @return FileInterface&MockObject
     */
    private function createFile(array $methods = []): FileInterface
    {
        return $this->createConfiguredMock(FileInterface::class, $methods);
    }
}
