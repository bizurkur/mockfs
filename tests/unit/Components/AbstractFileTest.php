<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Tests\Components\ComponentTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractFileTest extends ComponentTestCase
{
    /**
     * @var AbstractFile&MockObject
     */
    protected FileInterface $fixture;

    private string $name;

    protected function setUp(): void
    {
        parent::setUp();

        $this->name = uniqid();

        $this->fixture = $this->getMockForAbstractClass(
            AbstractFile::class,
            [$this->name]
        );
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(FileInterface::class, $this->fixture);
    }

    public function testSetsNameOnConstruction(): void
    {
        self::assertEquals($this->name, $this->fixture->getName());
    }

    public function testSetsPermissionsOnConstruction(): void
    {
        $permissions = rand();

        $this->fixture = $this->getMockForAbstractClass(
            AbstractFile::class,
            [$this->name, $permissions]
        );

        self::assertEquals($permissions, $this->fixture->getPermissions());
    }

    public function testSetsPermissionsWhenNullOnConstruction(): void
    {
        self::assertEquals(-1, $this->fixture->getPermissions());
    }

    public function testSetConfigSetsPermissions(): void
    {
        $umask = 0222;
        $permissions = 0777;
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            ['getUmask' => $umask]
        );

        $this->fixture->method('getDefaultPermissions')->willReturn($permissions);

        $this->fixture->setConfig($config);

        $actual = $this->fixture->getPermissions();

        self::assertSame($permissions & ~$umask, $actual);
    }

    public function testAddToRemovesChildFromParent(): void
    {
        $parent = $this->createMock(ContainerInterface::class);
        $container = $this->createMock(ContainerInterface::class);

        $this->fixture->setParent($parent);

        $parent->expects(self::once())
            ->method('removeChild')
            ->with($this->name);

        $this->fixture->addTo($container);
    }

    public function testAddToDoesNotRemovesChildFromFileSystem(): void
    {
        $parent = $this->createMock(FileSystemInterface::class);
        $container = $this->createMock(ContainerInterface::class);

        $this->fixture->setParent($parent);

        $parent->expects(self::never())
            ->method('removeChild');

        $this->fixture->addTo($container);
    }

    public function testStat(): void
    {
        $size = rand();
        $type = rand();
        $permissions = rand();
        $user = rand();
        $group = rand();
        $atime = rand();
        $mtime = rand();
        $ctime = rand();

        $this->fixture->method('getSize')->willReturn($size);
        $this->fixture->method('getType')->willReturn($type);
        $this->fixture->setPermissions($permissions);
        $this->fixture->setUser($user);
        $this->fixture->setGroup($group);
        $this->fixture->setLastAccessTime($atime);
        $this->fixture->setLastModifyTime($mtime);
        $this->fixture->setLastChangeTime($ctime);

        $actual = $this->fixture->stat();

        $stat = [
            'dev' => 0,
            'ino' => spl_object_id($this->fixture),
            'mode' => $type | $permissions,
            'nlink' => 1,
            'uid' => $user,
            'gid' => $group,
            'rdev' => 0,
            'size' => $size,
            'atime' => $atime,
            'mtime' => $mtime,
            'ctime' => $ctime,
            'blksize' => -1,
            'blocks' => -1,
        ];
        $expected = array_merge(array_values($stat), $stat);

        self::assertEquals($expected, $actual);
    }
}
