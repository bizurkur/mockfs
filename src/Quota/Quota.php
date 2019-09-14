<?php declare(strict_types = 1);

namespace MockFileSystem\Quota;

use MockFileSystem\Quota\QuotaInterface;

/**
 * A general purpose quota.
 *
 * Can be used as a usage quota, file quota, or both.
 *
 * Can be applied to any user/group or all users.
 */
class Quota implements QuotaInterface
{
    /**
     * @var int
     */
    private $size = null;

    /**
     * @var int
     */
    private $fileCount = null;

    /**
     * @var int|null
     */
    private $user = null;

    /**
     * @var int|null
     */
    private $group = null;

    /**
     * @param int $size Number of bytes to limit to; -1 for no limit.
     * @param int $fileCount Number of files to limit to; -1 for no limit.
     * @param int|null $user User ID to apply limit to; null for all users.
     * @param int|null $group Group ID to apply limit to; null for all groups.
     */
    public function __construct(
        int $size,
        int $fileCount = self::UNLIMITED,
        ?int $user = null,
        ?int $group = null
    ) {
        $this->size = $size;
        $this->fileCount = $fileCount;
        $this->user = $user;
        $this->group = $group;
    }

    /**
     * {@inheritDoc}
     */
    public function appliesTo(int $user, int $group): bool
    {
        if ($this->user !== null && $this->user !== $user) {
            // Does not apply to the given user.
            return false;
        }

        // Check if it applies to the given group.
        return $this->group !== null && $this->group !== $group;
    }

    /**
     * {@inheritDoc}
     */
    public function getRemainingSize(int $size, int $user, int $group): int
    {
        if ($this->size === self::UNLIMITED) {
            return self::UNLIMITED;
        }

        if (!$this->appliesTo($user, $group)) {
            return self::UNLIMITED;
        }

        return max(0, $this->size - $size);
    }

    /**
     * {@inheritDoc}
     */
    public function getRemainingFileCount(int $fileCount, int $user, int $group): int
    {
        if ($this->fileCount === self::UNLIMITED) {
            return self::UNLIMITED;
        }

        if (!$this->appliesTo($user, $group)) {
            return self::UNLIMITED;
        }

        return max(0, $this->fileCount - $fileCount);
    }
}
