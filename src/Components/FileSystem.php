<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ChildInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\Finder;
use MockFileSystem\Components\FinderInterface;
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
     * @var FinderInterface
     */
    private $finder = null;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->finder = new Finder($this);
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
    public function setFinder(FinderInterface $finder): FileSystemInterface
    {
        $this->finder = $finder;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getFinder(): FinderInterface
    {
        return $this->finder;
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
    public function setParent(?ContainerInterface $parent): ChildInterface
    {
        if ($parent !== null) {
            throw new LogicException('The file system cannot have a parent.');
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $path): ?FileInterface
    {
        return $this->finder->find($path);
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(?string $file = null): string
    {
        if ($file === null) {
            return '';
        }

        $clean = trim($file);

        $prefix = StreamWrapper::PROTOCOL.'://';
        $pathPrefix = mb_substr($clean, 0, mb_strlen($prefix));
        if (strcmp(mb_strtoupper($prefix), mb_strtoupper($pathPrefix)) === 0) {
            $clean = mb_substr($clean, mb_strlen($prefix));
        }

        $sep = $this->config->getFileSeparator();
        if ($this->config->getNormalizeSlashes()) {
            $clean = str_replace(['\\', '/'], $sep, $clean);
        }

        if (mb_substr($clean, -strlen($sep)) === $sep) {
            // Remove trailing slash
            $clean = mb_substr($clean, 0, -strlen($sep));
        }

        $parts = explode($sep, $clean);
        if ($parts === false) {
            return $clean;
        }

        $files = [];

        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }

            if ($part !== '..') {
                $files[] = $part;
            } elseif (count($files) > 1) {
                array_pop($files);
            }
        }

        return implode($sep, $files);
    }

    /**
     * {@inheritDoc}
     */
    public function getUrl(?string $file = null): string
    {
        return StreamWrapper::PROTOCOL.'://'.$this->getPath($file);
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

        $name = $partition->getName();
        $normalized = $this->normalizeName($name);

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
        $fileSeparator = $this->config->getFileSeparator();
        $partitionSeparator = $this->config->getPartitionSeparator();

        $name = rtrim($name, $fileSeparator);
        $name = rtrim($name, $partitionSeparator);
        $name = $name.$partitionSeparator.$fileSeparator;

        if ($this->config->getIgnoreCase()) {
            $name = mb_strtoupper($name);
        }

        return $name;
    }
}
