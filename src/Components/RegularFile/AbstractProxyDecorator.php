<?php declare(strict_types = 1);

namespace MockFileSystem\Components\RegularFile;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Content\ContentInterface;

/**
 * Decorator class to proxy all calls to a base file.
 *
 * This file does nothing by itself. It is meant to be extended and override
 * specific methods that need to be decorated.
 */
abstract class AbstractProxyDecorator implements RegularFileInterface
{
    /**
     * @var RegularFileInterface
     */
    protected $base = null;

    /**
     * @param RegularFileInterface $base
     */
    public function __construct(RegularFileInterface $base)
    {
        $this->base = $base;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig(ConfigInterface $config): void
    {
        $this->base->setConfig($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): ConfigInterface
    {
        return $this->base->getConfig();
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultPermissions(): int
    {
        return $this->base->getDefaultPermissions();
    }

    /**
     * {@inheritDoc}
     */
    public function setPermissions(int $permissions): void
    {
        $this->base->setPermissions($permissions);
    }

    /**
     * {@inheritDoc}
     */
    public function getPermissions(): int
    {
        return $this->base->getPermissions();
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(int $user): void
    {
        $this->base->setUser($user);
    }

    /**
     * {@inheritDoc}
     */
    public function getUser(): int
    {
        return $this->base->getUser();
    }

    /**
     * {@inheritDoc}
     */
    public function setGroup(int $group): void
    {
        $this->base->setGroup($group);
    }

    /**
     * {@inheritDoc}
     */
    public function getGroup(): int
    {
        return $this->base->getGroup();
    }

    /**
     * {@inheritDoc}
     */
    public function isReadable(int $user, int $group): bool
    {
        return $this->base->isReadable($user, $group);
    }

    /**
     * {@inheritDoc}
     */
    public function isWritable(int $user, int $group): bool
    {
        return $this->base->isWritable($user, $group);
    }

    /**
     * {@inheritDoc}
     */
    public function isExecutable(int $user, int $group): bool
    {
        return $this->base->isExecutable($user, $group);
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): int
    {
        return $this->base->getType();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->base->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name): void
    {
        $this->base->setName($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(): ?ContainerInterface
    {
        return $this->base->getParent();
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(?ContainerInterface $parent): void
    {
        $this->base->setParent($parent);
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        return $this->base->getPath();
    }

    /**
     * {@inheritDoc}
     */
    public function getUrl(): string
    {
        return $this->base->getUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return $this->base->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function stat(): array
    {
        return $this->base->stat();
    }

    /**
     * {@inheritDoc}
     */
    public function getLastAccessTime(): int
    {
        return $this->base->getLastAccessTime();
    }

    /**
     * {@inheritDoc}
     */
    public function getLastModifyTime(): int
    {
        return $this->base->getLastModifyTime();
    }

    /**
     * {@inheritDoc}
     */
    public function getLastChangeTime(): int
    {
        return $this->base->getLastChangeTime();
    }

    /**
     * {@inheritDoc}
     */
    public function setLastAccessTime(?int $time = null): void
    {
        $this->base->setLastAccessTime($time);
    }

    /**
     * {@inheritDoc}
     */
    public function setLastModifyTime(?int $time = null): void
    {
        $this->base->setLastModifyTime($time);
    }

    /**
     * {@inheritDoc}
     */
    public function setLastChangeTime(?int $time = null): void
    {
        $this->base->setLastChangeTime($time);
    }

    /**
     * {@inheritDoc}
     */
    public function open(): void
    {
        $this->base->open();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->base->close();
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $count): string
    {
        return $this->base->read($count);
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $data): int
    {
        return $this->base->write($data);
    }

    /**
     * {@inheritDoc}
     */
    public function truncate(int $size): bool
    {
        return $this->base->truncate($size);
    }

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        return $this->base->seek($offset, $whence);
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        return $this->base->tell();
    }

    /**
     * {@inheritDoc}
     */
    public function isEof(): bool
    {
        return $this->base->isEof();
    }

    /**
     * {@inheritDoc}
     */
    public function unlink(): bool
    {
        return $this->base->unlink();
    }

    /**
     * {@inheritDoc}
     */
    public function setContent(ContentInterface $content): void
    {
        $this->base->setContent($content);
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ContentInterface
    {
        return $this->base->getContent();
    }
}
