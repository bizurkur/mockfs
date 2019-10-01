<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\SummaryInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\LogicException;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FileSystemTest extends TestCase
{
    /**
     * @var FileSystem
     */
    private $fixture = null;

    /**
     * @var ConfigInterface
     */
    private $config = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Config();

        $this->fixture = new FileSystem($this->config);
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(FileSystemInterface::class, $this->fixture);
    }

    public function testGetConfig(): void
    {
        self::assertSame($this->config, $this->fixture->getConfig());
    }

    public function testGetParent(): void
    {
        self::assertNull($this->fixture->getParent());
    }

    public function testSetParent(): void
    {
        $parent = $this->createParent();

        self::expectException(LogicException::class);
        self::expectExceptionMessage('The file system cannot have a parent.');

        $this->fixture->setParent($parent);
    }

    public function testGetPath(): void
    {
        self::assertEquals('', $this->fixture->getPath());
    }

    public function testGetUrl(): void
    {
        self::assertEquals(StreamWrapper::PROTOCOL.'://', $this->fixture->getUrl());
    }

    public function testAddChildSetsConfig(): void
    {
        $partition = $this->createPartition();

        $partition->expects(self::once())
            ->method('setConfig')
            ->with(self::identicalTo($this->config));

        $this->fixture->addChild($partition);
    }

    public function testAddChildSetsParent(): void
    {
        $partition = $this->createPartition();

        $partition->expects(self::once())
            ->method('setParent')
            ->with(self::identicalTo($this->fixture));

        $this->fixture->addChild($partition);
    }

    public function testAddChildNormalizesName(): void
    {
        $partitionA = $this->createPartition(['getPath' => 'some name']);
        $partitionB = $this->createPartition(['getPath' => 'SoMe NaMe']);

        $this->fixture = new FileSystem(new Config(['ignoreCase' => true]));
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $actual = $this->fixture->getChildren();

        self::assertCount(1, $actual);
        self::assertSame($partitionB, $actual[0]);
    }

    public function testAddChildDoesNotNormalizeNames(): void
    {
        $partitionA = $this->createPartition(['getPath' => 'some name']);
        $partitionB = $this->createPartition(['getPath' => 'SoMe NaMe']);

        $this->fixture = new FileSystem(new Config(['ignoreCase' => false]));
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $actual = $this->fixture->getChildren();

        self::assertCount(2, $actual);
        self::assertSame($partitionA, $actual[0]);
        self::assertSame($partitionB, $actual[1]);
    }

    public function testAddChildThrowsException(): void
    {
        $partition = $this->createMock(FileInterface::class);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(
            FileSystem::class.' only accepts children that implement '.PartitionInterface::class
        );

        $this->fixture->addChild($partition);
    }

    public function testGetChildrenEmpty(): void
    {
        self::assertEquals([], $this->fixture->getChildren());
    }

    /**
     * @dataProvider sampleChildCase
     */
    public function testHasChild(
        bool $normalize,
        string $path,
        string $name,
        bool $expected
    ): void {
        $this->fixture = new FileSystem(new Config(['ignoreCase' => $normalize]));

        $partitionA = $this->createPartition(['getPath' => uniqid()]);
        $partitionB = $this->createPartition(['getPath' => $path]);
        $partitionC = $this->createPartition(['getPath' => uniqid()]);
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);
        $this->fixture->addChild($partitionC);

        self::assertEquals($expected, $this->fixture->hasChild($name));
    }

    public function sampleChildCase(): array
    {
        return [
            'matching case, ignore case' => [
                'normalize' => true,
                'path' => 'some name',
                'name' => 'some name',
                'expected' => true,
            ],
            'matching case, honor case' => [
                'normalize' => false,
                'path' => 'some name',
                'name' => 'some name',
                'expected' => true,
            ],
            'mismatch case, ignore case' => [
                'normalize' => true,
                'path' => 'some name',
                'name' => 'SoMe NamE',
                'expected' => true,
            ],
            'mismatch case, honor case' => [
                'normalize' => false,
                'path' => 'some name',
                'name' => 'SoMe NamE',
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider sampleChildCase
     */
    public function testGetChild(
        bool $normalize,
        string $path,
        string $name,
        bool $expected
    ): void {
        $this->fixture = new FileSystem(new Config(['ignoreCase' => $normalize]));

        $partitionA = $this->createPartition(['getPath' => uniqid()]);
        $partitionB = $this->createPartition(['getPath' => $path]);
        $partitionC = $this->createPartition(['getPath' => uniqid()]);
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);
        $this->fixture->addChild($partitionC);

        if (!$expected) {
            self::expectException(NotFoundException::class);
            self::expectExceptionMessage('Partition "'.$name.'" does not exist.');
        }

        $actual = $this->fixture->getChild($name);

        if ($expected) {
            self::assertSame($partitionB, $actual);
        }
    }

    /**
     * @dataProvider sampleChildCase
     */
    public function testRemoveChild(
        bool $normalize,
        string $path,
        string $name,
        bool $expected
    ): void {
        $this->fixture = new FileSystem(new Config(['ignoreCase' => $normalize]));

        $partitionA = $this->createPartition(['getPath' => uniqid()]);
        $partitionB = $this->createPartition(['getPath' => $path]);
        $partitionC = $this->createPartition(['getPath' => uniqid()]);
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);
        $this->fixture->addChild($partitionC);

        self::assertEquals($expected, $this->fixture->removeChild($name));
    }

    public function testHasChildWhenNoPartitions(): void
    {
        self::assertFalse($this->fixture->hasChild(uniqid()));
    }

    public function testGetSummaryWhenNoPartitions(): void
    {
        $actual = $this->fixture->getSummary(null, null);

        self::assertEquals(0, $actual->getSize());
        self::assertEquals(0, $actual->getFileCount());
    }

    /**
     * @dataProvider sampleSummaryUserGroup
     */
    public function testGetSummaryCallsPartitionGetSummary(?int $user, ?int $group): void
    {
        $partitionA = $this->createPartition(['getPath' => uniqid()]);
        $partitionB = $this->createPartition(['getPath' => uniqid()]);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionA->expects(self::once())
            ->method('getSummary')
            ->with($user, $group);

        $partitionB->expects(self::once())
            ->method('getSummary')
            ->with($user, $group);

        $this->fixture->getSummary($user, $group);
    }

    public function testFindWithNoPath(): void
    {
        $name = uniqid();
        $partitionA = $this->createPartition(['getPath' => $name.'/']);
        $partitionB = $this->createPartition(['getPath' => uniqid().'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionA->expects(self::once())
            ->method('find')
            ->with('');

        $this->fixture->find($name);
    }

    public function testFindWithPathCallsPartitionFind(): void
    {
        $name = uniqid();
        $path = uniqid();
        $partitionA = $this->createPartition(['getPath' => uniqid().'/']);
        $partitionB = $this->createPartition(['getPath' => $name.'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionA->expects(self::never())->method('find');
        $partitionB->expects(self::once())
            ->method('find')
            ->with($path);

        $this->fixture->find($name.'/'.$path);
    }

    public function testFindWithPathReturnsPartitionFind(): void
    {
        $name = uniqid();
        $path = uniqid();
        $partitionA = $this->createPartition(['getPath' => uniqid().'/']);
        $partitionB = $this->createPartition(['getPath' => $name.'/']);
        $file = $this->createFile();

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionB->method('find')->willReturn($file);

        $actual = $this->fixture->find($name.'/'.uniqid());

        self::assertSame($file, $actual);
    }

    public function testFindWithUnknownPath(): void
    {
        $partitionA = $this->createPartition(['getPath' => uniqid().'/']);
        $partitionB = $this->createPartition(['getPath' => uniqid().'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $actual = $this->fixture->find(uniqid().'/'.uniqid());

        self::assertNull($actual);
    }

    public function testFindWithError(): void
    {
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            ['getFileSeparator' => '']
        );
        $this->fixture = new FileSystem($config);

        $name = uniqid();
        $partitionA = $this->createPartition(['getPath' => $name.'/']);
        $partitionB = $this->createPartition(['getPath' => uniqid().'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $actual = $this->fixture->find($name);

        self::assertNull($actual);
    }

    public function testFindWithNormalizedSlashes(): void
    {
        $this->fixture = new FileSystem(new Config(['normalizeSlashes' => true]));

        $name = uniqid();
        $path = uniqid();
        $partitionA = $this->createPartition(['getPath' => uniqid().'/']);
        $partitionB = $this->createPartition(['getPath' => $name.'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionA->expects(self::never())->method('find');
        $partitionB->expects(self::once())
            ->method('find')
            ->with($path);

        $this->fixture->find($name.'\\'.$path);
    }

    public function testFindWithoutNormalizedSlashes(): void
    {
        $this->fixture = new FileSystem(new Config(['normalizeSlashes' => false]));

        $name = uniqid();
        $partitionA = $this->createPartition(['getPath' => uniqid().'/']);
        $partitionB = $this->createPartition(['getPath' => $name.'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionA->expects(self::never())->method('find');
        $partitionB->expects(self::never())->method('find');

        $actual = $this->fixture->find($name.'\\'.uniqid());

        self::assertNull($actual);
    }

    public function testFindWithIgnoreCase(): void
    {
        $this->fixture = new FileSystem(new Config(['ignoreCase' => true]));

        $name = uniqid();
        $path = uniqid();
        $partitionA = $this->createPartition(['getPath' => uniqid().'/']);
        $partitionB = $this->createPartition(['getPath' => $name.'/']);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $partitionA->expects(self::never())->method('find');
        $partitionB->expects(self::once())
            ->method('find')
            ->with(strtoupper($path));

        $this->fixture->find(strtoupper($name.'/'.$path));
    }

    public function sampleSummaryUserGroup(): array
    {
        $user = rand();
        $group = rand();

        return [
            'null user, null group' => [
                'user' => null,
                'group' => null,
            ],
            'int user, null group' => [
                'user' => $user,
                'group' => null,
            ],
            'null user, int group' => [
                'user' => null,
                'group' => $group,
            ],
            'int user, int group' => [
                'user' => $user,
                'group' => $group,
            ],
        ];
    }

    public function testGetSummary(): void
    {
        $sizeA = rand(1, 100);
        $sizeB = rand(1, 100);
        $countA = rand(1, 100);
        $countB = rand(1, 100);
        $summaryA = $this->createSummary(['getSize' => $sizeA, 'getFileCount' => $countA]);
        $summaryB = $this->createSummary(['getSize' => $sizeB, 'getFileCount' => $countB]);
        $partitionA = $this->createPartition(['getSummary' => $summaryA, 'getPath' => uniqid()]);
        $partitionB = $this->createPartition(['getSummary' => $summaryB, 'getPath' => uniqid()]);

        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);
        $actual = $this->fixture->getSummary(null, null);

        self::assertEquals($sizeA + $sizeB, $actual->getSize());
        self::assertEquals($countA + $countB, $actual->getFileCount());
    }

    /**
     * @return ContainerInterface&MockObject
     */
    private function createParent(): ContainerInterface
    {
        return $this->createMock(ContainerInterface::class);
    }

    /**
     * @return PartitionInterface&MockObject
     */
    private function createPartition(array $methods = []): PartitionInterface
    {
        return $this->createConfiguredMock(PartitionInterface::class, $methods);
    }

    /**
     * @return SummaryInterface&MockObject
     */
    private function createSummary(array $methods = []): SummaryInterface
    {
        return $this->createConfiguredMock(SummaryInterface::class, $methods);
    }

    /**
     * @return FileInterface&MockObject
     */
    private function createFile(array $methods = []): FileInterface
    {
        return $this->createConfiguredMock(FileInterface::class, $methods);
    }
}
