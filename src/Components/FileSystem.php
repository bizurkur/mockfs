<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\SummaryInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\LogicException;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\StreamWrapper;

/**
 * Class to represent the entire file system.
 */
final class FileSystem implements FileSystemInterface
{
    /**
     * @var PartitionInterface[]
     */
    private $partitions = [];

    /**
     * @var ConfigInterface
     */
    private $config = null;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): ConfigInterface
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
        if ($parent !== null) {
            throw new LogicException('The file system cannot have a parent.');
        }
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
        return StreamWrapper::PROTOCOL.'://';
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $path): ?FileInterface
    {
        $clean = trim($path);

        $sep = $this->config->getSeparator();
        if ($this->config->getNormalizeSlashes()) {
            $clean = str_replace(['\\', '/'], $sep, $clean);
        }

        foreach ($this->partitions as $partition) {
            $prefix = $partition->getPath();
            $length = mb_strlen($prefix);
            $dirPath = mb_substr($clean.$sep, 0, $length);
            $filePath = mb_substr($clean, $length);

            if (strcmp($prefix, $dirPath) === 0) {
                return $partition->find($filePath);
            }

            if ($this->config->getIgnoreCase()
                && strcmp(mb_strtoupper($prefix), mb_strtoupper($dirPath)) === 0
            ) {
                return $partition->find($filePath);
            }
        }

        return null;
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
