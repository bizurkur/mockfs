<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Quota\QuotaInterface;

/**
 * Represents a partition.
 */
interface PartitionInterface extends DirectoryInterface
{
    /**
     * Gets the quota for the partition.
     *
     * @return QuotaInterface|null
     */
    public function getQuota(): ?QuotaInterface;

    /**
     * Sets the quota for the partition.
     *
     * @param QuotaInterface|null $quota
     *
     * @return PartitionInterface
     */
    public function setQuota(?QuotaInterface $quota): PartitionInterface;
}
