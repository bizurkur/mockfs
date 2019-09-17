<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\Quota\QuotaInterface;

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
    public function find(string $path): ?FileInterface
    {
        $clean = trim($path);
        if (empty($path)) {
            return $this;
        }

        // TODO: Clean up this entire method.
        $config = $this->getConfig();
        $sep = $config->getSeparator();

        if ($config->getNormalizeSlashes()) {
            $clean = str_replace(['\\', '/'], $sep, $clean);
        }

        $prefix = $this->getPath();
        if (strcmp(mb_substr($prefix, -mb_strlen($sep)), $sep) !== 0) {
            $prefix .= $sep;
        }

        $length = mb_strlen($prefix);
        $dirPath = mb_substr($clean.$sep, 0, $length);

        if (strcmp($prefix, $dirPath) === 0) {
            $clean = mb_substr($clean, $length);
        } elseif ($config->getIgnoreCase()
            && strcmp(mb_strtoupper($prefix), mb_strtoupper($dirPath)) === 0
        ) {
            $clean = mb_substr($clean, $length);
        }

        $file = $this;
        $clean = trim($clean, $sep);

        if (empty($clean)) {
            return $this;
        }

        $parts = explode($sep, $clean);
        if ($parts === false) {
            return $file;
        }

        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }

            if ($part === '..') {
                $parent = $file->getParent();
                if ($parent !== null) {
                    $file = $parent;
                }

                continue;
            }

            if (!$file->hasChild($part)) {
                return null;
            }

            $file = $file->getChild($part);
        }

        return $file;
    }

    /**
     * {@inheritDoc}
     */
    public function getChildren(): array
    {
        return array_values($this->children);
    }

    /**
     * {@inheritDoc}
     */
    public function addChild(FileInterface $child): void
    {
        $child->setConfig($this->getConfig());

        // TODO: Decide if quota check should be here
        if (!$this->hasEnoughDiskSpace($child)) {
            trigger_error('Not enough disk space', \E_USER_WARNING);

            return;
        }

        $child->setParent($this);

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
            if ($child instanceof self) {
                $summary = $child->getSummary($user, $group);
                $count += $summary->getFileCount();
                $size += $summary->getSize();
            } elseif ($user !== null && $user !== $child->getUser()) {
                continue;
            } elseif ($group !== null && $group !== $child->getGroup()) {
                continue;
            } else {
                $size += $child->getSize();
                $count++;
            }
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
        if ($this->getConfig()->getIgnoreCase()) {
            $name = mb_strtoupper($name);
        }

        return $name;
    }

    /**
     * Gets the root container.
     *
     * @return ContainerInterface
     */
    private function getRoot(): ContainerInterface
    {
        $root = $this;
        while ($root) {
            $parent = $root->getParent();
            if ($parent === null) {
                break;
            }
            $root = $parent;
        }

        return $root;
    }

    /**
     * Checks if there's enough free disk space for the child file.
     *
     * This uses the current user to determine if there are any quotas that apply.
     *
     * @param FileInterface $child
     *
     * @return bool
     */
    private function hasEnoughDiskSpace(FileInterface $child): bool
    {
        $config = $this->getConfig();
        $quota = $config->getQuota();
        $user = $config->getUser();
        $group = $config->getGroup();

        if (!$quota->appliesTo($user, $group)) {
            return true;
        }

        $summary = $this->getRoot()->getSummary($user, $group);
        $usedCount = $summary->getFileCount();
        $usedSize = $summary->getSize();
        $remainingCount = $quota->getRemainingFileCount($usedCount, $user, $group);
        $remainingSize = $quota->getRemainingSize($usedSize, $user, $group);

        if ($remainingCount === 0 || $remainingSize === 0) {
            return false;
        }

        if (!$child instanceof ContainerInterface) {
            return $remainingSize >= $child->getSize();
        }

        $childSummary = $child->getSummary();

        if ($remainingCount !== QuotaInterface::UNLIMITED
            && $childSummary->getFileCount() > $remainingCount
        ) {
            return false;
        }

        return $remainingSize !== QuotaInterface::UNLIMITED
            && $childSummary->getSize() > $remainingSize;
    }
}
