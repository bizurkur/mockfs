<?php

declare(strict_types=1);

namespace MockFileSystem\Components\RegularFile;

use MockFileSystem\Components\ChildInterface;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
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
    protected RegularFileInterface $base;

    public function __construct(RegularFileInterface $base)
    {
        $this->base = $base;
    }

    public function setConfig(ConfigInterface $config): FileInterface
    {
        return $this->base->setConfig($config);
    }

    public function getConfig(): ConfigInterface
    {
        return $this->base->getConfig();
    }

    public function getDefaultPermissions(): int
    {
        return $this->base->getDefaultPermissions();
    }

    public function setPermissions(int $permissions): FileInterface
    {
        return $this->base->setPermissions($permissions);
    }

    public function getPermissions(): int
    {
        return $this->base->getPermissions();
    }

    public function setUser(int $user): FileInterface
    {
        return $this->base->setUser($user);
    }

    public function getUser(): int
    {
        return $this->base->getUser();
    }

    public function setGroup(int $group): FileInterface
    {
        return $this->base->setGroup($group);
    }

    public function getGroup(): int
    {
        return $this->base->getGroup();
    }

    public function isReadable(int $user, int $group): bool
    {
        return $this->base->isReadable($user, $group);
    }

    public function isWritable(int $user, int $group): bool
    {
        return $this->base->isWritable($user, $group);
    }

    public function isExecutable(int $user, int $group): bool
    {
        return $this->base->isExecutable($user, $group);
    }

    public function getType(): int
    {
        return $this->base->getType();
    }

    public function getName(): string
    {
        return $this->base->getName();
    }

    public function setName(string $name): FileInterface
    {
        return $this->base->setName($name);
    }

    public function getParent(): ?ContainerInterface
    {
        return $this->base->getParent();
    }

    public function setParent(?ContainerInterface $parent): ChildInterface
    {
        return $this->base->setParent($parent);
    }

    public function addTo(ContainerInterface $container): FileInterface
    {
        return $this->base->addTo($container);
    }

    public function getPath(): string
    {
        return $this->base->getPath();
    }

    public function getUrl(): string
    {
        return $this->base->getUrl();
    }

    public function getSize(): int
    {
        return $this->base->getSize();
    }

    public function stat(): array
    {
        return $this->base->stat();
    }

    public function getLastAccessTime(): int
    {
        return $this->base->getLastAccessTime();
    }

    public function getLastModifyTime(): int
    {
        return $this->base->getLastModifyTime();
    }

    public function getLastChangeTime(): int
    {
        return $this->base->getLastChangeTime();
    }

    public function setLastAccessTime(?int $time = null): FileInterface
    {
        return $this->base->setLastAccessTime($time);
    }

    public function setLastModifyTime(?int $time = null): FileInterface
    {
        return $this->base->setLastModifyTime($time);
    }

    public function setLastChangeTime(?int $time = null): FileInterface
    {
        return $this->base->setLastChangeTime($time);
    }

    public function open(): bool
    {
        return $this->base->open();
    }

    public function close(): bool
    {
        return $this->base->close();
    }

    public function read(int $count): string
    {
        return $this->base->read($count);
    }

    public function write(string $data): int
    {
        return $this->base->write($data);
    }

    public function truncate(int $size): bool
    {
        return $this->base->truncate($size);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        return $this->base->seek($offset, $whence);
    }

    public function tell(): int
    {
        return $this->base->tell();
    }

    public function isEof(): bool
    {
        return $this->base->isEof();
    }

    public function flush(): bool
    {
        return $this->base->flush();
    }

    public function unlink(): bool
    {
        return $this->base->unlink();
    }

    public function setContent(ContentInterface $content): RegularFileInterface
    {
        return $this->base->setContent($content);
    }

    public function setContentFromString(string $content): RegularFileInterface
    {
        return $this->base->setContentFromString($content);
    }

    public function getContent(): ContentInterface
    {
        return $this->base->getContent();
    }
}
