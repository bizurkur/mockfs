<?php

declare(strict_types=1);

namespace MockFileSystem\Quota;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Quota\QuotaManagerInterface;

class QuotaManager implements QuotaManagerInterface
{
    private PartitionInterface $partition;

    public function __construct(PartitionInterface $partition)
    {
        $this->partition = $partition;
    }

    /**
     * Checks if there's enough free disk space for the child file.
     *
     * If no child is given, returns the remaining disk space.
     *
     * This uses the current user to determine if there are any quotas that apply.
     *
     * @return int Returns -1 for unlimited space.
     */
    public function getFreeDiskSpace(?FileInterface $child = null): int
    {
        $config = $this->partition->getConfig();
        $user = $config->getUser();
        $group = $config->getGroup();

        $quota = $this->getQuotaForChild($child, $user, $group);
        if ($quota === null) {
            return QuotaInterface::UNLIMITED;
        }

        $summary = $this->partition->getSummary($user, $group);
        $usedCount = $summary->getFileCount();
        $usedSize = $summary->getSize();
        $remainingCount = $quota->getRemainingFileCount($usedCount, $user, $group);
        $remainingSize = $quota->getRemainingSize($usedSize, $user, $group);

        if ($remainingCount === 0 || $remainingSize === 0) {
            // Out of space or out of files
            return 0;
        }

        return $this->getRemainingSizeForChild($child, $remainingSize, $remainingCount);
    }

    /**
     * Gets the quota for the child, if one exists.
     */
    private function getQuotaForChild(
        ?FileInterface $child,
        int $user,
        int $group
    ): ?QuotaInterface {
        if ($child instanceof PartitionInterface) {
            // Partitions don't count against quotas
            return null;
        }

        $quota = $this->partition->getQuota();
        if ($quota === null) {
            // No quota set
            return null;
        }

        if (!$quota->appliesTo($user, $group)) {
            // Quota doesn't apply to the current user
            return null;
        }

        return $quota;
    }

    /**
     * Gets the remaining file size, accounting for the child size.
     */
    private function getRemainingSizeForChild(
        ?FileInterface $child,
        int $remainingSize,
        int $remainingCount
    ): int {
        if ($child === null) {
            return $remainingSize;
        }

        if (!$child instanceof ContainerInterface) {
            return max(0, $remainingSize - $child->getSize());
        }

        $childSummary = $child->getSummary();

        if (
            $remainingCount !== QuotaInterface::UNLIMITED
            && $childSummary->getFileCount() > $remainingCount
        ) {
            return 0;
        }

        if ($remainingSize !== QuotaInterface::UNLIMITED) {
            return max(0, $remainingSize - $childSummary->getSize());
        }

        return QuotaInterface::UNLIMITED;
    }
}
