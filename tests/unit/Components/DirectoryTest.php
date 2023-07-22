<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Components\SummaryInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Exception\NoDiskSpaceException;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\Exception\RecursionException;
use MockFileSystem\Quota\QuotaManagerInterface;
use MockFileSystem\Tests\Components\ComponentTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DirectoryTest extends ComponentTestCase
{
    /**
     * @var Directory
     */
    protected FileInterface $fixture;

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

    public function testGetDefaultPermission(): void
    {
        $actual = $this->fixture->getDefaultPermissions();

        self::assertEquals(0777, $actual);
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

    /**
     * @dataProvider sampleGetIterator
     */
    public function testGetIterator(array $files, array $options, array $expected): void
    {
        $config = new Config($options);
        $this->fixture->setConfig($config);

        foreach ($files as $data) {
            $file = $this->createFile($data);
            $this->fixture->addChild($file);
        }

        $iterator = $this->fixture->getIterator();

        $actual = [];
        foreach ($iterator as $file) {
            $actual[] = $file->getName();
        }

        self::assertSame($expected, $actual);
    }

    public function sampleGetIterator(): array
    {
        $nameA = uniqid();
        $nameB = uniqid();

        return [
            'empty dir, no dots' => [
                'files' => [],
                'options' => ['includeDotFiles' => false],
                'expected' => [],
            ],
            'empty dir, with dots' => [
                'files' => [],
                'options' => ['includeDotFiles' => true],
                'expected' => ['.', '..'],
            ],
            'one file, no dots' => [
                'files' => [['getName' => $nameA]],
                'options' => ['includeDotFiles' => false],
                'expected' => [$nameA],
            ],
            'one file, with dots' => [
                'files' => [['getName' => $nameA]],
                'options' => ['includeDotFiles' => true],
                'expected' => ['.', '..', $nameA],
            ],
            'multiple files, no dots' => [
                'files' => [['getName' => $nameA], ['getName' => $nameB]],
                'options' => ['includeDotFiles' => false],
                'expected' => [$nameA, $nameB],
            ],
            'multiple files, with dots' => [
                'files' => [['getName' => $nameA], ['getName' => $nameB]],
                'options' => ['includeDotFiles' => true],
                'expected' => ['.', '..', $nameA, $nameB],
            ],
        ];
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
        $fileA = $this->createFile(['getName' => 'Τάχιστη']);
        $fileB = $this->createFile(['getName' => mb_strtoupper('Τάχιστη')]);

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
        $fileA = $this->createFile(['getName' => 'Τάχιστη']);
        $fileB = $this->createFile(['getName' => mb_strtoupper('Τάχιστη')]);

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

    public function testAddChildPartitionAddsToDirectory(): void
    {
        $child = $this->createPartition(['getName' => uniqid()]);
        $root = $this->createMock(FileSystemInterface::class);
        $this->fixture->setParent($root);
        $this->fixture->setConfig(new Config());

        $this->fixture->addChild($child);

        $actual = $this->fixture->getChildren();

        self::assertCount(1, $actual);
        self::assertSame($child, $actual[0]);
    }

    public function testAddChildPartitionAddsToRootSingleLevel(): void
    {
        $child = $this->createPartition(['getName' => uniqid()]);
        $root = $this->createMock(FileSystemInterface::class);
        $this->fixture->setParent($root);
        $this->fixture->setConfig(new Config());

        $root->expects(self::once())
            ->method('addChild')
            ->with(self::identicalTo($child));

        $this->fixture->addChild($child);
    }

    public function testAddChildPartitionAddsToRootMultipleLevels(): void
    {
        $child = $this->createPartition(['getName' => uniqid()]);
        $root = $this->createMock(FileSystemInterface::class);
        $parentA = $this->createDirectory(['getParent' => $root]);
        $parentB = $this->createDirectory(['getParent' => $parentA]);
        $this->fixture->setParent($parentB);
        $this->fixture->setConfig(new Config());

        $root->expects(self::once())
            ->method('addChild')
            ->with(self::identicalTo($child));

        $this->fixture->addChild($child);
    }

    public function testAddChildPartitionNoRoot(): void
    {
        $child = $this->createPartition(['getName' => uniqid()]);
        $this->fixture->setConfig(new Config());

        $this->fixture->addChild($child);

        $actual = $this->fixture->getChildren();

        self::assertCount(1, $actual);
        self::assertSame($child, $actual[0]);
    }

    public function testAddChildCallsQuotaManager(): void
    {
        $file = $this->createFile();
        $manager = $this->setUpQuotaManager(rand(1, 999));
        $this->fixture->setConfig(new Config());

        $manager->expects(self::once())
            ->method('getFreeDiskSpace')
            ->with($file);

        $this->fixture->addChild($file);
    }

    public function testAddChildWhenLimitedDiskSpace(): void
    {
        $file = $this->createFile();
        $this->setUpQuotaManager(0);
        $this->fixture->setConfig(new Config());

        self::expectException(NoDiskSpaceException::class);
        self::expectExceptionMessage('Not enough disk space');

        $this->fixture->addChild($file);
    }

    public function testAddChildWhenUnlimitedDiskSpace(): void
    {
        $file = $this->createFile();
        $this->setUpQuotaManager(-1);
        $this->fixture->setConfig(new Config());

        $this->fixture->addChild($file);

        $actual = $this->fixture->getChildren();

        self::assertCount(1, $actual);
        self::assertSame($file, $actual[0]);
    }

    public function testGetChildrenEmpty(): void
    {
        self::assertEquals([], $this->fixture->getChildren());
    }

    public function testGetChildrenUpdatesLastAccessTime(): void
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
            self::expectExceptionMessage('Child "' . $searchName . '" does not exist.');
        }

        $actual = $this->fixture->getChild($searchName);

        if ($expected) {
            self::assertSame($fileB, $actual);
        }
    }

    public function testGetChildUpdatesLastAccessTime(): void
    {
        $name = uniqid();
        $file = $this->createFile(['getName' => $name]);
        $this->fixture->setConfig(new Config());
        $this->fixture->addChild($file);
        $this->fixture->setLastAccessTime(rand());

        $this->fixture->getChild($name);

        self::assertEqualsWithDelta(time(), $this->fixture->getLastAccessTime(), 1);
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

    public function testRemoveChildUpdatesLastModifyTime(): void
    {
        $name = uniqid();
        $file = $this->createFile(['getName' => $name]);
        $this->fixture->setConfig(new Config());
        $this->fixture->addChild($file);
        $this->fixture->setLastModifyTime(rand());

        $this->fixture->removeChild($name);

        self::assertEqualsWithDelta(time(), $this->fixture->getLastModifyTime(), 1);
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

    public function testSetConfigWhenNoChildren(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $actual = $this->fixture->getConfig();

        self::assertSame($config, $actual);
    }

    public function testSetConfigSetsChildConfigs(): void
    {
        $config = new Config();
        $this->fixture->setConfig($config);

        $fileA = $this->createFile(['getName' => uniqid()]);
        $fileB = $this->createFile(['getName' => uniqid()]);

        $this->fixture->addChild($fileA);
        $this->fixture->addChild($fileB);

        $fileA->expects(self::once())
            ->method('setConfig')
            ->with(self::identicalTo($config));

        $fileB->expects(self::once())
            ->method('setConfig')
            ->with(self::identicalTo($config));

        $this->fixture->setConfig($config);
    }

    /**
     * @dataProvider sampleSummaryData
     */
    public function testGetSummary(
        array $partitions,
        array $directories,
        array $files,
        ?int $user,
        ?int $group,
        int $expectedSize,
        int $expectedFileCount
    ): void {
        $this->fixture->setConfig(new Config());

        foreach ($partitions as $data) {
            $child = $this->createPartition($data);
            $this->fixture->addChild($child);
        }
        foreach ($directories as $data) {
            $child = $this->createDirectory($data);
            $this->fixture->addChild($child);
        }
        foreach ($files as $data) {
            $child = $this->createFile($data);
            $this->fixture->addChild($child);
        }

        $actual = $this->fixture->getSummary($user, $group);

        self::assertInstanceOf(SummaryInterface::class, $actual);
        self::assertEquals($expectedSize, $actual->getSize(), 'wrong size');
        self::assertEquals($expectedFileCount, $actual->getFileCount(), 'wrong file count');
    }

    public function sampleSummaryData(): array
    {
        $user = rand(100, 199);
        $group = rand(100, 199);
        $sizeA = rand(1, 9999);
        $sizeB = rand(1, 9999);
        $sizeC = rand(1, 9999);
        $sizeD = rand(1, 9999);
        $countA = rand(1, 999);
        $countB = rand(1, 999);
        $countC = rand(1, 999);

        return [
            'no user, no group' => [
                'partitions' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                ],
                'directories' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => $sizeA, 'getFileCount' => $countA],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => $sizeB, 'getFileCount' => $countB],
                    ],
                ],
                'files' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSize' => $sizeC,
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSize' => $sizeD,
                    ],
                ],
                'user' => null,
                'group' => null,
                'expectedSize' => $sizeA + $sizeB + $sizeC + $sizeD,
                'expectedFileCount' => $countA + $countB + 4,
            ],
            'has user, no group' => [
                'partitions' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                ],
                'directories' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => $sizeA, 'getFileCount' => $countA],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => $sizeB, 'getFileCount' => $countB],
                    ],
                ],
                'files' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => rand(),
                        'getSize' => $sizeC,
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSize' => rand(),
                    ],
                ],
                'user' => $user,
                'group' => null,
                'expectedSize' => $sizeA + $sizeB + $sizeC,
                'expectedFileCount' => $countA + $countB + 2,
            ],
            'no user, has group' => [
                'partitions' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => $group,
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                ],
                'directories' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => $group,
                        'getSummary' => ['getSize' => $sizeA, 'getFileCount' => $countA],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => $sizeB, 'getFileCount' => $countB],
                    ],
                ],
                'files' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => rand(),
                        'getSize' => rand(),
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => $group,
                        'getSize' => $sizeD,
                    ],
                ],
                'user' => null,
                'group' => $group,
                'expectedSize' => $sizeA + $sizeB + $sizeD,
                'expectedFileCount' => $countA + $countB + 2,
            ],
            'has user, has group' => [
                'partitions' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => $group,
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => $group,
                        'getSummary' => ['getSize' => rand(), 'getFileCount' => rand()],
                    ],
                ],
                'directories' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => $group,
                        'getSummary' => ['getSize' => $sizeA, 'getFileCount' => $countA],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => rand(),
                        'getSummary' => ['getSize' => $sizeB, 'getFileCount' => $countB],
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => $group,
                        'getSummary' => ['getSize' => $sizeC, 'getFileCount' => $countC],
                    ],
                ],
                'files' => [
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => rand(),
                        'getSize' => rand(),
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => $user,
                        'getGroup' => $group,
                        'getSize' => $sizeD,
                    ],
                    [
                        'getName' => uniqid(),
                        'getUser' => rand(),
                        'getGroup' => $group,
                        'getSize' => rand(),
                    ],
                ],
                'user' => $user,
                'group' => $group,
                'expectedSize' => $sizeA + $sizeB + $sizeC + $sizeD,
                'expectedFileCount' => $countA + $countB + $countC + 2,
            ],
        ];
    }

    public function testSetParentWithSelfThrowsException(): void
    {
        self::expectException(RecursionException::class);
        self::expectExceptionMessage('A parent cannot contain a child reference to itself.');

        $this->fixture->setParent($this->fixture);
    }

    public function testSetParentWithParentOfSelfThrowsException(): void
    {
        $parent = $this->createConfiguredMock(
            ContainerInterface::class,
            ['getParent' => $this->fixture]
        );

        self::expectException(RecursionException::class);
        self::expectExceptionMessage('A parent cannot contain a child reference to itself.');

        $this->fixture->setParent($parent);
    }

    /**
     * @dataProvider sampleIsDot
     */
    public function testIsDot(string $name, bool $expected): void
    {
        $fixture = new Directory($name);

        $actual = $fixture->isDot();

        self::assertEquals($expected, $actual);
    }

    public function sampleIsDot(): array
    {
        return [
            'single dot' => [
                'name' => '.',
                'expected' => true,
            ],
            'double dot' => [
                'name' => '..',
                'expected' => true,
            ],
            'triple dot' => [
                'name' => '...',
                'expected' => false,
            ],
            'starts with dot' => [
                'name' => '.' . uniqid(),
                'expected' => false,
            ],
            'no dot' => [
                'name' => uniqid(),
                'expected' => false,
            ],
        ];
    }

    /**
     * @return FileInterface&MockObject
     */
    private function createFile(array $methods = []): FileInterface
    {
        return $this->createConfiguredMock(FileInterface::class, $methods);
    }

    /**
     * @return DirectoryInterface&MockObject
     */
    private function createDirectory(array $methods = []): DirectoryInterface
    {
        if (isset($methods['getSummary'])) {
            $summary = $this->createConfiguredMock(SummaryInterface::class, $methods['getSummary']);
            $methods['getSummary'] = $summary;
        }

        return $this->createConfiguredMock(DirectoryInterface::class, $methods);
    }

    /**
     * @return PartitionInterface&MockObject
     */
    private function createPartition(array $methods = []): PartitionInterface
    {
        if (isset($methods['getSummary'])) {
            $summary = $this->createConfiguredMock(SummaryInterface::class, $methods['getSummary']);
            $methods['getSummary'] = $summary;
        }

        return $this->createConfiguredMock(PartitionInterface::class, $methods);
    }

    /**
     * @param int $remaining
     *
     * @return QuotaManagerInterface&MockObject
     */
    private function setUpQuotaManager(int $remaining = -1): QuotaManagerInterface
    {
        $manager = $this->createConfiguredMock(
            QuotaManagerInterface::class,
            ['getFreeDiskSpace' => $remaining]
        );
        $partition = $this->createPartition(['getQuotaManager' => $manager]);
        $this->fixture->setParent($partition);

        return $manager;
    }
}
