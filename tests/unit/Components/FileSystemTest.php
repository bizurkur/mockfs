<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\FinderInterface;
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

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class FileSystemTest extends TestCase
{
    private FileSystem $fixture;

    private ConfigInterface $config;

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

    public function testSetParentNull(): void
    {
        $actual = $this->fixture->setParent(null);

        self::assertSame($this->fixture, $actual);
    }

    public function testSetFinder(): void
    {
        $finder = $this->createMock(FinderInterface::class);

        $this->fixture->setFinder($finder);

        $actual = $this->fixture->getFinder();

        self::assertSame($finder, $actual);
    }

    public function testSetFinderResponse(): void
    {
        $finder = $this->createMock(FinderInterface::class);

        $actual = $this->fixture->setFinder($finder);

        self::assertSame($this->fixture, $actual);
    }

    public function testFindCallsFinder(): void
    {
        $path = uniqid();

        $finder = $this->createMock(FinderInterface::class);
        $this->fixture->setFinder($finder);

        $finder->expects(self::once())
            ->method('find')
            ->with($path);

        $this->fixture->find($path);
    }

    public function testFindResponse(): void
    {
        $file = $this->createMock(FileInterface::class);
        $finder = $this->createConfiguredMock(
            FinderInterface::class,
            ['find' => $file]
        );
        $this->fixture->setFinder($finder);

        $actual = $this->fixture->find(uniqid());

        self::assertSame($file, $actual);
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetPath(array $options, ?string $path, string $expected): void
    {
        if (
            isset($options['getFileSeparator'])
            && empty($options['getFileSeparator'])
            && version_compare(PHP_VERSION, '7.9.9', '>=')
        ) {
            self::markTestSkipped('This test only applies to PHP < 8.0');
        }
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            $options + ['getFileSeparator' => '/']
        );
        $this->fixture = new FileSystem($config);

        $actual = @$this->fixture->getPath($path);

        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetUrl(array $options, ?string $path, string $expected): void
    {
        if (
            isset($options['getFileSeparator'])
            && empty($options['getFileSeparator'])
            && version_compare(PHP_VERSION, '7.9.9', '>=')
        ) {
            self::markTestSkipped('This test only applies to PHP < 8.0');
        }
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            $options + ['getFileSeparator' => '/']
        );
        $this->fixture = new FileSystem($config);

        $actual = @$this->fixture->getUrl($path);

        self::assertEquals(StreamWrapper::PROTOCOL . '://' . $expected, $actual);
    }

    public function samplePaths(): array
    {
        $multibyte = utf8_encode('Déjà_vu');

        return [
            'null path' => [
                'options' => [],
                'path' => null,
                'expected' => '',
            ],
            'needs trim path' => [
                'options' => [],
                'path' => '  /foo  ',
                'expected' => '/foo',
            ],
            'absolute, single' => [
                'options' => [],
                'path' => '/foo',
                'expected' => '/foo',
            ],
            'absolute, nested' => [
                'options' => [],
                'path' => '/foo/bar/../bing bong/./baz/../',
                'expected' => '/foo/bing bong',
            ],
            'relative, single' => [
                'options' => [],
                'path' => 'foo',
                'expected' => 'foo',
            ],
            'relative, nested' => [
                'options' => [],
                'path' => 'foo/bar/../bing bong/./baz/../',
                'expected' => 'foo/bing bong',
            ],
            'excessive relativeness' => [
                'options' => [],
                'path' => '/../../../../../../../.././././.././.././../bar',
                'expected' => '/bar',
            ],
            'leading protocol' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL . ':///foo/../bar',
                'expected' => '/bar',
            ],
            'multibyte support' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL . ':///' . $multibyte . '/υπέρ/../νωθρού',
                'expected' => '/' . $multibyte . '/νωθρού',
            ],
            'invalid separator' => [
                'options' => ['getFileSeparator' => ''],
                'path' => StreamWrapper::PROTOCOL . ':///' . $multibyte . '/υπέρ/../νωθρού/',
                'expected' => '/' . $multibyte . '/υπέρ/../νωθρού/',
            ],
            'dir slash not normalized' => [
                'options' => [],
                'path' => '/foo\\..\\bar/baz/../bur',
                'expected' => '/foo\\..\\bar/bur',
            ],
            'dir slash normalized' => [
                'options' => ['getNormalizeSlashes' => true],
                'path' => '/foo\\..\\bar/baz/../bur',
                'expected' => '/bar/bur',
            ],
            'custom separator' => [
                'options' => ['getFileSeparator' => '>'],
                'path' => '/foo/bar>..>baz>hot/../cakes',
                'expected' => '/foo/bar>baz>hot/../cakes',
            ],
        ];
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
        $partitionA = $this->createPartition(['getName' => 'Τάχιστη']);
        $partitionB = $this->createPartition(['getName' => mb_strtoupper('Τάχιστη')]);

        $this->fixture = new FileSystem(new Config(['ignoreCase' => true]));
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);

        $actual = $this->fixture->getChildren();

        self::assertCount(1, $actual);
        self::assertSame($partitionB, $actual[0]);
    }

    public function testAddChildDoesNotNormalizeNames(): void
    {
        $partitionA = $this->createPartition(['getName' => 'Τάχιστη']);
        $partitionB = $this->createPartition(['getName' => mb_strtoupper('Τάχιστη')]);

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
            FileSystem::class . ' only accepts children that implement ' . PartitionInterface::class
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

        $partitionA = $this->createPartition(['getName' => uniqid()]);
        $partitionB = $this->createPartition(['getName' => $path]);
        $partitionC = $this->createPartition(['getName' => uniqid()]);
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

        $partitionA = $this->createPartition(['getName' => uniqid()]);
        $partitionB = $this->createPartition(['getName' => $path]);
        $partitionC = $this->createPartition(['getName' => uniqid()]);
        $this->fixture->addChild($partitionA);
        $this->fixture->addChild($partitionB);
        $this->fixture->addChild($partitionC);

        if (!$expected) {
            self::expectException(NotFoundException::class);
            self::expectExceptionMessage('Partition "' . $name . '" does not exist.');
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

        $partitionA = $this->createPartition(['getName' => uniqid()]);
        $partitionB = $this->createPartition(['getName' => $path]);
        $partitionC = $this->createPartition(['getName' => uniqid()]);
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
        $partitionA = $this->createPartition(['getName' => uniqid()]);
        $partitionB = $this->createPartition(['getName' => uniqid()]);

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
        $partitionA = $this->createPartition(['getSummary' => $summaryA, 'getName' => uniqid()]);
        $partitionB = $this->createPartition(['getSummary' => $summaryB, 'getName' => uniqid()]);

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
}
