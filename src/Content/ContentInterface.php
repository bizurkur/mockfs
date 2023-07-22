<?php

declare(strict_types=1);

namespace MockFileSystem\Content;

interface ContentInterface
{
    /**
     * Opens the file.
     */
    public function open(): bool;

    /**
     * Closes the file.
     */
    public function close(): bool;

    /**
     * Deletes the file.
     */
    public function unlink(): bool;

    /**
     * Reads data from the file.
     */
    public function read(int $count): string;

    /**
     * Writes data to the file.
     *
     * @return int Number of bytes written.
     */
    public function write(string $data): int;

    /**
     * Truncates the file.
     *
     * If the truncate size is greater than the file size, it should pad the
     * file with null bytes.
     */
    public function truncate(int $size): bool;

    /**
     * Sets the file position.
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool;

    /**
     * Gets the file position.
     */
    public function tell(): int;

    /**
     * Checks if end-of-file has been reached.
     */
    public function isEof(): bool;

    /**
     * Flushes the content.
     */
    public function flush(): bool;

    /**
     * Gets the size of the file.
     */
    public function getSize(): int;
}
