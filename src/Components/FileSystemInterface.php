<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ChildInterface;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Config;

/**
 * Represents the entire file system.
 *
 * @see https://en.wikipedia.org/wiki/File_system
 */
interface FileSystemInterface extends ChildInterface, ContainerInterface
{
    /**
     * Gets the configuration settings used by the file system.
     *
     * @return Config
     */
    public function getConfig(): Config;
}
