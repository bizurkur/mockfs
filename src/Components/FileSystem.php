<?php

declare(strict_types=1);

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
    private array $partitions = [];

    private ConfigInterface $config;

    private FinderInterface $finder;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $this->finder = new Finder($this);
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    public function setFinder(FinderInterface $finder): FileSystemInterface
    {
        $this->finder = $finder;

        return $this;
    }

    public function getFinder(): FinderInterface
    {
        return $this->finder;
    }

    public function getParent(): ?ContainerInterface
    {
        return null;
    }

    public function setParent(?ContainerInterface $parent): ChildInterface
    {
        if ($parent !== null) {
            throw new LogicException('The file system cannot have a parent.');
        }

        return $this;
    }

    public function find(string $path): ?FileInterface
    {
        return $this->finder->find($path);
    }

    public function getPath(?string $file = null): string
    {
        if ($file === null) {
            return '';
        }

        $separator = $this->config->getFileSeparator();
        $clean = $this->sanitizePath($file, $separator);

        $parts = explode($separator, $clean);
        if ($parts === false) {
            return $clean;
        }

        return $this->resolvePath($parts, $separator);
    }

    public function getUrl(?string $file = null): string
    {
        return StreamWrapper::PROTOCOL . '://' . $this->getPath($file);
    }

    public function getChildren(): array
    {
        return array_values($this->partitions);
    }

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

    public function hasChild(string $name): bool
    {
        $normalized = $this->normalizeName($name);

        return isset($this->partitions[$normalized]);
    }

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

    public function removeChild(string $name): bool
    {
        $normalized = $this->normalizeName($name);

        if (isset($this->partitions[$normalized])) {
            unset($this->partitions[$normalized]);

            return true;
        }

        return false;
    }

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
     */
    private function normalizeName(string $name): string
    {
        $fileSeparator = $this->config->getFileSeparator();
        $partitionSeparator = $this->config->getPartitionSeparator();

        $name = rtrim($name, $fileSeparator);
        $name = rtrim($name, $partitionSeparator);
        $name .= $partitionSeparator . $fileSeparator;

        if ($this->config->getIgnoreCase()) {
            $name = mb_strtoupper($name);
        }

        return $name;
    }

    /**
     * Sanitizes a path.
     *
     * - Removes the Mock File System prefix, if present
     * - Normalizes slashes, if enabled
     * - Removes any trailing separator
     */
    private function sanitizePath(string $path, string $separator): string
    {
        $clean = trim($path);

        $prefix = StreamWrapper::PROTOCOL . '://';
        $pathPrefix = mb_substr($clean, 0, mb_strlen($prefix));
        if (strcmp(mb_strtoupper($prefix), mb_strtoupper($pathPrefix)) === 0) {
            $clean = mb_substr($clean, mb_strlen($prefix));
        }

        if ($this->config->getNormalizeSlashes()) {
            $clean = str_replace(['\\', '/'], $separator, $clean);
        }

        if (mb_substr($clean, -strlen($separator)) === $separator) {
            // Remove trailing slash
            $clean = mb_substr($clean, 0, -strlen($separator));
        }

        return $clean;
    }

    /**
     * Resolves the path parts into a usable string.
     *
     * Removes dot navigation from the parts.
     *
     * @param string[] $parts
     */
    private function resolvePath(array $parts, string $separator): string
    {
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

        return implode($separator, $files);
    }
}
