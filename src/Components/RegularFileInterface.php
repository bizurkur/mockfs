<?php

declare(strict_types=1);

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
     */
    public function open(): bool;

    /**
     * Closes the file.
     */
    public function close(): bool;

    /**
     * Reads data from the file.
     *
     * Should update the last accessed time.
     */
    public function read(int $count): string;

    /**
     * Writes data to the file.
     *
     * Should update the last modified time.
     *
     * @return int Number of bytes written.
     */
    public function write(string $data): int;

    /**
     * Truncates the file.
     *
     * Should update the last modified time.
     */
    public function truncate(int $size): bool;

    /**
     * Sets the file position.
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool;

    /**
     * Gets the current file position.
     */
    public function tell(): int;

    /**
     * Checks if end-of-file has been reached.
     */
    public function isEof(): bool;

    /**
     * Flushes the file contents.
     */
    public function flush(): bool;

    /**
     * Deletes the file.
     */
    public function unlink(): bool;

    /**
     * Sets the file content.
     *
     * Does not update the last modified time.
     */
    public function setContent(ContentInterface $content): RegularFileInterface;

    /**
     * Sets the file content from a string.
     *
     * Does not update the last modified time.
     */
    public function setContentFromString(string $content): RegularFileInterface;

    /**
     * Gets the file content.
     *
     * Does not update the last accessed time.
     */
    public function getContent(): ContentInterface;
}
