<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Config\WindowsConfig;
use MockFileSystem\MockFileSystem;
use PHPUnit\Framework\TestCase;

/**
 * Test different configuration settings
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class ConfigTest extends TestCase
{
    public function testWindowsConfig(): void
    {
        MockFileSystem::create('c:\\', null, [], new WindowsConfig());

        $actualNames = [];
        $actualPaths = [];
        foreach (MockFileSystem::getFileSystem()->getChildren() as $child) {
            $actualNames[] = $child->getName();
            $actualPaths[] = $child->getPath();
        }

        self::assertEquals(['c'], $actualNames, 'wrong names');
        self::assertEquals(['c:\\'], $actualPaths, 'wrong paths');
    }

    public function testWindowsConfigIgnoresCase(): void
    {
        MockFileSystem::create('c:\\', null, [], new WindowsConfig());
        $base = MockFileSystem::getUrl('c:\\windows');
        $path = $base . '\\test.txt';
        mkdir($base);
        file_put_contents($path, uniqid());

        self::assertTrue(file_exists($path), 'file does not exist in normal case');
        self::assertTrue(file_exists(strtoupper($path)), 'file does not exist in uppercase');
    }

    public function testDefaultConfig(): void
    {
        MockFileSystem::create();

        $actualNames = [];
        $actualPaths = [];
        foreach (MockFileSystem::getFileSystem()->getChildren() as $child) {
            $actualNames[] = $child->getName();
            $actualPaths[] = $child->getPath();
        }

        self::assertEquals([''], $actualNames, 'wrong names');
        self::assertEquals(['/'], $actualPaths, 'wrong paths');
    }
}
