<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\NoDiskSpaceException;
use MockFileSystem\Exception\NotFoundException;

/**
 * Class to represent a single directory.
 */
class Directory extends AbstractFile implements DirectoryInterface
{
    /**
     * @var FileInterface[]
     */
    private $children = [];

    /**
     * @param string $name
     * @param int|null $permissions
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        parent::__construct($name, $permissions);
        $this->type = self::TYPE_DIR;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(ConfigInterface $config): FileInterface
    {
        parent::setConfig($config);

        foreach ($this->children as $child) {
            $child->setConfig($config);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultPermissions(): int
    {
        return 0777;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileCount(): int
    {
        return count($this->children);
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Iterator
    {
        $children = $this->getChildren();

        if ($this->getConfig()->getIncludeDotFiles()) {
            array_unshift($children, new Directory('.'), new Directory('..'));
        }

        return new \ArrayIterator($children);
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren(): array
    {
        $this->setLastAccessTime();

        return array_values($this->children);
    }

    /**
     * {@inheritDoc}
     */
    public function addChild(FileInterface $child): void
    {
        $child->setConfig($this->getConfig());

        if ($this->getFreeDiskSpace($child) === 0) {
            throw new NoDiskSpaceException('Not enough disk space');
        }

        if ($child instanceof PartitionInterface) {
            $root = $this->getRoot();
            if ($root) {
                $root->addChild($child);
            }
        }

        $child->setParent($this);
        $this->setLastModifyTime();

        $name = $child->getName();
        $normalized = $this->normalizeName($name);

        $this->children[$normalized] = $child;
    }

    /**
     * {@inheritDoc}
     */
    public function hasChild(string $name): bool
    {
        $normalized = $this->normalizeName($name);

        return isset($this->children[$normalized]);
    }

    /**
     * {@inheritDoc}
     */
    public function getChild(string $name): FileInterface
    {
        $normalized = $this->normalizeName($name);

        if (!isset($this->children[$normalized])) {
            throw new NotFoundException(
                sprintf('Child "%s" does not exist.', $name)
            );
        }

        $this->setLastAccessTime();

        return $this->children[$normalized];
    }

    /**
     * {@inheritDoc}
     */
    public function removeChild(string $name): bool
    {
        $normalized = $this->normalizeName($name);

        if (isset($this->children[$normalized])) {
            unset($this->children[$normalized]);

            $this->setLastModifyTime();

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

        foreach ($this->children as $child) {
            if ($child instanceof PartitionInterface) {
                // Don't count child partitions.
                // They have their own summary
                continue;
            }

            $this->addChildSummary($child, $user, $group, $count, $size);
        }

        return new Summary($size, $count);
    }

    /**
     * Adds a file to the summary.
     *
     * @param FileInterface $child
     * @param int|null $user
     * @param int|null $group
     * @param int $count
     * @param int $size
     */
    private function addChildSummary(
        FileInterface $child,
        ?int $user,
        ?int $group,
        int &$count,
        int &$size
    ): void {
        if ($child instanceof DirectoryInterface) {
            $summary = $child->getSummary($user, $group);
            $count += $summary->getFileCount();
            $size += $summary->getSize();
        }

        if ($user !== null && $user !== $child->getUser()) {
            // File doesn't belong to this user
            return;
        }

        if ($group !== null && $group !== $child->getGroup()) {
            // File doesn't belong to this group
            return;
        }

        $size += $child->getSize();
        $count++;
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
        if ($this->getConfig()->getIgnoreCase()) {
            $name = mb_strtoupper($name);
        }

        return $name;
    }

    /**
     * Gets the file system.
     *
     * @return FileSystemInterface|null
     */
    private function getRoot(): ?FileSystemInterface
    {
        $root = $this->getParent();

        while ($root) {
            $parent = $root->getParent();
            if ($parent instanceof FileSystemInterface) {
                return $parent;
            }
            $root = $parent;
        }

        return null;
    }
}
