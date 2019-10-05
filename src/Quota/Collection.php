<?php declare(strict_types = 1);

namespace MockFileSystem\Quota;

use MockFileSystem\Quota\QuotaInterface;

/**
 * Class to hold a collection of quotas.
 */
final class Collection implements QuotaInterface
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
     * Gets the quotas in the collection.
     *
     * @return QuotaInterface[]
     */
    public function getQuotas(): array
    {
        return $this->quotas;
    }

    /**
     * Gets the number of quotas in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->quotas);
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
    public function getRemainingSize(int $used, int $user, int $group): int
    {
        foreach ($this->quotas as $quota) {
            $remaining = $quota->getRemainingSize($used, $user, $group);
            if ($remaining !== self::UNLIMITED) {
                return $remaining;
            }
        }

        return self::UNLIMITED;
    }

    /**
     * {@inheritDoc}
     */
    public function getRemainingFileCount(int $used, int $user, int $group): int
    {
        foreach ($this->quotas as $quota) {
            $remaining = $quota->getRemainingFileCount($used, $user, $group);
            if ($remaining !== self::UNLIMITED) {
                return $remaining;
            }
        }

        return self::UNLIMITED;
    }
}
