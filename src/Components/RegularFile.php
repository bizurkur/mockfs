<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\Content\StreamContent;
use MockFileSystem\Exception\InvalidArgumentException;

/**
 * Class to represent a regular file.
 */
class RegularFile extends AbstractFile implements RegularFileInterface
{
    private ContentInterface $content;

    /**
     * @param ContentInterface|string|null $content
     */
    public function __construct(
        string $name,
        ?int $permissions = null,
        $content = null
    ) {
        parent::__construct($name, $permissions);

        if ($content === null) {
            $content = new StreamContent('');
        } elseif (is_string($content)) {
            $content = new StreamContent($content);
        } elseif (!$content instanceof ContentInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Content must be an instance of %s, a string, or null; %s given',
                    ContentInterface::class,
                    gettype($content)
                )
            );
        }

        $this->content = $content;
    }

    public function getDefaultPermissions(): int
    {
        return 0666;
    }

    public function getSize(): int
    {
        return $this->content->getSize();
    }

    public function getType(): int
    {
        return self::TYPE_FILE;
    }

    public function open(): bool
    {
        $this->setLastAccessTime();

        return $this->content->open();
    }

    public function close(): bool
    {
        return $this->content->close();
    }

    public function read(int $count): string
    {
        $this->setLastAccessTime();

        return $this->content->read($count);
    }

    public function write(string $data): int
    {
        $remaining = $this->getFreeDiskSpace();
        if ($remaining >= 0) {
            $remaining += $this->content->tell() - $this->content->getSize();
            $data = mb_substr($data, 0, $remaining);
        }

        $this->setLastModifyTime();

        return $this->content->write($data);
    }

    public function truncate(int $size): bool
    {
        $remaining = $this->getFreeDiskSpace();
        if (
            $remaining >= 0
            && ($size - $this->content->getSize()) > $remaining
        ) {
            return false;
        }

        $this->setLastModifyTime();

        return $this->content->truncate($size);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        return $this->content->seek($offset, $whence);
    }

    public function tell(): int
    {
        return $this->content->tell();
    }

    public function isEof(): bool
    {
        return $this->content->isEof();
    }

    public function flush(): bool
    {
        return $this->content->flush();
    }

    public function unlink(): bool
    {
        if (!$this->content->unlink()) {
            return false;
        }

        $parent = $this->getParent();
        if ($parent === null) {
            return true;
        }

        return $parent->removeChild($this->getName());
    }

    public function setContent(ContentInterface $content): RegularFileInterface
    {
        $this->content = $content;

        return $this;
    }

    public function setContentFromString(string $content): RegularFileInterface
    {
        $this->content = new StreamContent($content);

        return $this;
    }

    public function getContent(): ContentInterface
    {
        return $this->content;
    }
}
