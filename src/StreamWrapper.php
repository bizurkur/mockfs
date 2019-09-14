<?php declare(strict_types = 1);

namespace MockFileSystem;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\RegularFile\MultiInstanceDecorator;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Exception\BaseException;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded
class StreamWrapper
{
    /**
     * Stream wrapper protocol.
     *
     * @var string
     */
    public const PROTOCOL = 'mfs';

    public const MODE_READ = 'r';
    public const MODE_WRITE = 'w';
    public const MODE_APPEND = 'a';
    public const MODE_CREATE = 'c';
    public const MODE_CREATE_NEW = 'x';

    /**
     * @var RegularFileInterface|null
     */
    private $file = null;

    /**
     * @var string
     */
    private $mode = null;

    /**
     * @var bool
     */
    private $canRead = false;

    /**
     * @var bool
     */
    private $canWrite = false;

    // public function dir_closedir(): bool
    // {
    //     return true;
    // }
    //
    // public function dir_opendir(string $path, int $options): bool
    // {
    //     return true;
    // }
    //
    // public function dir_readdir(): string
    // {
    //     return '';
    // }
    //
    // public function dir_rewinddir(): bool
    // {
    //     return true;
    // }
    //
    // public function mkdir(string $path, int $mode, int $options): bool
    // {
    //     return true;
    // }
    //
    // public function rmdir(string $path, int $options): bool
    // {
    //     return true;
    // }

    /**
     * Opens a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-open.php
     *
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string|null $openedPath
     *
     * @return bool
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        // TODO: Clean this mess up

        if (!$this->parseMode($mode)) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('Illegal mode "%s"', $mode),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        /** @var RegularFileInterface|null $file */
        $file = MockFileSystem::findByType($path, FileInterface::TYPE_FILE);
        if ($file === null) {
            if ($this->mode === self::MODE_READ) {
                if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                    trigger_error(
                        sprintf('Cannot open non-existent file "%s" for reading.', $path),
                        \E_USER_WARNING
                    );
                }

                return false;
            }

            $remaining = $this->getRemainingFileCount();
            if ($remaining === 0) {
                if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                    trigger_error('No more free disk space', \E_USER_WARNING);
                }

