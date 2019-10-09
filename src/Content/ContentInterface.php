<?php

declare(strict_types=1);

namespace MockFileSystem\Content;

interface ContentInterface
{
    /**
     * Opens the file.
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
     * Deletes the file.
     *
     * @return bool
     */
    public function unlink(): bool;

    /**
     * Reads data from the file.
     *
     * @param int $count
     *
     * @return string
     */
    public function read(int $count): string;

    /**
     * Writes data to the file.
     *
     * @param string $data
     *
     * @return int Number of bytes written.
     */
    public function write(string $data): int;

    /**
     * Truncates the file.
     *
     * If the truncate size is greater than the file size, it should pad the
     * file with null bytes.
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
     * Gets the file position.
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
     * Flushes the content.
     *
     * @return bool
     */
    public function flush(): bool;

    /**
     * Gets the size of the file.
     *
     * @return int
     */
    public function getSize(): int;
}
