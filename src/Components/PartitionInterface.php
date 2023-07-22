<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\Quota\QuotaManagerInterface;

/**
 * Represents a partition.
 */
interface PartitionInterface extends DirectoryInterface
{
    /**
     * Gets the quota for the partition.
     */
    public function getQuota(): ?QuotaInterface;

    /**
     * Sets the quota for the partition.
     */
    public function setQuota(?QuotaInterface $quota): PartitionInterface;

    /**
     * Gets the quota manager for the partition.
     */
    public function getQuotaManager(): QuotaManagerInterface;

    /**
     * Sets the quota manager for the partition.
     */
    public function setQuotaManager(QuotaManagerInterface $quotaManager): PartitionInterface;
}
