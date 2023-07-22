<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FinderInterface;
use MockFileSystem\Config\ConfigInterface;

/**
 * Represents the entire file system.
 *
 * @see https://en.wikipedia.org/wiki/File_system
 */
interface FileSystemInterface extends ContainerInterface
{
    /**
     * Gets the configuration settings used by the file system.
     */
    public function getConfig(): ConfigInterface;

    /**
     * Sets the finder used by the file system.
     */
    public function setFinder(FinderInterface $finder): FileSystemInterface;

    /**
     * Gets the finder used by the file system.
     */
    public function getFinder(): FinderInterface;

    /**
     * Finds a file, if it exists.
     */
    public function find(string $path): ?FileInterface;

    /**
     * Gets the full path for the file.
     *
     * If no file is given, returns the path of the file system.
     *
     * Automatically removes the stream wrapper prefix, if applicable.
     *
     * Examples:
     * - /foo => /foo
     * - mfs:///foo => /foo
     * - mfs:///foo/./bar/baz/../ => /foo/bar/
     */
    public function getPath(?string $file = null): string;

    /**
     * Gets the full URL for the file.
     *
     * If no file is given, returns the URL of the file system.
     *
     * This is the same as the path, except includes the Mock File System prefix.
     *
     * Examples:
     * - /foo => mfs:///foo
     * - mfs:///foo => mfs:///foo
     * - /foo/./bar/baz/../ => mfs:///foo/bar/
     */
    public function getUrl(?string $file = null): string;
}
