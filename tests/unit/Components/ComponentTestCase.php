<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Abstract tests that should be applied to every component.
 */
abstract class ComponentTestCase extends TestCase
{
    /**
     * @var FileInterface
     */
    protected $fixture = null;

    public function testInstanceOfInterface(): void
    {
        self::assertInstanceOf(FileInterface::class, $this->fixture);
    }

    public function testSetsName(): void
    {
        $name = uniqid();

        $this->fixture->setName($name);

        self::assertEquals($name, $this->fixture->getName());
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

    public function testSetsPermissions(): void
    {
        $permissions = rand();

        $this->fixture->setPermissions($permissions);

        self::assertEquals($permissions, $this->fixture->getPermissions());
    }
}
