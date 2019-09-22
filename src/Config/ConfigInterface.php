<?php declare(strict_types = 1);

namespace MockFileSystem\Config;

use MockFileSystem\Quota\QuotaInterface;

/**
 * Represents configuration settings for the file system.
 */
interface ConfigInterface
{
    /**
     * ID of root user.
     *
     * This is only used on a non-POSIX system (i.e. Windows) when the user has
     * not been manually set.
     *
     * @var int
     */
    public const ROOT_UID = 0;

    /**
     * ID of root group.
     *
     * This is only used on a non-POSIX system (i.e. Windows) when the group has
     * not been manually set.
     *
     * @var int
     */
    public const ROOT_GID = 0;

    /**
     * Returns the config as an array.
     *
     * @return mixed[]
     */
    public function toArray(): array;

    /**
     * Gets the quota for the file system.
     *
     * @return QuotaInterface
     */
    public function getQuota(): QuotaInterface;

    /**
     * Gets the user ID.
     *
     * On non-POSIX system (i.e. Windows) this returns root (0).
     *
     * @see https://www.php.net/manual/en/function.posix-getuid.php
     *
     * @return int
     */
    public function getUser(): int;

    /**
     * Gets the group ID.
     *
     * On non-POSIX system (i.e. Windows) this returns root (0).
     *
     * @see https://www.php.net/manual/en/function.posix-getgid.php
     *
     * @return int
     */
    public function getGroup(): int;

    /**
     * Gets the umask.
     *
     * @return int
     */
    public function getUmask(): int;

    /**
     * Gets the directory separator.
     *
     * @return string
     */
    public function getSeparator(): string;

    /**
     * Gets whether to ignore string case when creating/accessing files.
     *
     * @return bool
     */
    public function getIgnoreCase(): bool;

    /**
     * Gets whether to include dot files when iterating through directories.
     *
     * @return bool
     */
    public function getIncludeDotFiles(): bool;

    /**
     * Gets whether to normalize slashes in file paths.
     *
     * @return bool
     */
    public function getNormalizeSlashes(): bool;

    /**
     * Gets the blacklist of characters that cannot be used in file names.
     *
     * @return string[]
     */
    public function getBlacklist(): array;

    /**
     * Sets the quota for the file system.
     *
     * @param QuotaInterface $quota
     */
    public function setQuota(QuotaInterface $quota): void;

    /**
     * Sets the user ID.
     *
     * Set to null to default to the real system's user ID.
     *
     * @param int|null $user
     */
    public function setUser(?int $user): void;

    /**
     * Sets the group ID.
     *
     * Set to null to default to the real system's group ID.
     *
     * @param int|null $group
     */
    public function setGroup(?int $group): void;

    /**
     * Sets the umask.
     *
     * @param int $mask
     */
    public function setUmask(int $mask): void;
}
