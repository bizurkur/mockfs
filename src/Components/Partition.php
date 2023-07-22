<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\Directory;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\Quota\QuotaManager;
use MockFileSystem\Quota\QuotaManagerInterface;

/**
 * Class to represent a partition.
 */
final class Partition extends Directory implements PartitionInterface
{
    private ?QuotaInterface $quota = null;

    private QuotaManagerInterface $quotaManager;

    public function __construct(string $name, ?int $permissions = null)
    {
        parent::__construct($name, $permissions);
        $this->quotaManager = new QuotaManager($this);
    }

    public function getPath(): string
    {
        $config = $this->getConfig();

        $fileSeparator = $config->getFileSeparator();

        $name = $this->getName();
        $parent = $this->getParent();
        if ($parent === null || $parent instanceof FileSystemInterface) {
            $partitionSeparator = $config->getPartitionSeparator();

            // Always end root partitions with a slash, e.g. / or C:\
            return $name . $partitionSeparator . $fileSeparator;
        }

        return rtrim($parent->getPath(), $fileSeparator) . $fileSeparator . $name;
    }

    public function getQuota(): ?QuotaInterface
    {
        return $this->quota;
    }

    public function setQuota(?QuotaInterface $quota): PartitionInterface
    {
        $this->quota = $quota;

        return $this;
    }

    public function getQuotaManager(): QuotaManagerInterface
    {
        return $this->quotaManager;
    }

    public function setQuotaManager(QuotaManagerInterface $quotaManager): PartitionInterface
    {
        $this->quotaManager = $quotaManager;

        return $this;
    }

    protected function allowEmptyName(): bool
    {
        return true;
    }
}
