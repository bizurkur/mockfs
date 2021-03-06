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
     *
     * @var string
     */
    public const TYPE_BLOCK = 0060000;

    /**
     * Character special device.
     *
     * @var string
     */
    public const TYPE_CHAR = 0020000;

    /**
     * Directory.
     *
     * @var string
     */
    public const TYPE_DIR = 0040000;

    /**
     * Named pipe.
     *
     * @var string
     */
    public const TYPE_FIFO = 0010000;

    /**
     * Regular file.
     *
     * @var string
     */
    public const TYPE_FILE = 0100000;

    /**
     * Symbolic link.
     *
     * @var string
     */
    public const TYPE_LINK = 0120000;

    /**
     * Sets the configuration settings used by the file system.
     *
     * @param ConfigInterface $config
     *
     * @return FileInterface
     */
    public function setConfig(ConfigInterface $config): FileInterface;

    /**
     * Gets the configuration settings used by the file system.
     *
     * @return ConfigInterface
     *
     * @throws RuntimeException When not set.
     */
    public function getConfig(): ConfigInterface;

    /**
     * Gets the default permissions.
     *
     * @return int
     */
    public function getDefaultPermissions(): int;

    /**
     * Sets the file permissions.
     *
     * @param int $permissions
     *
     * @return FileInterface
     */
    public function setPermissions(int $permissions): FileInterface;

    /**
     * Gets the file permissions.
     *
     * @return int
     */
    public function getPermissions(): int;

    /**
     * Sets the file owner.
     *
     * @param int $user
     *
     * @return FileInterface
     */
    public function setUser(int $user): FileInterface;

    /**
     * Gets the file owner.
     *
     * @return int
     */
    public function getUser(): int;

    /**
     * Sets the file group.
     *
     * @param int $group
     *
     * @return FileInterface
     */
    public function setGroup(int $group): FileInterface;

    /**
     * Gets the file group.
     *
     * @return int
     */
    public function getGroup(): int;

    /**
     * Checks if the file is readable by the given user and group.
     *
     * @param int $user
     * @param int $group
     *
     * @return bool
     */
    public function isReadable(int $user, int $group): bool;

    /**
     * Checks if the file is writable by the given user and group.
     *
     * @param int $user
     * @param int $group
     *
     * @return bool
     */
    public function isWritable(int $user, int $group): bool;

    /**
     * Checks if the file is executable by the given user and group.
     *
     * @param int $user
     * @param int $group
     *
     * @return bool
     */
    public function isExecutable(int $user, int $group): bool;

    /**
     * Gets the type of the file.
     *
     * @return int
     */
    public function getType(): int;

    /**
     * Gets the name of the file.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Sets the name of the file.
     *
     * @param string $name
     *
     * @return FileInterface
     */
    public function setName(string $name): FileInterface;

    /**
     * Adds this file to the given container.
     *
     * This should remove the file from its current container.
     *
     * @param ContainerInterface $container
     *
     * @return FileInterface
     */
    public function addTo(ContainerInterface $container): FileInterface;

    /**
     * Gets the size of the file.
     *
     * @return int
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
     *
     * @return int
     */
    public function getLastAccessTime(): int;

    /**
     * Gets the time the file was last modified.
     *
     * @return int
     */
    public function getLastModifyTime(): int;

    /**
     * Gets the time the file attributes were last changed.
     *
     * @return int
     */
    public function getLastChangeTime(): int;

    /**
     * Sets the time the file was last accessed.
     *
     * @param int|null $time
     *
     * @return FileInterface
     */
    public function setLastAccessTime(?int $time = null): FileInterface;

    /**
     * Sets the time the file was last modified.
     *
     * @param int|null $time
     *
     * @return FileInterface
     */
    public function setLastModifyTime(?int $time = null): FileInterface;

    /**
     * Sets the time the file attributes were last changed.
     *
     * @param int|null $time
     *
     * @return FileInterface
     */
    public function setLastChangeTime(?int $time = null): FileInterface;
}
