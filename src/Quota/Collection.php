<?php declare(strict_types = 1);

namespace MockFileSystem\Quota;

use MockFileSystem\Quota\QuotaInterface;

/**
 * Class to hold a collection of quotas.
 */
class Collection implements QuotaInterface
{
    /**
     * @var QuotaInterface[]
     */
    private $quotas = [];

    /**
     * @param QuotaInterface[] $quotas
     */
    public function __construct(array $quotas = [])
    {
        foreach ($quotas as $quota) {
            $this->addQuota($quota);
        }
    }

    /**
     * Adds a quota to the collection.
     *
     * @param QuotaInterface $quota
     */
    public function addQuota(QuotaInterface $quota): void
    {
        $this->quotas[] = $quota;
    }

    /**
     * {@inheritDoc}
     */
    public function appliesTo(int $user, int $group): bool
    {
        foreach ($this->quotas as $quota) {
            if ($quota->appliesTo($user, $group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getRemainingSize(int $size, int $user, int $group): int
    {
        foreach ($this->quotas as $quota) {
            $size = $quota->getRemainingSize($size, $user, $group);
            if ($size !== self::UNLIMITED) {
                return $size;
            }
        }

        return self::UNLIMITED;
    }

    /**
     * {@inheritDoc}
     */
    public function getRemainingFileCount(int $fileCount, int $user, int $group): int
    {
        foreach ($this->quotas as $quota) {
            $count = $quota->getRemainingFileCount($fileCount, $user, $group);
            if ($count !== self::UNLIMITED) {
                return $count;
            }
        }

        return self::UNLIMITED;
    }
}
