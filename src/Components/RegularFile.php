<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\AbstractFile;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\Content\StreamContent;

/**
 * Class to represent a regular file.
 */
class RegularFile extends AbstractFile implements RegularFileInterface
{
    /**
     * @var ContentInterface
     */
    private $content = null;

    /**
     * @param string $name
     * @param int|null $permissions
     * @param ContentInterface|null $content
     */
    public function __construct(
        string $name,
        ?int $permissions = null,
        ?ContentInterface $content = null
    ) {
        parent::__construct($name, $permissions);
        $this->type = self::TYPE_FILE;
        if ($content === null) {
            $content = new StreamContent('');
        }
        $this->content = $content;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultPermissions(): int
    {
        return 0666;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return $this->content->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function open(): void
    {
        $this->setLastAccessTime();
        $this->content->open();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->content->close();
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $count): string
    {
        $this->setLastAccessTime();

        return $this->content->read($count);
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function truncate(int $size): bool
    {
        $remaining = $this->getFreeDiskSpace();
        if ($remaining >= 0 && $size > $remaining) {
            return false;
        }

        $this->setLastModifyTime();

        return $this->content->truncate($size);
    }

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        return $this->content->seek($offset, $whence);
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        return $this->content->tell();
    }

    /**
     * {@inheritDoc}
     */
    public function isEof(): bool
    {
        return $this->content->isEof();
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        return $this->content->flush();
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function setContent(ContentInterface $content): void
    {
        $this->content = $content;
    }

    /**
     * {@inheritDoc}
     */
    public function getContent(): ContentInterface
    {
        return $this->content;
    }
}
