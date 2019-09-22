<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\RecursionException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\StreamWrapper;

/**
 * Class to represent any file.
 *
 * This could be a regular file, directory, block, link, etc.
 */
abstract class AbstractFile implements FileInterface
{
    /**
     * @var int
     */
    protected $type = null;

    /**
     * Last access time.
     *
     * @var int
     */
    protected $lastAccessTime = 0;

    /**
     * Last modify time.
     *
     * @var int
     */
    protected $lastModifyTime = 0;

    /**
     * Last change time.
     *
     * @var int
     */
    protected $lastChangeTime = 0;

    /**
     * @var ConfigInterface
     */
    private $config = null;

    /**
     * @var ContainerInterface|null
     */
    private $parent = null;

    /**
     * @var string
     */
    private $name = null;

    /**
     * @var int
     */
    private $permissions = -1;

    /**
     * @var int
     */
    private $user = -1;

    /**
     * @var int
     */
    private $group = -1;

    /**
     * @param string $name
     * @param int|null $permissions
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        $this->setName($name);

        if ($permissions !== null) {
            $this->permissions = $permissions;
        }

        $now = time();
        $this->setLastAccessTime($now);
        $this->setLastModifyTime($now);
        $this->setLastChangeTime($now);
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(ConfigInterface $config): void
    {
        $this->config = $config;

        if ($this->user === -1) {
            $this->user = $config->getUser();
        }

        if ($this->group === -1) {
            $this->group = $config->getGroup();
        }

        if ($this->permissions === -1) {
            $this->permissions = $this->getDefaultPermissions() & ~$config->getUmask();
        }

        $this->validateName($this->name);
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): ConfigInterface
    {
        if ($this->config === null) {
            throw new RuntimeException('Config not set.');
        }

        return $this->config;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getDefaultPermissions(): int;

    /**
     * {@inheritDoc}
     */
    abstract public function getSize(): int;

    /**
     * {@inheritDoc}
     */
    public function setPermissions(int $permissions): void
    {
        $this->setLastChangeTime();
        $this->permissions = $permissions;
    }

    /**
     * {@inheritDoc}
     */
    public function getPermissions(): int
    {
        return $this->permissions;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(int $user): void
    {
        $this->setLastChangeTime();
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function getUser(): int
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function setGroup(int $group): void
    {
        $this->setLastChangeTime();
        $this->group = $group;
    }

    /**
     * {@inheritDoc}
     */
    public function getGroup(): int
    {
        return $this->group;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): void
    {
        if ($this->config !== null) {
            $this->validateName($name);
        }

        $this->name = $name;
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        $sep = $this->getConfig()->getSeparator();

        return rtrim($this->parent->getPath(), $sep).$sep.$this->name;
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
    public function getParent(): ?ContainerInterface
    {
        return $this->parent;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(?ContainerInterface $parent): void
    {
        $node = $parent;
        while ($node) {
            if ($node === $this) {
                throw new RecursionException(
                    'A parent cannot contain a child reference to itself.'
                );
            }
            $node = $node->getParent();
        }

        $this->parent = $parent;
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0400;
        } elseif ($this->group === $group) {
            $check = 0040;
        } else {
            $check = 0004;
        }

        return (bool) ($this->permissions & $check);
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0200;
        } elseif ($this->group === $group) {
            $check = 0020;
        } else {
            $check = 0002;
        }

        return (bool) ($this->permissions & $check);
    }

    /**
     * {@inheritDoc}
     */
    public function isExecutable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0100;
        } elseif ($this->group === $group) {
            $check = 0010;
        } else {
            $check = 0001;
        }

        return (bool) ($this->permissions & $check);
    }

    /**
     * {@inheritDoc}
     */
    public function stat(): array
    {
        $stat = [
            'dev' => 0,
            'ino' => spl_object_id($this),
            'mode' => $this->getType() | $this->getPermissions(),
            'nlink' => 1,
            'uid' => $this->getUser(),
            'gid' => $this->getGroup(),
            'rdev' => 0,
            'size' => $this->getSize(),
            'atime' => $this->getLastAccessTime(),
            'mtime' => $this->getLastModifyTime(),
            'ctime' => $this->getLastChangeTime(),
            'blksize' => -1,
            'blocks' => -1,
        ];

        return array_merge(array_values($stat), $stat);
    }

