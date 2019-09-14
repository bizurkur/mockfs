<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Config;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\RecursionException;
use MockFileSystem\Exception\RuntimeException;
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
     * @var Config
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
        $this->lastAccessTime = $now;
        $this->lastModifyTime = $now;
        $this->lastChangeTime = $now;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(Config $config): void
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
    public function getConfig(): Config
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
        $this->lastChangeTime = time();
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
        $this->lastChangeTime = time();
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
        $this->lastChangeTime = time();
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
        $sep = $this->config->getSeparator();

        if ($this->parent === null
            || $this->parent instanceof FileSystemInterface
        ) {
            return $this->name.$sep;
        }

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
            'atime' => $this->lastAccessTime,
            'mtime' => $this->lastModifyTime,
            'ctime' => $this->lastChangeTime,
            'blksize' => -1,
            'blocks' => -1,
        ];

        return array_merge(array_values($stat), $stat);
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
