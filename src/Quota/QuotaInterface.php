<?php declare(strict_types = 1);

namespace MockFileSystem\Quota;

/**
 * Represents a disk quota.
 *
 * @see https://en.wikipedia.org/wiki/Disk_quota
 */
interface QuotaInterface
{
    /**
     * @var int
     */
    public const UNLIMITED = -1;

    /**
     * Checks if the quota applies to the given user and group.
     *
     * @param int $user User ID to check.
     * @param int $group Group ID to check.
     *
     * @return bool
     */
    public function appliesTo(int $user, int $group): bool;

    /**
     * Gets the remaining number of bytes that can be used.
     *
     * Returns -1 for unlimited space.
     *
     * @param int $size Number of bytes already used.
     * @param int $user User ID to check.
     * @param int $group Group ID to check.
     *
     * @return int
     */
    public function getRemainingSize(int $size, int $user, int $group): int;

    /**
     * Gets the remaining number of files that can be created.
     *
     * Returns -1 for unlimited files.
     *
     * @param int $fileCount Number of files already created.
     * @param int $user User ID to check.
     * @param int $group Group ID to check.
     *
     * @return int
     */
    public function getRemainingFileCount(int $fileCount, int $user, int $group): int;
}
