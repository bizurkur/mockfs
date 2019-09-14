<?php declare(strict_types = 1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;

/**
 * Content stored in memory.
 */
class InMemoryContent extends AbstractContent
{
    /**
     * @var string
     */
    private $content = null;

    /**
     * @param string $content
     */
    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $count): string
    {
        $data = mb_substr($this->content, $this->position, $count);
        $this->position += mb_strlen($data);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $data): int
    {
        $bytes = mb_strlen($data);

        $this->content = mb_substr($this->content, 0, $this->position)
            .$data
            .mb_substr($this->content, $this->position + $bytes);

        $this->position += $bytes;

        return $bytes;
    }

    /**
     * {@inheritDoc}
     */
    public function truncate(int $size): bool
    {
        if ($size > $this->getSize()) {
            // If size is larger than the file then the file is extended with null bytes.
            $this->content .= str_repeat("\0", $size - $this->getSize());
        } else {
            // If size is smaller than the file then the file is truncated to that size.
            $this->content = mb_substr($this->content, 0, $size);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return mb_strlen($this->content);
    }
}
