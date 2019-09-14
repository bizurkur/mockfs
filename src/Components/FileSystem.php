<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\SummaryInterface;
use MockFileSystem\Config;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\LogicException;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\StreamWrapper;

/**
 * Class to represent the entire file system.
 */
class FileSystem implements FileSystemInterface
{
    /**
     * @var PartitionInterface[]
     */
    private $partitions = [];

    /**
     * @var Config
     */
    private $config = null;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(): ?ContainerInterface
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(?ContainerInterface $parent): void
    {
        throw new LogicException('The file system cannot have a parent.');
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getUrl(): string
    {
        $prefix = StreamWrapper::PROTOCOL.'://';

        return $prefix.$this->getPath();
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $path): ?FileInterface
    {
        $sep = $this->config->getSeparator();
        $clean = $path;

        if ($this->config->getNormalizeSlashes()) {
            $clean = str_replace(['\\', '/'], $sep, $clean);
        }

        $parts = explode($sep, $clean, 2);
        if ($parts === false) {
            return null;
        }

        $name = $parts[0].$sep;
        if (!$this->hasChild($name)) {
            return null;
        }

        /** @var PartitionInterface $partition */
        $partition = $this->getChild($name);

        if (isset($parts[1])) {
            return $partition->find($parts[1]);
        }

        return $partition;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren(): array
    {
        return array_values($this->partitions);
    }

    /**
     * {@inheritDoc}
     */
    public function addChild(FileInterface $partition): void
    {
        if (!$partition instanceof PartitionInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s only accepts children that implement %s',
                    self::class,
                    PartitionInterface::class
                )
            );
        }

        $partition->setConfig($this->config);
        $partition->setParent($this);

        $path = $partition->getPath();
        $normalized = $this->normalizeName($path);

        $this->partitions[$normalized] = $partition;
    }

    /**
     * {@inheritDoc}
     */
    public function hasChild(string $name): bool
    {
        $normalized = $this->normalizeName($name);

        return isset($this->partitions[$normalized]);
    }

    /**
     * {@inheritDoc}
     */
    public function getChild(string $name): FileInterface
    {
        $normalized = $this->normalizeName($name);

        if (!isset($this->partitions[$normalized])) {
            throw new NotFoundException(
                sprintf('Partition "%s" does not exist.', $name)
            );
        }

        return $this->partitions[$normalized];
    }

    /**
     * {@inheritDoc}
     */
    public function removeChild(string $name): bool
    {
        $normalized = $this->normalizeName($name);

        if (isset($this->partitions[$normalized])) {
            unset($this->partitions[$normalized]);

            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getSummary(?int $user = null, ?int $group = null): SummaryInterface
    {
        $count = 0;
        $size = 0;

        foreach ($this->partitions as $partition) {
            $summary = $partition->getSummary($user, $group);
            $count += $summary->getFileCount();
            $size += $summary->getSize();
        }

        return new Summary($size, $count);
    }

    /**
     * Normalizes the partition name, if applicable.
     *
     * This only has an effect on case-insensitive systems.
     *
     * @param string $name
     *
     * @return string
     */
    private function normalizeName(string $name): string
    {
        if ($this->config->getIgnoreCase()) {
            $name = mb_strtoupper($name);
        }

        return $name;
    }
}
