<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\TestCase;

/**
 * Abstract tests that should be applied to every component.
 */
abstract class ComponentTestCase extends TestCase
{
    protected FileInterface $fixture;

    public function testInstanceOfInterface(): void
    {
        self::assertInstanceOf(FileInterface::class, $this->fixture);
    }

    public function testSetsLastAccessTimeOnConstruction(): void
    {
        $now = time();

        self::assertEqualsWithDelta($now, $this->fixture->getLastAccessTime(), 1);
    }

    public function testSetsLastModifyTimeOnConstruction(): void
    {
        $now = time();

        self::assertEqualsWithDelta($now, $this->fixture->getLastModifyTime(), 1);
    }

    public function testSetsLastChangeTimeOnConstruction(): void
    {
        $now = time();

        self::assertEqualsWithDelta($now, $this->fixture->getLastChangeTime(), 1);
    }

    public function testSetsName(): void
    {
        $name = uniqid();

        $this->fixture->setName($name);

        self::assertEquals($name, $this->fixture->getName());
    }

    public function testSetsNameResponse(): void
    {
        $actual = $this->fixture->setName(uniqid());

        self::assertEquals($this->fixture, $actual);
    }

    /**
     * @dataProvider sampleInvalidNames
     */
    public function testSetInvalidNameThrowsException(array $config, string $name, string $expected): void
    {
        $this->fixture->setConfig(new Config($config));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage($expected);

        $this->fixture->setName($name);
    }

    /**
     * @dataProvider sampleInvalidNames
     */
    public function testSetInvalidNameLateConfigThrowsException(array $config, string $name, string $expected): void
    {
        $this->fixture->setName($name);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage($expected);

        $this->fixture->setConfig(new Config($config));
    }

    public function sampleInvalidNames(): array
    {
        $config = [
            'fileSeparator' => '/',
            'partitionSeparator' => ':',
            'blacklist' => [
                'skipped' => '',
                '>',
                'tab' => "\t",
            ],
        ];

        return [
            'dot' => [
                'config' => $config,
                'name' => '.',
                'expected' => 'Name cannot be "." or ".."',
            ],
            'dotdot' => [
                'config' => $config,
                'name' => '..',
                'expected' => 'Name cannot be "." or ".."',
            ],
            'no name' => [
                'config' => $config,
                'name' => '',
                'expected' => 'Name cannot be empty.',
            ],
            'has fileSeparator' => [
                'config' => $config,
                'name' => 'test/ing',
                'expected' => 'Name cannot contain a "/" character.',
            ],
            'has partitionSeparator' => [
                'config' => $config,
                'name' => 'test:ing',
                'expected' => 'Name cannot contain a ":" character.',
            ],
            'has null character' => [
                'config' => $config,
                'name' => ".\0.",
                'expected' => 'Name cannot contain a "null" character.',
            ],
            'has custom blacklist character, no index' => [
                'config' => $config,
                'name' => 'some>ting',
                'expected' => 'Name cannot contain a ">" character.',
            ],
            'has custom blacklist character, named index' => [
                'config' => $config,
                'name' => "some\tting",
                'expected' => 'Name cannot contain a "tab" character.',
            ],
        ];
    }

    public function testSetConfig(): void
    {
        $config = $this->createMock(ConfigInterface::class);

        $this->fixture->setConfig($config);

        $actual = $this->fixture->getConfig();

        self::assertSame($config, $actual);
    }

    public function testSetConfigSetsUser(): void
    {
        $user = rand();
        $config = $this->createConfiguredMock(ConfigInterface::class, ['getUser' => $user]);

        $this->fixture->setConfig($config);

        $actual = $this->fixture->getUser();

        self::assertSame($user, $actual);
    }

    public function testSetConfigDoesNotSetUser(): void
    {
        $user = rand();
        $config = $this->createConfiguredMock(ConfigInterface::class, ['getUser' => rand()]);

        $this->fixture->setUser($user);
        $this->fixture->setConfig($config);

        $actual = $this->fixture->getUser();

        self::assertSame($user, $actual);
    }

    public function testSetConfigSetsGroup(): void
    {
        $group = rand();
        $config = $this->createConfiguredMock(ConfigInterface::class, ['getGroup' => $group]);

        $this->fixture->setConfig($config);

        $actual = $this->fixture->getGroup();

        self::assertSame($group, $actual);
    }