    /**
     * {@inheritDoc}
     */
    public function getLastAccessTime(): int
    {
        return $this->lastAccessTime;
    }

    /**
     * {@inheritDoc}
     */
    public function getLastModifyTime(): int
    {
        return $this->lastModifyTime;
    }

    /**
     * {@inheritDoc}
     */
    public function getLastChangeTime(): int
    {
        return $this->lastChangeTime;
    }

    /**
     * {@inheritDoc}
     */
    public function setLastAccessTime(?int $time = null): void
    {
        if ($time === null) {
            $time = time();
        }

        $this->lastAccessTime = $time;
    }

    /**
     * {@inheritDoc}
     */
    public function setLastModifyTime(?int $time = null): void
    {
        if ($time === null) {
            $time = time();
        }

        $this->lastModifyTime = $time;
    }

    /**
     * {@inheritDoc}
     */
    public function setLastChangeTime(?int $time = null): void
    {
        if ($time === null) {
            $time = time();
        }

        $this->lastChangeTime = $time;
    }

    /**
     * Checks if there's enough free disk space for the child file.
     *
     * If no child is given, returns the remaining disk space.
     *
     * This uses the current user to determine if there are any quotas that apply.
     *
     * @param FileInterface|null $child
     *
     * @return int Returns -1 for unlimited space.
     */
    protected function getFreeDiskSpace(?FileInterface $child = null): int
    {
        $config = $this->getConfig();
        $quota = $config->getQuota();
        $user = $config->getUser();
        $group = $config->getGroup();

        if (!$quota->appliesTo($user, $group)) {
            return QuotaInterface::UNLIMITED;
        }

        $summary = $this->getRoot()->getSummary($user, $group);
        $usedCount = $summary->getFileCount();
        $usedSize = $summary->getSize();
        $remainingCount = $quota->getRemainingFileCount($usedCount, $user, $group);
        $remainingSize = $quota->getRemainingSize($usedSize, $user, $group);

        if ($remainingCount === 0 || $remainingSize === 0) {
            return 0;
        }

        if ($child === null) {
            return $remainingSize;
        }

        if (!$child instanceof ContainerInterface) {
            return max(0, $remainingSize - $child->getSize());
        }

        $childSummary = $child->getSummary();

        if ($remainingCount !== QuotaInterface::UNLIMITED
            && $childSummary->getFileCount() > $remainingCount
        ) {
            return 0;
        }

        if ($remainingSize !== QuotaInterface::UNLIMITED) {
            return max(0, $remainingSize - $childSummary->getSize());
        }

        return QuotaInterface::UNLIMITED;
    }

    /**
     * Gets the root container.
     *
     * @return ContainerInterface
     */
    private function getRoot(): ContainerInterface
    {
        $root = $this->getParent();
        if ($root === null) {
            throw new RuntimeException('File has not been attached to the filesystem');
        }

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
     * Validates the file name doesn't contain any blacklisted characters.
     *
     * @param string $name
     *
     * @throws InvalidArgumentException When not valid.
     */
    private function validateName(string $name): void
    {
        if ($name === '.' || $name === '..') {
            throw new InvalidArgumentException('Name cannot be "." or ".."');
        }

        $config = $this->getConfig();

        $blacklist = array_merge(
            $config->getBlacklist(),
            [
                $config->getSeparator(),
                'null' => "\0",
            ]
        );

        foreach ($blacklist as $key => $character) {
            if (is_int($key)) {
                $key = $character;
            }

            if (mb_strpos($name, $character) !== false) {
                throw new InvalidArgumentException(
                    sprintf('Name cannot contain a "%s" character.', $key)
                );
            }
        }
    }
}
