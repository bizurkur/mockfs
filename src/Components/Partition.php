<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\Directory;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Quota\QuotaInterface;

/**
 * Class to represent a partition.
 */
final class Partition extends Directory implements PartitionInterface
{
    /**
     * @var QuotaInterface|null
     */
    private $quota = null;

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        $config = $this->getConfig();

        $fileSeparator = $config->getFileSeparator();

        $name = $this->getName();
        $parent = $this->getParent();
        if ($parent === null || $parent instanceof FileSystemInterface) {
            $partitionSeparator = $config->getPartitionSeparator();

            // Always end root partitions with a slash, e.g. / or C:\
            return $name.$partitionSeparator.$fileSeparator;
        }

        return rtrim($parent->getPath(), $fileSeparator).$fileSeparator.$name;
    }

    /**
     * {@inheritDoc}
     */
    public function getQuota(): ?QuotaInterface
    {
        return $this->quota;
    }

    /**
     * {@inheritDoc}
     */
    public function setQuota(?QuotaInterface $quota): void
    {
        $this->quota = $quota;
    }
}
