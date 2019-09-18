<?php declare(strict_types = 1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\Block;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\Partition;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Content\InMemoryContent;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\MockFileSystem;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\TestCase;

class MockFileSystemTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::setUp();

        $wrappers = stream_get_wrappers();
        if (in_array(StreamWrapper::PROTOCOL, $wrappers, true)) {
            stream_wrapper_unregister(StreamWrapper::PROTOCOL);
            MockFileSystem::destroy();
        }
    }

    public function testCreateRegistersStreamWrapper(): void
    {
        MockFileSystem::create();

        $actual = stream_get_wrappers();

        self::assertContains(StreamWrapper::PROTOCOL, $actual);
    }

    public function testCreateCalledTwiceOnlyRegistersOnce(): void
    {
        MockFileSystem::create();
        MockFileSystem::create();

        $actual = stream_get_wrappers();

        self::assertContains(StreamWrapper::PROTOCOL, $actual);
    }

    public function testCreateFailsToRegisterStreamWrapper(): void
    {
        $level = error_reporting();
        error_reporting(0);

        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to register '.StreamWrapper::PROTOCOL.':// protocol.');

        MockFileSystem::create();

        error_reporting($level);
    }

    public function testCreateReturnsFileSystem(): void
    {
        $actual = MockFileSystem::create();

        self::assertInstanceOf(FileSystem::class, $actual);
    }

    /**
     * @dataProvider sampleOptions
     */
    public function testCreateUsingCorrectOptions(array $options, array $expected): void
    {
        $fileSystem = MockFileSystem::create('', null, $options);
        $actual = $fileSystem->getConfig()->toArray();

        self::assertInstanceOf(QuotaInterface::class, $actual['quota']);
        unset($actual['quota']);

        self::assertEquals($expected, $actual);
    }

    public function sampleOptions(): array
    {
        $default = [
            'umask' => 0000,
            'separator' => '/',
            'ignoreCase' => false,
            'includeDotFiles' => true,
            'normalizeSlashes' => false,
            'blacklist' => [],
            'user' => null,
            'group' => null,
        ];

        return [
            'no options' => [
                'options' => [],
                'expected' => $default,
            ],
            'umask' => [
                'options' => ['umask' => 0444],
                'expected' => array_replace($default, ['umask' => 0444]),
            ],
            'separator' => [
                'options' => ['separator' => '\\'],
                'expected' => array_replace($default, ['separator' => '\\']),
            ],
            'ignoreCase' => [
                'options' => ['ignoreCase' => true],
                'expected' => array_replace($default, ['ignoreCase' => true]),
            ],
            'includeDotFiles' => [
                'options' => ['includeDotFiles' => false],
                'expected' => array_replace($default, ['includeDotFiles' => false]),
            ],
            'normalizeSlashes' => [
                'options' => ['normalizeSlashes' => true],
                'expected' => array_replace($default, ['normalizeSlashes' => true]),
            ],
            'blacklist' => [
                'options' => ['blacklist' => ['\\', '>']],
                'expected' => array_replace($default, ['blacklist' => ['\\', '>']]),
            ],
            'user' => [
                'options' => ['user' => 123],
                'expected' => array_replace($default, ['user' => 123]),
            ],
            'group' => [
                'options' => ['group' => 123],
                'expected' => array_replace($default, ['group' => 123]),
            ],
        ];
    }

    public function testCreateUsesPartitionName(): void
    {
        $name = uniqid();

        $actual = MockFileSystem::create($name);

        $partitions = $actual->getChildren();
        self::assertCount(1, $partitions);
        self::assertEquals($name, $partitions[0]->getName());
    }

    public function testCreateUsesPartitionPermissions(): void
    {
        $permissions = 0750;

        $actual = MockFileSystem::create('', $permissions);

        $partitions = $actual->getChildren();
        self::assertCount(1, $partitions);
        self::assertEquals($permissions, $partitions[0]->getPermissions());
    }

    public function testCreateMakesEmptyPartition(): void
    {
        $actual = MockFileSystem::create();

        $summary = $actual->getSummary();
        self::assertEquals(0, $summary->getSize());
        self::assertEquals(0, $summary->getFileCount());
    }

    public function testDestroyUnregistersStreamWrapper(): void
    {
        MockFileSystem::create();
        MockFileSystem::destroy();

        $actual = stream_get_wrappers();

        self::assertNotContains(StreamWrapper::PROTOCOL, $actual);
    }

    public function testDestroyDeletesFileSystem(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::getFileSystem();
        self::assertInstanceOf(FileSystem::class, $actual);

        MockFileSystem::destroy();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::getFileSystem();
    }

    public function testGetUmask(): void
    {
        $umask = 0200;

        MockFileSystem::create('', null, ['umask' => $umask]);

        $actual = MockFileSystem::umask();

        self::assertEquals($umask, $actual);
    }

    public function testSetUmaskReturnsOldUmask(): void
    {
        $umask = 0222;

        MockFileSystem::create('', null, ['umask' => $umask]);

        $actual = MockFileSystem::umask(0777);

        self::assertEquals($umask, $actual);
    }

    public function testSetUmask(): void
    {
        $umask = 0222;

        MockFileSystem::create();
        MockFileSystem::umask($umask);

        $actualB = MockFileSystem::umask();

        self::assertEquals($umask, $actualB);
    }

    public function testUmaskThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::umask();
    }

    public function testCreatePartition(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createPartition(uniqid());

        self::assertInstanceOf(Partition::class, $actual);
    }

    /**
     * @dataProvider samplePartitionNames
     */
    public function testCreatePartitionSetsName(
        array $options,
        string $name,
        string $expectedName,
        string $expectedPath
    ): void {
        MockFileSystem::create('', null, $options);
        MockFileSystem::createPartition($name);

        $partitions = MockFileSystem::getFileSystem()->getChildren();
        self::assertCount(2, $partitions);
        self::assertEquals($expectedName, $partitions[1]->getName(), 'wrong name');
        self::assertEquals($expectedPath, $partitions[1]->getPath(), 'wrong path');
    }

    public function samplePartitionNames(): array
    {
        return [
            'no slash' => [
                'options' => ['separator' => '\\'],
                'name' => 'D:',
                'expectedName' => 'D:',
                'expectedPath' => 'D:\\',
            ],
            'trailing slash' => [
                'options' => ['separator' => '\\'],
                'name' => 'D:\\',
                'expectedName' => 'D:',
                'expectedPath' => 'D:\\',
            ],
            'leading slash' => [
                'options' => [],
                'name' => '/home',
                'expectedName' => 'home',
                'expectedPath' => '/home',
            ],
            'leading slash, wrong slash' => [
                'options' => ['normalizeSlashes' => false],
                'name' => '\\home',
                'expectedName' => '\\home',
                'expectedPath' => '\\home/',
            ],
            'leading slash, normalized slash' => [
                'options' => ['normalizeSlashes' => true],
                'name' => '\\home',
                'expectedName' => 'home',
                'expectedPath' => '/home',
            ],
        ];
    }

    public function testCreatePartitionSetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createPartition(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreatePartitionThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createPartition(uniqid());
    }

    public function testCreateDirectory(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createDirectory(uniqid());

        self::assertInstanceOf(Directory::class, $actual);
    }

    public function testCreateDirectorySetsName(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        $actual = MockFileSystem::createDirectory($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateNestedDirectory(): void
    {
        $nameA = uniqid('a');
        $nameB = uniqid('b');
        $nameC = uniqid('c');

        MockFileSystem::create();

        MockFileSystem::createDirectory('/'.$nameA);
        MockFileSystem::createDirectory('/'.$nameA.'/'.$nameB);
        $actual = MockFileSystem::createDirectory('/'.$nameA.'/'.$nameB.'/'.$nameC);

        self::assertEquals($nameC, $actual->getName());
        self::assertEquals('/'.$nameA.'/'.$nameB.'/'.$nameC, $actual->getPath());
    }

    public function testCreateDirectoryThrowsExceptionWhenMissingParent(): void
    {
        $missing = uniqid('/');

        MockFileSystem::create();

        self::expectException(NotFoundException::class);
        self::expectExceptionMessage('Directory "'.$missing.'" does not exist.');

        MockFileSystem::createDirectory($missing.uniqid('/'));
    }

    public function testCreateDirectoryThrowsExceptionWhenExists(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        MockFileSystem::createDirectory('/'.$name);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Path "/'.$name.'" already exists.');

        MockFileSystem::createDirectory('/'.$name);
    }

    public function testCreateDirectorySetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createDirectory(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateDirectoryThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createDirectory(uniqid());
    }

    public function testCreateFile(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createFile(uniqid());

        self::assertInstanceOf(RegularFile::class, $actual);
    }

    public function testCreateFileSetsName(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        $actual = MockFileSystem::createFile($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateNestedFile(): void
    {
        $nameA = uniqid('a');
        $nameB = uniqid('b');
        $nameC = uniqid('c');

        MockFileSystem::create();

        MockFileSystem::createDirectory('/'.$nameA);
        MockFileSystem::createDirectory('/'.$nameA.'/'.$nameB);
        $actual = MockFileSystem::createFile('/'.$nameA.'/'.$nameB.'/'.$nameC);

        self::assertEquals($nameC, $actual->getName());
        self::assertEquals('/'.$nameA.'/'.$nameB.'/'.$nameC, $actual->getPath());
    }

    public function testCreateFileThrowsExceptionWhenMissingParent(): void
    {
        $missing = uniqid('/');

        MockFileSystem::create();

        self::expectException(NotFoundException::class);
        self::expectExceptionMessage('Directory "'.$missing.'" does not exist.');

        MockFileSystem::createFile($missing.uniqid('/'));
    }

    public function testCreateFileThrowsExceptionWhenExists(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        MockFileSystem::createFile('/'.$name);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Path "/'.$name.'" already exists.');

        MockFileSystem::createFile('/'.$name);
    }

    public function testCreateFileSetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createFile(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateFileSetsContent(): void
    {
        $content = new InMemoryContent(uniqid());

        MockFileSystem::create();

        $actual = MockFileSystem::createFile(uniqid(), null, $content);

        self::assertSame($content, $actual->getContent());
    }

    public function testCreateFileThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createFile(uniqid());
    }

    public function testCreateBlock(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createBlock(uniqid());

        self::assertInstanceOf(Block::class, $actual);
    }

    public function testCreateBlockSetsName(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        $actual = MockFileSystem::createBlock($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateNestedBlock(): void
    {
        $nameA = uniqid('a');
        $nameB = uniqid('b');
        $nameC = uniqid('c');

        MockFileSystem::create();

        MockFileSystem::createDirectory('/'.$nameA);
        MockFileSystem::createDirectory('/'.$nameA.'/'.$nameB);
        $actual = MockFileSystem::createBlock('/'.$nameA.'/'.$nameB.'/'.$nameC);

        self::assertEquals($nameC, $actual->getName());
        self::assertEquals('/'.$nameA.'/'.$nameB.'/'.$nameC, $actual->getPath());
    }

    public function testCreateBlockThrowsExceptionWhenMissingParent(): void
    {
        $missing = uniqid('/');

        MockFileSystem::create();

        self::expectException(NotFoundException::class);
        self::expectExceptionMessage('Directory "'.$missing.'" does not exist.');

        MockFileSystem::createBlock($missing.uniqid('/'));
    }

    public function testCreateBlockThrowsExceptionWhenExists(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        MockFileSystem::createBlock('/'.$name);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Path "/'.$name.'" already exists.');

        MockFileSystem::createBlock('/'.$name);
    }

    public function testCreateBlockSetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createBlock(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateBlockThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createBlock(uniqid());
    }




    /**
     * @dataProvider samplePaths
     */
    public function testGetPath(array $options, string $path, string $expected): void
    {
        MockFileSystem::create('/', null, $options);

        $actual = MockFileSystem::getPath($path);

        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetUrl(array $options, string $path, string $expected): void
    {
        MockFileSystem::create('/', null, $options);

        $actual = MockFileSystem::getUrl($path);

        self::assertEquals(StreamWrapper::PROTOCOL.'://'.$expected, $actual);
    }

    public function samplePaths(): array
    {
        $multibyte = utf8_encode("Déjà_vu");

        return [
            'absolute, single' => [
                'options' => [],
                'path' => '/foo',
                'expected' => '/foo',
            ],
            'absolute, nested' => [
                'options' => [],
                'path' => '/foo/bar/../bing bong/./baz/../',
                'expected' => '/foo/bing bong/',
            ],
            'relative, single' => [
                'options' => [],
                'path' => 'foo',
                'expected' => 'foo',
            ],
            'relative, nested' => [
                'options' => [],
                'path' => 'foo/bar/../bing bong/./baz/../',
                'expected' => 'foo/bing bong/',
            ],
            'excessive relativeness' => [
                'options' => [],
                'path' => '/../../../../../../../.././././.././.././../bar',
                'expected' => '/bar',
            ],
            'leading protocol' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL.':///foo/../bar',
                'expected' => '/bar',
            ],
            'multibyte support' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL.':///'.$multibyte.'/υπέρ/../νωθρού',
                'expected' => '/'.$multibyte.'/νωθρού',
            ],
            'dir slash not normalized' => [
                'options' => [],
                'path' => '/foo\\..\\bar/baz/../bur',
                'expected' => '/foo\\..\\bar/bur',
            ],
            'dir slash normalized' => [
                'options' => ['normalizeSlashes' => true],
                'path' => '/foo\\..\\bar/baz/../bur',
                'expected' => '/bar/bur',
            ],
            'custom separator' => [
                'options' => ['separator' => '>'],
                'path' => '/foo/bar>..>baz>hot/../cakes',
                'expected' => '/foo/bar>baz>hot/../cakes',
            ],
        ];
    }
}
