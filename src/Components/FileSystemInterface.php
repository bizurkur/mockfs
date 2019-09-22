<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\ConfigInterface;

/**
 * Represents the entire file system.
 *
 * @see https://en.wikipedia.org/wiki/File_system
 */
interface FileSystemInterface extends ContainerInterface
{
    /**
     * Gets the configuration settings used by the file system.
     *
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface;
}