    public function testSetConfigDoesNotSetGroup(): void
    {
        $group = rand();
        $config = $this->createConfiguredMock(ConfigInterface::class, ['getGroup' => rand()]);

        $this->fixture->setGroup($group);
        $this->fixture->setConfig($config);

        $actual = $this->fixture->getGroup();

        self::assertSame($group, $actual);
    }

    public function testSetConfigDoesNotSetPermissions(): void
    {
        $permissions = rand();
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            ['getUmask' => rand()]
        );

        $this->fixture->setPermissions($permissions);
        $this->fixture->setConfig($config);

        $actual = $this->fixture->getPermissions();

        self::assertSame($permissions, $actual);
    }

    public function testSetConfigResponse(): void
    {
        $config = $this->createMock(ConfigInterface::class);

        $actual = $this->fixture->setConfig($config);

        self::assertSame($this->fixture, $actual);
    }

    public function testGetConfigThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Config not set.');

        $this->fixture->getConfig();
    }

    public function testSetsPermissions(): void
    {
        $permissions = rand();

        $this->fixture->setPermissions($permissions);

        self::assertEquals($permissions, $this->fixture->getPermissions());
    }

    public function testSetPermissionsUpdatesLastChangeTime(): void
    {
        $this->fixture->setLastChangeTime(rand());

        $this->fixture->setPermissions(rand());

        $actual = $this->fixture->getLastChangeTime();

        self::assertEqualsWithDelta(time(), $actual, 1);
    }

    public function testSetsPermissionsResponse(): void
    {
        $actual = $this->fixture->setPermissions(rand());

        self::assertEquals($this->fixture, $actual);
    }

    public function testSetsUser(): void
    {
        $user = rand();

        $this->fixture->setUser($user);

        self::assertEquals($user, $this->fixture->getUser());
    }

    public function testSetUserUpdatesLastChangeTime(): void
    {
        $this->fixture->setLastChangeTime(rand());

        $this->fixture->setUser(rand());

        $actual = $this->fixture->getLastChangeTime();

        self::assertEqualsWithDelta(time(), $actual, 1);
    }

    public function testSetsUserResponse(): void
    {
        $actual = $this->fixture->setUser(rand());

        self::assertEquals($this->fixture, $actual);
    }

    public function testGetPathWhenNoParent(): void
    {
        $name = uniqid();

        $this->fixture->setName($name);

        $actual = $this->fixture->getPath();

        self::assertEquals($name, $actual);
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetPathWhenHasParent(
        array $config,
        string $name,
        string $path,
        string $expected
    ): void {
        $this->fixture->setConfig(new Config($config));
        $parent = $this->createConfiguredMock(
            ContainerInterface::class,
            ['getPath' => $path]
        );
        $this->fixture->setName($name);
        $this->fixture->setParent($parent);

        $actual = $this->fixture->getPath();

        self::assertEquals($expected, $actual);
    }

    public function samplePaths(): array
    {
        $name = uniqid();
        $path = uniqid();
        $separator = array_rand(['/' => true, '\\' => true]);

        return [
            'adds trailing slash' => [
                'config' => ['fileSeparator' => $separator],
                'name' => $name,
                'path' => $path,
                'expected' => $path . $separator . $name,
            ],
            'existing trailing slash' => [
                'config' => ['fileSeparator' => $separator],
                'name' => $name,
                'path' => $path . $separator,
                'expected' => $path . $separator . $name,
            ],
            'multiple trailing slashes' => [
                'config' => ['fileSeparator' => $separator],
                'name' => $name,
                'path' => $path . $separator . $separator,
                'expected' => $path . $separator . $name,
            ],
        ];
    }

    public function testGetUrlWhenNoParent(): void
    {
        $name = uniqid();

        $this->fixture->setName($name);

        $actual = $this->fixture->getUrl();

        self::assertEquals(StreamWrapper::PROTOCOL . '://' . $name, $actual);
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetUrlWhenHasParent(
        array $config,
        string $name,
        string $path,
        string $expected
    ): void {
        $this->fixture->setConfig(new Config($config));
        $parent = $this->createConfiguredMock(
            ContainerInterface::class,
            ['getPath' => $path]
        );
        $this->fixture->setName($name);
        $this->fixture->setParent($parent);

        $actual = $this->fixture->getUrl();

        self::assertEquals(StreamWrapper::PROTOCOL . '://' . $expected, $actual);
    }

    public function testGetParentWhenNoParent(): void
    {
        $actual = $this->fixture->getParent();

        self::assertNull($actual);
    }

    public function testGetParentWhenHasParent(): void
    {
        $parent = $this->createMock(ContainerInterface::class);
        $this->fixture->setParent($parent);

        $actual = $this->fixture->getParent();

        self::assertSame($parent, $actual);
    }

    public function testSetParentResponse(): void
    {
        $parent = $this->createMock(ContainerInterface::class);

        $actual = $this->fixture->setParent($parent);

        self::assertSame($this->fixture, $actual);
    }

    public function testAddToCallsAddChild(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->expects(self::once())
            ->method('addChild')
            ->with($this->fixture);

        $this->fixture->addTo($container);
    }

    public function testAddToResponse(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $actual = $this->fixture->addTo($container);

        self::assertSame($this->fixture, $actual);
    }

    /**
     * @dataProvider sampleIsReadable
     */
    public function testIsReadable(
        ?int $permissions,
        ?int $fileUser,
        ?int $fileGroup,
        int $testUser,
        int $testGroup,
        bool $expected
    ): void {
        if ($permissions !== null) {
            $this->fixture->setPermissions($permissions);
        }
        if ($fileGroup !== null) {
            $this->fixture->setGroup($fileGroup);
        }
        if ($fileUser !== null) {
            $this->fixture->setUser($fileUser);
        }

        $actual = $this->fixture->isReadable($testUser, $testGroup);

        self::assertEquals($expected, $actual);
    }

    public function sampleIsReadable(): array
    {
        return [
            'readable to user' => [
                'permissions' => 0400,
                'fileUser' => 123,
                'fileGroup' => null,
                'testUser' => 123,
                'testGroup' => rand(),
                'expected' => true,
            ],
            'not readable to user' => [
                'permissions' => 0200,
                'fileUser' => 123,
                'fileGroup' => null,
                'testUser' => 123,
                'testGroup' => rand(),
                'expected' => false,
            ],
            'readable to group' => [
                'permissions' => 0040,
                'fileUser' => null,
                'fileGroup' => 123,
                'testUser' => rand(),
                'testGroup' => 123,
                'expected' => true,
            ],
            'not readable to group' => [
                'permissions' => 0020,
                'fileUser' => null,
                'fileGroup' => 123,
                'testUser' => rand(),
                'testGroup' => 123,
                'expected' => false,
            ],
            'readable to other' => [
                'permissions' => 0004,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => true,
            ],
            'not readable to other' => [
                'permissions' => 0002,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => false,
            ],
            'readable to nobody' => [
                'permissions' => 0000,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider sampleIsWritable
     */
    public function testIsWritable(
        ?int $permissions,
        ?int $fileUser,
        ?int $fileGroup,
        int $testUser,
        int $testGroup,
        bool $expected
    ): void {
        if ($permissions !== null) {
            $this->fixture->setPermissions($permissions);
        }
        if ($fileGroup !== null) {
            $this->fixture->setGroup($fileGroup);
        }
        if ($fileUser !== null) {
            $this->fixture->setUser($fileUser);
        }

        $actual = $this->fixture->isWritable($testUser, $testGroup);

        self::assertEquals($expected, $actual);
    }

    public function sampleIsWritable(): array
    {
        return [
            'writable to user' => [
                'permissions' => 0200,
                'fileUser' => 123,
                'fileGroup' => null,
                'testUser' => 123,
                'testGroup' => rand(),
                'expected' => true,
            ],
            'not writable to user' => [
                'permissions' => 0400,
                'fileUser' => 123,
                'fileGroup' => null,
                'testUser' => 123,
                'testGroup' => rand(),
                'expected' => false,
            ],
            'writable to group' => [
                'permissions' => 0020,
                'fileUser' => null,
                'fileGroup' => 123,
                'testUser' => rand(),
                'testGroup' => 123,
                'expected' => true,
            ],
            'not writable to group' => [
                'permissions' => 0040,
                'fileUser' => null,
                'fileGroup' => 123,
                'testUser' => rand(),
                'testGroup' => 123,
                'expected' => false,
            ],
            'writable to other' => [
                'permissions' => 0002,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => true,
            ],
            'not writable to other' => [
                'permissions' => 0004,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => false,
            ],
            'writable to nobody' => [
                'permissions' => 0000,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider sampleIsExecutable
     */
    public function testIsExecutable(
        ?int $permissions,
        ?int $fileUser,
        ?int $fileGroup,
        int $testUser,
        int $testGroup,
        bool $expected
    ): void {
        if ($permissions !== null) {
            $this->fixture->setPermissions($permissions);
        }
        if ($fileGroup !== null) {
            $this->fixture->setGroup($fileGroup);
        }
        if ($fileUser !== null) {
            $this->fixture->setUser($fileUser);
        }

        $actual = $this->fixture->isExecutable($testUser, $testGroup);

        self::assertEquals($expected, $actual);
    }

    public function sampleIsExecutable(): array
    {
        return [
            'executable to user' => [
                'permissions' => 0100,
                'fileUser' => 123,
                'fileGroup' => null,
                'testUser' => 123,
                'testGroup' => rand(),
                'expected' => true,
            ],
            'not executable to user' => [
                'permissions' => 0200,
                'fileUser' => 123,
                'fileGroup' => null,
                'testUser' => 123,
                'testGroup' => rand(),
                'expected' => false,
            ],
            'executable to group' => [
                'permissions' => 0010,
                'fileUser' => null,
                'fileGroup' => 123,
                'testUser' => rand(),
                'testGroup' => 123,
                'expected' => true,
            ],
            'not executable to group' => [
                'permissions' => 0020,
                'fileUser' => null,
                'fileGroup' => 123,
                'testUser' => rand(),
                'testGroup' => 123,
                'expected' => false,
            ],
            'executable to other' => [
                'permissions' => 0001,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => true,
            ],
            'not executable to other' => [
                'permissions' => 0002,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => false,
            ],
            'executable to nobody' => [
                'permissions' => 0000,
                'fileUser' => null,
                'fileGroup' => null,
                'testUser' => rand(),
                'testGroup' => rand(),
                'expected' => false,
            ],
        ];
    }

    public function testSetLastAccessTime(): void
    {
        $time = rand();

        $this->fixture->setLastAccessTime($time);

        $actual = $this->fixture->getLastAccessTime();

        self::assertEquals($time, $actual);
    }

    public function testSetLastAccessTimeNull(): void
    {
        $this->fixture->setLastAccessTime(null);

        $actual = $this->fixture->getLastAccessTime();

        self::assertEqualsWithDelta(time(), $actual, 1);
    }

    public function testSetLastAccessTimeResponse(): void
    {
        $actual = $this->fixture->setLastAccessTime();

        self::assertSame($this->fixture, $actual);
    }

    public function testSetLastModifyTime(): void
    {
        $time = rand();

        $this->fixture->setLastModifyTime($time);

        $actual = $this->fixture->getLastModifyTime();

        self::assertEquals($time, $actual);
    }

    public function testSetLastModifyTimeNull(): void
    {
        $this->fixture->setLastModifyTime(null);

        $actual = $this->fixture->getLastModifyTime();

        self::assertEqualsWithDelta(time(), $actual, 1);
    }

    public function testSetLastModifyTimeResponse(): void
    {
        $actual = $this->fixture->setLastModifyTime();

        self::assertSame($this->fixture, $actual);
    }

    public function testSetLastChangeTime(): void
    {
        $time = rand();

        $this->fixture->setLastChangeTime($time);

        $actual = $this->fixture->getLastChangeTime();

        self::assertEquals($time, $actual);
    }

    public function testSetLastChangeTimeNull(): void
    {
        $this->fixture->setLastChangeTime(null);

        $actual = $this->fixture->getLastChangeTime();

        self::assertEqualsWithDelta(time(), $actual, 1);
    }

    public function testSetLastChangeTimeResponse(): void
    {
        $actual = $this->fixture->setLastChangeTime();

        self::assertSame($this->fixture, $actual);
    }
}
