<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\Partition;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\Quota\QuotaManagerInterface;
use MockFileSystem\Tests\Components\ComponentTestCase;

class PartitionTest extends ComponentTestCase
{
    /**
     * @var Partition
     */
    protected $fixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new Partition(uniqid());
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(Directory::class, $this->fixture);
        self::assertInstanceOf(PartitionInterface::class, $this->fixture);
    }

    public function testGetPathWhenNoParent(): void
    {
        $name = uniqid();
        $fileSeparator = '\\';
        $partitionSeparator = ':';
        $config = new Config(
            [
                'fileSeparator' => $fileSeparator,
                'partitionSeparator' => $partitionSeparator,
            ]
        );

        $this->fixture = new Partition($name);
        $this->fixture->setConfig($config);

        $actual = $this->fixture->getPath();

        self::assertEquals($name.$partitionSeparator.$fileSeparator, $actual);
    }

    public function testGetPathWhenParentIsFileSystem(): void
    {
        $name = uniqid();
        $fileSeparator = '\\';
        $partitionSeparator = ':';
        $config = new Config(
            [
                'fileSeparator' => $fileSeparator,
                'partitionSeparator' => $partitionSeparator,
            ]
        );
        $parent = $this->createMock(FileSystemInterface::class);

        $this->fixture = new Partition($name);
        $this->fixture->setConfig($config);
        $this->fixture->setParent($parent);

        $actual = $this->fixture->getPath();

        self::assertEquals($name.$partitionSeparator.$fileSeparator, $actual);
    }

    public function testGetPathWhenNormalParent(): void
    {
        $name = uniqid();
        $path = uniqid();
        $fileSeparator = '\\';
        $config = new Config(['fileSeparator' => $fileSeparator]);
        $parent = $this->createConfiguredMock(
            ContainerInterface::class,
            ['getPath' => $path]
        );

        $this->fixture = new Partition($name);
        $this->fixture->setConfig($config);
        $this->fixture->setParent($parent);

        $actual = $this->fixture->getPath();

        self::assertEquals($path.$fileSeparator.$name, $actual);
    }

    public function testGetPathWhenNormalParentWithTrailingSlash(): void
    {
        $name = uniqid();
        $path = uniqid();
        $fileSeparator = '\\';
        $config = new Config(['fileSeparator' => $fileSeparator]);
        $parent = $this->createConfiguredMock(
            ContainerInterface::class,
            ['getPath' => $path.$fileSeparator]
        );

        $this->fixture = new Partition($name);
        $this->fixture->setConfig($config);
        $this->fixture->setParent($parent);

        $actual = $this->fixture->getPath();

        self::assertEquals($path.$fileSeparator.$name, $actual);
    }

    public function testSetQuotaNull(): void
    {
        $this->fixture->setQuota(null);

        $actual = $this->fixture->getQuota();

        self::assertNull($actual);
    }

    public function testSetQuota(): void
    {
        $quota = $this->createMock(QuotaInterface::class);

        $this->fixture->setQuota($quota);

        $actual = $this->fixture->getQuota();

        self::assertSame($quota, $actual);
    }

    public function testSetQuotaReturnsSelf(): void
    {
        $actual = $this->fixture->setQuota(null);

        self::assertSame($this->fixture, $actual);
    }

    public function testSetEmptyNameIsAllowed(): void
    {
        $this->fixture->setConfig(new Config());

        $this->fixture->setName('');

        $actual = $this->fixture->getName();

        self::assertEquals('', $actual);
    }

    /**
     * {@inheritDoc}
     */
    public function sampleInvalidNames(): array
    {
        $data = parent::sampleInvalidNames();
        unset($data['no name']);

        return $data;
    }

    public function testSetQuotaManager(): void
    {
        $manager = $this->createMock(QuotaManagerInterface::class);

        $this->fixture->setQuotaManager($manager);

        $actual = $this->fixture->getQuotaManager();

        self::assertSame($manager, $actual);
    }

    public function testSetQuotaManagerReturnsSelf(): void
    {
        $manager = $this->createMock(QuotaManagerInterface::class);

        $actual = $this->fixture->setQuotaManager($manager);

        self::assertSame($this->fixture, $actual);
    }
}
