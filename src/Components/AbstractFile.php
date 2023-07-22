<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ChildInterface;
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
     * Last access time.
     */
    private int $lastAccessTime = 0;

    /**
     * Last modify time.
     */
    private int $lastModifyTime = 0;

    /**
     * Last change time.
     */
    private int $lastChangeTime = 0;

    private ?ConfigInterface $config = null;

    private ?ContainerInterface $parent = null;

    private string $name;

    private int $permissions = -1;

    private int $user = -1;

    private int $group = -1;

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

    public function setConfig(ConfigInterface $config): FileInterface
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

        return $this;
    }

    public function getConfig(): ConfigInterface
    {
        if ($this->config === null) {
            throw new RuntimeException('Config not set.');
        }

        return $this->config;
    }

    abstract public function getDefaultPermissions(): int;

    abstract public function getSize(): int;

    abstract public function getType(): int;

    public function setPermissions(int $permissions): FileInterface
    {
        $this->setLastChangeTime();
        $this->permissions = $permissions;

        return $this;
    }

    public function getPermissions(): int
    {
        return $this->permissions;
    }

    public function setUser(int $user): FileInterface
    {
        $this->setLastChangeTime();
        $this->user = $user;

        return $this;
    }

    public function getUser(): int
    {
        return $this->user;
    }

    public function setGroup(int $group): FileInterface
    {
        $this->setLastChangeTime();
        $this->group = $group;

        return $this;
    }

    public function getGroup(): int
    {
        return $this->group;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): FileInterface
    {
        if ($this->config !== null) {
            $this->validateName($name);
        }

        $this->name = $name;

        return $this;
    }

    public function getPath(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        $sep = $this->getConfig()->getFileSeparator();

        return rtrim($this->parent->getPath(), $sep) . $sep . $this->name;
    }

    public function getUrl(): string
    {
        $prefix = StreamWrapper::PROTOCOL . '://';

        return $prefix . $this->getPath();
    }

    public function getParent(): ?ContainerInterface
    {
        return $this->parent;
    }

    public function setParent(?ContainerInterface $parent): ChildInterface
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

        return $this;
    }

    public function addTo(ContainerInterface $container): FileInterface
    {
        $parent = $this->getParent();
        if ($parent !== null && !$parent instanceof FileSystemInterface) {
            $parent->removeChild($this->getName());
        }

        $container->addChild($this);

        return $this;
    }

    public function isReadable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0400;
        } elseif ($this->group === $group) {
            $check = 0040;
        } else {
            $check = 0004;
        }

        return ($this->permissions & $check) === $check;
    }

    public function isWritable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0200;
        } elseif ($this->group === $group) {
            $check = 0020;
        } else {
            $check = 0002;
        }

        return ($this->permissions & $check) === $check;
    }

    public function isExecutable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0100;
        } elseif ($this->group === $group) {
            $check = 0010;
        } else {
            $check = 0001;
        }

        return ($this->permissions & $check) === $check;
    }

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

    public function getLastAccessTime(): int
    {
        return $this->lastAccessTime;
    }

    public function getLastModifyTime(): int
    {
        return $this->lastModifyTime;
    }

    public function getLastChangeTime(): int
    {
        return $this->lastChangeTime;
    }

    public function setLastAccessTime(?int $time = null): FileInterface
    {
        if ($time === null) {
            $time = time();
        }

        $this->lastAccessTime = $time;

        return $this;
    }

    public function setLastModifyTime(?int $time = null): FileInterface
    {
        if ($time === null) {
            $time = time();
        }

        $this->lastModifyTime = $time;

        return $this;
    }

    public function setLastChangeTime(?int $time = null): FileInterface
    {
        if ($time === null) {
            $time = time();
        }

        $this->lastChangeTime = $time;

        return $this;
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
    protected function getFreeDiskSpace(?FileInterface $child = null): int
    {
        $partition = $this->getPartition();
        if ($partition === null) {
            return QuotaInterface::UNLIMITED;
        }

        return $partition->getQuotaManager()->getFreeDiskSpace($child);
    }

    /**
     * Whether or not to allow an empty file name.
     */
    protected function allowEmptyName(): bool
    {
        return false;
    }

    /**
     * Gets the partition this file is in.
     */
    private function getPartition(): ?PartitionInterface
    {
        $root = $this;

        do {
            if ($root instanceof PartitionInterface) {
                return $root;
            }

            $root = $root->getParent();
        } while ($root);

        return null;
    }

    /**
     * Validates the file name meets the config rules.
     *
     * @throws InvalidArgumentException When not valid.
     */
    private function validateName(string $name): void
    {
        if ($name === '.' || $name === '..') {
            throw new InvalidArgumentException('Name cannot be "." or ".."');
        }

        if (empty($name) && !$this->allowEmptyName()) {
            throw new InvalidArgumentException('Name cannot be empty.');
        }

        $this->validateBlacklist($name);
    }

    /**
     * Validates the file name doesn't contain any blacklisted characters.
     *
     * @throws InvalidArgumentException When not valid.
     */
    private function validateBlacklist(string $name): void
    {
        $config = $this->getConfig();

        $blacklist = array_merge(
            $config->getBlacklist(),
            [
                $config->getFileSeparator(),
                $config->getPartitionSeparator(),
                'null' => "\0",
            ]
        );

        foreach ($blacklist as $key => $character) {
            if (mb_strlen($character) === 0) {
                continue;
            }

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