                return false;
            }

            try {
                $file = MockFileSystem::createFile($path);
            } catch (BaseException $exception) {
                if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                    trigger_error($exception->getMessage(), \E_USER_WARNING);
                }

                return false;
            }

            /** @var FileInterface $parent */
            $parent = $file->getParent();
            if (!$this->isWritable($parent)) {
                if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                    trigger_error(
                        sprintf('Directory "%s" is not writable.', $parent->getPath()),
                        \E_USER_WARNING
                    );
                }

                return false;
            }
        } elseif ($this->mode === self::MODE_CREATE_NEW) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('File "%s" already exists; cannot open in mode %s', $path, $mode),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        if ($this->canRead && !$this->isReadable($file)) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('File "%s" is not readable.', $file->getPath()),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        if ($this->canWrite && !$this->isWritable($file)) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('File "%s" is not writeable.', $file->getPath()),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        $this->file = new MultiInstanceDecorator($file);
        if ($this->mode === self::MODE_APPEND) {
            $this->file->open();
            $this->file->seek(0, \SEEK_END);
        } else {
            $this->file->open();
            $this->file->seek(0);
            if ($this->mode === self::MODE_WRITE) {
                $this->file->truncate(0);
            }
        }

        if (($options & \STREAM_USE_PATH) === \STREAM_USE_PATH) {
            $openedPath = $this->file->getPath();
        }

        return true;
    }

    /**
     * Closes a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-close.php
     */
    public function stream_close(): void
    {
        if ($this->file === null) {
            return;
        }

        $this->file->close();
        $this->file = null;
    }

    /**
     * Reads from a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-read.php
     *
     * @param int $count
     *
     * @return string
     */
    public function stream_read(int $count): string
    {
        if ($this->file === null) {
            return '';
        }

        if (!$this->canRead) {
            return '';
        }

        if (!$this->isReadable($this->file)) {
            return '';
        }

        return $this->file->read($count);
    }

    /**
     * Writes to a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-write.php
     *
     * @param string $data
     *
     * @return int
     */
    public function stream_write(string $data): int
    {
        if ($this->file === null) {
            return 0;
        }

        if (!$this->canWrite) {
            return 0;
        }

        if (!$this->isWritable($this->file)) {
            return 0;
        }

        $remaining = $this->getRemainingSize();
        if ($remaining >= 0) {
            $data = mb_substr($data, 0, $remaining);
        }

        if ($this->mode === self::MODE_APPEND) {
            // Append mode always writes to the end of the file
            // Remember and restore the current position so read can use it
            $position = $this->file->tell();
            $this->file->seek(0, \SEEK_END);
            $bytes = $this->file->write($data);
            $this->file->seek($position);
        } else {
            $bytes = $this->file->write($data);
        }

        return $bytes;
    }

    /**
     * Seeks to a specific position in a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-seek.php
     *
     * @param int $offset
     * @param int $whence
     *
     * @return bool
     */
    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        if ($this->file === null) {
            return false;
        }

        return $this->file->seek($offset, $whence);
    }

    /**
     * Retrieves the current position in a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-tell.php
     *
     * @return int
     */
    public function stream_tell(): int
    {
        if ($this->file === null) {
            return 0;
        }

        return $this->file->tell();
    }

    /**
     * Checks if end-of-file is reached on a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-eof.php
     *
     * @return bool
     */
    public function stream_eof(): bool
    {
        if ($this->file === null) {
            return true;
        }

        return $this->file->isEof();
    }

    /**
     * Flushes the output.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-flush.php
     *
     * @return bool
     */
    public function stream_flush(): bool
    {
        return true;
    }

    // public function stream_lock(int $operation): bool
    // {
    //     // Default to not supporting locking
    //     // @see stream_supports_lock()
    //     // @see flock()
    //     return false;
    // }
    //
    // public function stream_metadata(string $path, int $option, $value): bool
    // {
    //     return true;
    // }
    //
    // public function stream_set_option(int $option, int $arg1, int $arg2): bool
    // {
    //     return true;
    // }

    /**
     * Retrieves information about a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-stat.php
     * @see https://www.php.net/manual/en/function.stat.php
     *
     * @return int[]
     */
    public function stream_stat(): array
    {
        if ($this->file === null) {
            return [];
        }

        return $this->file->stat();
    }

    /**
     * Truncates a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-truncate.php
     * @see https://www.php.net/manual/en/function.ftruncate.php
     *
     * @param int $newSize
     *
     * @return bool
     */
    public function stream_truncate(int $newSize): bool
    {
        if ($this->file === null) {
            return false;
        }

        if (!$this->canWrite) {
            return false;
        }

        if (!$this->isWritable($this->file)) {
            return false;
        }

        if ($newSize > $this->file->getSize()) {
            $remaining = $this->getRemainingSize();
            if ($newSize > $remaining) {
                return false;
            }
        }

        return $this->file->truncate($newSize);
    }

    /**
     * Retrieves the underlaying resource.
     *
     * This is not supported and always returns false.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-cast.php
     *
     * @param int $castAs
     *
     * @return resource|false
     */
    public function stream_cast(int $castAs)
    {
        // TODO: Look more into if this can be done
        return false;
    }

    /**
     * Renames a file or directory.
     *
     * @see https://www.php.net/manual/en/streamwrapper.rename.php
     * @see https://www.php.net/manual/en/function.rename.php
     *
     * @param string $pathFrom
     * @param string $pathTo
     *
     * @return bool
     */
    public function rename(string $pathFrom, string $pathTo): bool
    {
        $src = MockFileSystem::find($pathFrom);
        $parts = MockFileSystem::getFileParts($pathTo);
        $dest = MockFileSystem::find($parts['dirname']);

        if ($src === null) {
            trigger_error(
                sprintf('Path "%s" does not exist.', $pathFrom),
                \E_USER_WARNING
            );

            return false;
        }

        if ($dest === null) {
            trigger_error(
                sprintf('Destination "%s" does not exist.', $parts['dirname']),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$dest instanceof DirectoryInterface) {
            trigger_error(
                sprintf('Destination "%s" is not a directory.', $parts['dirname']),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$this->isWritable($dest)) {
            trigger_error(
                sprintf('Destination "%s" is not writable.', $parts['dirname']),
                \E_USER_WARNING
            );

            return false;
        }

        if ($src->getType() === FileInterface::TYPE_DIR
            && $dest->hasChild($src->getName())
        ) {
            // If renaming a directory and destination exists, emit a warning.
            trigger_error(
                sprintf('Destination "%s" already exists.', $pathTo),
                \E_USER_WARNING
            );

            return false;
        }

        // Remove from source
        /** @var ContainerInterface $srcParent */
        $srcParent = $src->getParent();
        $srcParent->removeChild($src->getName());

        // Rename
        $src->setName($parts['basename']);

        // Add to destination
        $dest->addChild($src);

        return true;
    }

    /**
     * Deletes a file.
     *
     * @see https://www.php.net/manual/en/streamwrapper.unlink.php
     *
     * @param string $path
     *
     * @return bool
     */
    public function unlink(string $path): bool
    {
        $file = MockFileSystem::find($path);

        if ($file === null) {
            trigger_error(
                sprintf('unlink(%s): No such file or directory', $path),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$file instanceof RegularFileInterface) {
            trigger_error(
                sprintf('unlink(%s): Operation not permitted', $path),
                \E_USER_WARNING
            );

            return false;
        }

        return $file->unlink();
    }

    /**
     * Retrieves information about a file.
     *
     * @see https://www.php.net/manual/en/streamwrapper.url-stat.php
     * @see https://www.php.net/manual/en/function.stat.php
     *
     * @param string $path
     * @param int $flags
     *
     * @return int[]|false
     */
    public function url_stat(string $path, int $flags)
    {
        $file = MockFileSystem::find($path);
        if ($file === null) {
            if (($flags & \STREAM_URL_STAT_QUIET) !== \STREAM_URL_STAT_QUIET) {
                trigger_error(
                    sprintf('No such file or directory: %s', $path),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        // if (($flags & \STREAM_URL_STAT_LINK) === \STREAM_URL_STAT_LINK) {
        //     // TODO: add support for symlinks
        // }

        return $file->stat();
    }

    private function parseMode(string $mode): bool
    {
        $extended = mb_substr($mode, -1) === '+';
        if ($extended) {
            $mode = mb_substr($mode, 0, -1);
        }

        $realMode = mb_substr($mode, 0, 1);
        $mode = mb_substr($mode, 1);
        if (!in_array($realMode, ['r', 'w', 'a', 'x', 'c'], true)) {
            return false;
        }

        $flag = mb_substr($mode, 0, 1);
        $mode = mb_substr($mode, 1);
        if (!in_array($flag, ['', 'b', 't'], true)) {
            return false;
        }

        if (!empty($mode)) {
            return false;
        }

        if ($extended) {
            $this->canRead = true;
            $this->canWrite = true;
        } elseif ($realMode === 'r') {
            $this->canRead = true;
        } else {
            $this->canWrite = true;
        }

        $this->mode = $realMode;

        return true;
    }

    private function getFileSystem(): FileSystemInterface
    {
        return MockFileSystem::getFileSystem();
    }

    private function isReadable(FileInterface $file): bool
    {
        $config = $file->getConfig();

        return $file->isReadable($config->getUser(), $config->getGroup());
    }

    private function isWritable(FileInterface $file): bool
    {
        $config = $file->getConfig();

        return $file->isWritable($config->getUser(), $config->getGroup());
    }

    private function getRemainingSize(): int
    {
        // TODO: Decide if quota stuff should be here or not
        $fileSystem = $this->getFileSystem();
        $config = $fileSystem->getConfig();
        $user = $config->getUser();
        $group = $config->getGroup();

        $used = $fileSystem->getSummary($user, $group)->getSize();

        return $config->getQuota()->getRemainingSize($used, $user, $group);
    }

    private function getRemainingFileCount(): int
    {
        // TODO: Decide if quota stuff should be here or not
        $fileSystem = $this->getFileSystem();
        $config = $fileSystem->getConfig();
        $user = $config->getUser();
        $group = $config->getGroup();

        $used = $fileSystem->getSummary($user, $group)->getFileCount();

        return $config->getQuota()->getRemainingFileCount($used, $user, $group);
    }
}
