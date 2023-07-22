<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Config;

use MockFileSystem\Config\Config;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testInstanceOf(): void
    {
        $fixture = new Config();

        self::assertInstanceOf(ConfigInterface::class, $fixture);
    }

    public function testGetDefaultOptions(): void
    {
        $fixture = new Config();

        $actual = $fixture->getDefaultOptions();

        $expected = [
            'umask' => 0000,
            'fileSeparator' => '/',
            'partitionSeparator' => '',
            'ignoreCase' => false,
            'includeDotFiles' => true,
            'normalizeSlashes' => false,
            'blacklist' => [],
            'user' => null,
            'group' => null,
        ];
        self::assertEquals($expected, $actual);
    }

    public function testUnknownOptionsThrowsException(): void
    {
        $name = uniqid();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Unknown option "' . $name . '"');

        new Config([$name => uniqid()]);
    }

    /**
     * @dataProvider sampleOptions
     */
    public function testOptions(array $options, array $expected): void
    {
        $config = new Config($options);
        $actual = $config->toArray();

        self::assertEquals($expected, $actual);
    }

    public function sampleOptions(): array
    {
        $default = [
            'umask' => 0000,
            'fileSeparator' => '/',
            'partitionSeparator' => '',
            'ignoreCase' => false,
            'includeDotFiles' => true,
            'normalizeSlashes' => false,
            'blacklist' => [],
            'user' => function_exists('posix_getuid') ? posix_getuid() : Config::ROOT_UID,
            'group' => function_exists('posix_getgid') ? posix_getgid() : Config::ROOT_GID,
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
            'fileSeparator' => [
                'options' => ['fileSeparator' => '\\'],
                'expected' => array_replace($default, ['fileSeparator' => '\\']),
            ],
            'partitionSeparator' => [
                'options' => ['partitionSeparator' => ':'],
                'expected' => array_replace($default, ['partitionSeparator' => ':']),
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

    public function testBlankSeparatorThrowsException(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Separator cannot be empty');

        new Config(['fileSeparator' => '']);
    }

    public function testGetUserWhenNotSet(): void
    {
        $config = new Config();

        $expected = function_exists('posix_getuid') ? posix_getuid() : Config::ROOT_UID;
        self::assertEquals($expected, $config->getUser());
    }

    public function testGetUserWhenSet(): void
    {
        $user = rand();
        $config = new Config(['user' => $user]);

        self::assertEquals($user, $config->getUser());
    }

    public function testSetUser(): void
    {
        $user = rand();

        $config = new Config();
        $config->setUser($user);

        self::assertEquals($user, $config->getUser());
    }

    public function testSetUserNull(): void
    {
        $config = new Config();
        $config->setUser(null);

        $expected = function_exists('posix_getuid') ? posix_getuid() : Config::ROOT_UID;
        self::assertEquals($expected, $config->getUser());
    }

    public function testGetGroupWhenNotSet(): void
    {
        $config = new Config();

        $expected = function_exists('posix_getgid') ? posix_getgid() : Config::ROOT_GID;
        self::assertEquals($expected, $config->getGroup());
    }

    public function testGetGroupWhenSet(): void
    {
        $group = rand();
        $config = new Config(['group' => $group]);

        self::assertEquals($group, $config->getGroup());
    }

    public function testSetGroup(): void
    {
        $group = rand();

        $config = new Config();
        $config->setGroup($group);

        self::assertEquals($group, $config->getGroup());
    }

    public function testSetGroupNull(): void
    {
        $config = new Config();
        $config->setGroup(null);

        $expected = function_exists('posix_getgid') ? posix_getgid() : Config::ROOT_GID;
        self::assertEquals($expected, $config->getGroup());
    }

    public function testGetUmaskWhenNotSet(): void
    {
        $config = new Config();

        self::assertEquals(0000, $config->getUmask());
    }

    public function testGetUmaskWhenSet(): void
    {
        $umask = rand();

        $config = new Config(['umask' => $umask]);

        self::assertEquals($umask & 0777, $config->getUmask());
    }

    public function testSetUmask(): void
    {
        $umask = rand();

        $config = new Config();
        $config->setUmask($umask);

        self::assertEquals($umask & 0777, $config->getUmask());
    }

    public function testGetFileSeparatorWhenNotSet(): void
    {
        $config = new Config();

        self::assertEquals('/', $config->getFileSeparator());
    }

    public function testGetFileSeparatorWhenSet(): void
    {
        $separator = chr(rand(65, 90));

        $config = new Config(['fileSeparator' => $separator]);

        self::assertEquals($separator, $config->getFileSeparator());
    }

    public function testGetPartitionSeparatorWhenNotSet(): void
    {
        $config = new Config();

        self::assertEquals('', $config->getPartitionSeparator());
    }

    public function testGetPartitionSeparatorWhenSet(): void
    {
        $separator = substr(uniqid(), rand(1, 3), 1);

        $config = new Config(['partitionSeparator' => $separator]);

        self::assertEquals($separator, $config->getPartitionSeparator());
    }

    public function testGetIgnoreCaseWhenNotSet(): void
    {
        $config = new Config();

        self::assertFalse($config->getIgnoreCase());
    }

    public function testGetIgnoreCaseWhenSet(): void
    {
        $bool = (bool) rand(0, 1);

        $config = new Config(['ignoreCase' => $bool]);

        self::assertEquals($bool, $config->getIgnoreCase());
    }

    public function testGetIncludeDotFilesWhenNotSet(): void
    {
        $config = new Config();

        self::assertTrue($config->getIncludeDotFiles());
    }

    public function testGetIncludeDotFilesWhenSet(): void
    {
        $bool = (bool) rand(0, 1);

        $config = new Config(['includeDotFiles' => $bool]);

        self::assertEquals($bool, $config->getIncludeDotFiles());
    }

    public function testGetNormalizeSlashesWhenNotSet(): void
    {
        $config = new Config();

        self::assertFalse($config->getNormalizeSlashes());
    }

    public function testGetNormalizeSlashesWhenSet(): void
    {
        $bool = (bool) rand(0, 1);

        $config = new Config(['normalizeSlashes' => $bool]);

        self::assertEquals($bool, $config->getNormalizeSlashes());
    }

    public function testGetBlacklistWhenNotSet(): void
    {
        $config = new Config();

        self::assertEquals([], $config->getBlacklist());
    }

    public function testGetBlacklistWhenSet(): void
    {
        $blacklist = [uniqid(), 'foo' => uniqid()];

        $config = new Config(['blacklist' => $blacklist]);

        self::assertEquals($blacklist, $config->getBlacklist());
    }
}
