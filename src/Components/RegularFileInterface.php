<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Content\ContentInterface;

/**
 * Represents a regular file.
 */
interface RegularFileInterface extends FileInterface
{
    /**
     * Opens the file.
     *
     * Should update the last accessed time.
     *
     * @return bool
     */
    public function open(): bool;

    /**
     * Closes the file.
     *
     * @return bool
     */
    public function close(): bool;

    /**
     * Reads data from the file.
     *
     * Should update the last accessed time.
     *
     * @param int $count
     *
     * @return string
     */
    public function read(int $count): string;

    /**
     * Writes data to the file.
     *
     * Should update the last modified time.
     *
     * @param string $data
     *
     * @return int Number of bytes written.
     */
    public function write(string $data): int;

    /**
     * Truncates the file.
     *
     * Should update the last modified time.
     *
     * @param int $size
     *
     * @return bool
     */
    public function truncate(int $size): bool;

    /**
     * Sets the file position.
     *
     * @param int $offset
     * @param int $whence
     *
     * @return bool
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool;

    /**
     * Gets the current file position.
     *
     * @return int
     */
    public function tell(): int;

    /**
     * Checks if end-of-file has been reached.
     *
     * @return bool
     */
    public function isEof(): bool;

    /**
     * Flushes the file contents.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Deletes the file.
     *
     * @return bool
     */
    public function unlink(): bool;

    /**
     * Sets the file content.
     *
     * Does not update the last modified time.
     *
     * @param ContentInterface $content
     *
     * @return RegularFileInterface
     */
    public function setContent(ContentInterface $content): RegularFileInterface;

    /**
     * Gets the file content.
     *
     * Does not update the last accessed time.
     *
     * @return ContentInterface
     */
    public function getContent(): ContentInterface;
}
