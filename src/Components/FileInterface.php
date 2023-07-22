<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ChildInterface;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\ReferenceableInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\RuntimeException;

/**
 * Represents any file.
 *
 * This could be a regular file, directory, block, link, etc.
 */
interface FileInterface extends ChildInterface, ReferenceableInterface
{
    /**
     * Block special device.
     */
    public const TYPE_BLOCK = 0060000;

    /**
     * Character special device.
     */
    public const TYPE_CHAR = 0020000;

    /**
     * Directory.
     */
    public const TYPE_DIR = 0040000;

    /**
     * Named pipe.
     */
    public const TYPE_FIFO = 0010000;

    /**
     * Regular file.
     */
    public const TYPE_FILE = 0100000;

    /**
     * Symbolic link.
     */
    public const TYPE_LINK = 0120000;

    /**
     * Sets the configuration settings used by the file system.
     */
    public function setConfig(ConfigInterface $config): FileInterface;

    /**
     * Gets the configuration settings used by the file system.
     *
     * @throws RuntimeException When not set.
     */
    public function getConfig(): ConfigInterface;

    /**
     * Gets the default permissions.
     */
    public function getDefaultPermissions(): int;

    /**
     * Sets the file permissions.
     */
    public function setPermissions(int $permissions): FileInterface;

    /**
     * Gets the file permissions.
     */
    public function getPermissions(): int;

    /**
     * Sets the file owner.
     */
    public function setUser(int $user): FileInterface;

    /**
     * Gets the file owner.
     */
    public function getUser(): int;

    /**
     * Sets the file group.
     */
    public function setGroup(int $group): FileInterface;

    /**
     * Gets the file group.
     */
    public function getGroup(): int;

    /**
     * Checks if the file is readable by the given user and group.
     */
    public function isReadable(int $user, int $group): bool;

    /**
     * Checks if the file is writable by the given user and group.
     */
    public function isWritable(int $user, int $group): bool;

    /**
     * Checks if the file is executable by the given user and group.
     */
    public function isExecutable(int $user, int $group): bool;

    /**
     * Gets the type of the file.
     */
    public function getType(): int;

    /**
     * Gets the name of the file.
     */
    public function getName(): string;

    /**
     * Sets the name of the file.
     */
    public function setName(string $name): FileInterface;

    /**
     * Adds this file to the given container.
     *
     * This should remove the file from its current container.
     */
    public function addTo(ContainerInterface $container): FileInterface;

    /**
     * Gets the size of the file.
     */
    public function getSize(): int;

    /**
     * Gets the file stats.
     *
     * This MUST return both numeric and associative keys.
     *
     * @see https://www.php.net/manual/en/function.stat.php
     *
     * @return int[]
     */
    public function stat(): array;

    /**
     * Gets the time the file was last accessed.
     */
    public function getLastAccessTime(): int;

    /**
     * Gets the time the file was last modified.
     */
    public function getLastModifyTime(): int;

    /**
     * Gets the time the file attributes were last changed.
     */
    public function getLastChangeTime(): int;

    /**
     * Sets the time the file was last accessed.
     */
    public function setLastAccessTime(?int $time = null): FileInterface;

    /**
     * Sets the time the file was last modified.
     */
    public function setLastModifyTime(?int $time = null): FileInterface;

    /**
     * Sets the time the file attributes were last changed.
     */
    public function setLastChangeTime(?int $time = null): FileInterface;
}
