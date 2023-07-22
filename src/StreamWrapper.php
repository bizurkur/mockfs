<?php

declare(strict_types=1);

namespace MockFileSystem;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\RegularFile\MultiInstanceDecorator;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Exception\BaseException;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded
// phpcs:disable SlevomatCodingStandard.Classes.ClassLength.ClassTooLong
// phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
final class StreamWrapper
{
    /**
     * Stream wrapper protocol.
     */
    public const PROTOCOL = 'mfs';

    public const MODE_READ = 'r';
    public const MODE_WRITE = 'w';
    public const MODE_APPEND = 'a';
    public const MODE_CREATE = 'c';
    public const MODE_CREATE_NEW = 'x';

    /**
     * The stream's context.
     *
     * This is set by php and must be public.
     *
     * @see https://www.php.net/manual/en/function.stream-context-create.php
     *
     * @var resource|null
     */
    public $context = null;

    private \Iterator $dir;

    private RegularFileInterface $file;

    private ?string $mode = null;

    private bool $canRead = false;

    private bool $canWrite = false;

    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * Opens a directory for iteration.
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-opendir.php
     */
    public function dir_opendir(string $path, int $options): bool
    {
        if ($this->getContextOption('opendir_fail')) {
            $message = $this->getContextOption('opendir_message');
            if (is_string($message)) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

        /** @var DirectoryInterface|null $dir */
        $dir = MockFileSystem::findByType($path, FileInterface::TYPE_DIR);
        if ($dir === null) {
            trigger_error(
                sprintf('opendir(%s): failed to open dir: No such file or directory', $path),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$this->isReadable($dir)) {
            trigger_error(
                sprintf('opendir(%s): failed to open dir: Permission denied', $path),
                \E_USER_WARNING
            );

            return false;
        }

        $this->dir = $dir->getIterator();

        return true;
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * Closes a directory.
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-closedir.php
     */
    public function dir_closedir(): bool
    {
        return !$this->getContextOption('closedir_fail', false);
    }

    /**
     * Reads an entry from the directory.
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-readdir.php
     *
     * @return string|false
     */
    public function dir_readdir()
    {
        if ($this->getContextOption('readdir_fail')) {
            return false;
        }

        $file = $this->dir->current();
        if (!$file instanceof FileInterface) {
            return false;
        }

        $this->dir->next();

        return $file->getName();
    }

    /**
     * Rewinds the directory iterator.
     *
     * @see https://www.php.net/manual/en/streamwrapper.dir-rewinddir.php
     */
    public function dir_rewinddir(): bool
    {
        if ($this->getContextOption('rewinddir_fail')) {
            return false;
        }

        $this->dir->rewind();

        return true;
    }

    /**
     * Makes a directory.
     *
     * @see https://www.php.net/manual/en/streamwrapper.mkdir.php
     * @see https://www.php.net/manual/en/function.mkdir.php
     */
    public function mkdir(string $path, int $mode, int $options): bool
    {
        if ($this->getContextOption('mkdir_fail')) {
            $message = $this->getContextOption('mkdir_message');
            if (is_string($message)) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

        $permissions = $mode & ~MockFileSystem::umask();

        $file = MockFileSystem::find($path);
        if ($file !== null) {
            trigger_error('mkdir(): File exists', \E_USER_WARNING);

            return false;
        }

        $parts = $this->explodePath($path);
        if (count($parts) < 2) {
            trigger_error('mkdir(): No such file or directory', \E_USER_WARNING);

            return false;
        }

        /** @var DirectoryInterface|null $parent */
        $parent = MockFileSystem::findByType($parts[0], FileInterface::TYPE_DIR);
        if ($parent === null) {
            trigger_error('mkdir(): No such file or directory', \E_USER_WARNING);

            return false;
        }

        $recursive = ($options & \STREAM_MKDIR_RECURSIVE) === \STREAM_MKDIR_RECURSIVE;

        $count = count($parts);
        for ($i = 1; $i < $count; $i++) {
            if (!$this->isWritable($parent)) {
                trigger_error('mkdir(): Permission denied', \E_USER_WARNING);

                return false;
            }

            $file = null;
            if ($parent->hasChild($parts[$i])) {
                $file = $parent->getChild($parts[$i]);
            }

            if ($file instanceof DirectoryInterface) {
                $parent = $file;

                continue;
            }

            if ($file !== null) {
                trigger_error('mkdir(): Not a directory', \E_USER_WARNING);

                return false;
            }

            if (!$recursive && $i !== $count - 1) {
                trigger_error('mkdir(): No such file or directory', \E_USER_WARNING);

                return false;
            }

            $dir = new Directory($parts[$i], $permissions);
            $parent->addChild($dir);
            $parent = $dir;
        }

        return true;
    }

    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * Removes a directory.
     *
     * @see https://www.php.net/manual/en/streamwrapper.rmdir.php
     * @see https://www.php.net/manual/en/function.rmdir.php
     */
    public function rmdir(string $path, int $options): bool
    {
        if ($this->getContextOption('rmdir_fail')) {
            $message = $this->getContextOption('rmdir_message');
            if (is_string($message)) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

        $file = MockFileSystem::find($path);
        if ($file === null) {
            trigger_error(
                sprintf('rmdir(%s): No such file or directory', $path),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$file instanceof DirectoryInterface) {
            trigger_error(
                sprintf('rmdir(%s): Not a directory', $path),
                \E_USER_WARNING
            );

            return false;
        }

        if (count($file->getChildren()) > 0) {
            trigger_error(
                sprintf('rmdir(%s): Directory not empty', $path),
                \E_USER_WARNING
            );

            return false;
        }

        /** @var DirectoryInterface $parent */
        $parent = $file->getParent();
        if (!$this->isWritable($parent)) {
            trigger_error(
                sprintf('rmdir(%s): Permission denied', $path),
                \E_USER_WARNING
            );

            return false;
        }

        $parent->removeChild($file->getName());
        clearstatcache();

        return true;
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * Opens a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-open.php
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        // TODO: Clean this mess up

        if ($this->getContextOption('fopen_fail')) {
            $message = $this->getContextOption('fopen_message');
            if (
                is_string($message)
                && ($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS
            ) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

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

            $file = $this->createFile($path, $options);
            if ($file === null) {
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
                    sprintf('File "%s" is not readable.', $file->getUrl()),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        if ($this->canWrite && !$this->isWritable($file)) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('File "%s" is not writeable.', $file->getUrl()),
                    \E_USER_WARNING
                );
            }

            return false;
        }

        $this->file = new MultiInstanceDecorator($file);
        if ($this->mode === self::MODE_APPEND) {
            $success = $this->file->open();
            $this->file->seek(0, \SEEK_END);
        } else {
            $success = $this->file->open();
            $this->file->seek(0);
            if ($this->mode === self::MODE_WRITE) {
                $this->file->truncate(0);
            }
        }

        if (($options & \STREAM_USE_PATH) === \STREAM_USE_PATH) {
            $openedPath = $this->file->getUrl();
        }

        return $success;
    }

    /**
     * Closes a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-close.php
     */
    public function stream_close(): void
    {
        if ($this->getContextOption('fclose_fail')) {
            return;
        }

        $this->file->close();
    }

    /**
     * Reads from a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-read.php
     */
    public function stream_read(int $count): string
    {
        if ($this->getContextOption('fread_fail')) {
            return '';
        }

        if (!$this->canRead) {
            return '';
        }

        return $this->file->read($count);
    }

    /**
     * Writes to a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-write.php
     */
    public function stream_write(string $data): int
    {
        if ($this->getContextOption('fwrite_fail')) {
            return 0;
        }

        if (!$this->canWrite) {
            return 0;
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
     */
    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        if ($this->getContextOption('fseek_fail')) {
            return false;
        }

        return $this->file->seek($offset, $whence);
    }

    /**
     * Retrieves the current position in a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-tell.php
     */
    public function stream_tell(): int
    {
        if ($this->getContextOption('ftell_fail')) {
            return 0;
        }

        return $this->file->tell();
    }

    /**
     * Checks if end-of-file is reached on a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-eof.php
     */
    public function stream_eof(): bool
    {
        if ($this->getContextOption('feof_fail')) {
            return (bool) $this->getContextOption('feof_response', false);
        }

        // TODO: File "update" mode (what the default stream content uses)
        // does not report EOF. Ever.

        return $this->file->isEof();
    }

    /**
     * Flushes the output.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-flush.php
     */
    public function stream_flush(): bool
    {
        if ($this->getContextOption('fflush_fail')) {
            return false;
        }

        return $this->file->flush();
    }

    // public function stream_lock(int $operation): bool
    // {
    //     // Default to not supporting locking
    //     // @see stream_supports_lock()
    //     // @see flock()
    //     return false;
    // }

    /**
     * Changes stream metadata, e.g. owner or permissions.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-metadata.php
     *
     * @param mixed $value
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        $file = MockFileSystem::find($path);

        if ($option === \STREAM_META_TOUCH) {
            // @phpstan-ignore-next-line
            return $this->touch($file, $path, $value);
        }

        if ($file === null || !$this->isOwner($file)) {
            return false;
        }

        switch ($option) {
            case \STREAM_META_OWNER:
                // @phpstan-ignore-next-line
                $file->setUser($value);
                break;
            case \STREAM_META_GROUP:
                // @phpstan-ignore-next-line
                $file->setGroup($value);
                break;
            case \STREAM_META_ACCESS:
                // @phpstan-ignore-next-line
                $file->setPermissions($value);
                break;
            default:
                return false;
        }

        clearstatcache();

        return true;
    }

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
     * @return int[]|false
     */
    public function stream_stat()
    {
        if ($this->getContextOption('fstat_fail')) {
            return false;
        }

        return $this->file->stat();
    }

    /**
     * Truncates a stream.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-truncate.php
     * @see https://www.php.net/manual/en/function.ftruncate.php
     */
    public function stream_truncate(int $newSize): bool
    {
        if ($this->getContextOption('ftruncate_fail')) {
            return false;
        }

        if (!$this->canWrite) {
            return false;
        }

        return $this->file->truncate($newSize);
    }

    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * Retrieves the underlaying resource.
     *
     * This is not supported and always returns false.
     *
     * @see https://www.php.net/manual/en/streamwrapper.stream-cast.php
     *
     * @return resource|false
     */
    public function stream_cast(int $castAs)
    {
        return false;
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * Renames a file or directory.
     *
     * @see https://www.php.net/manual/en/streamwrapper.rename.php
     * @see https://www.php.net/manual/en/function.rename.php
     */
    public function rename(string $pathFrom, string $pathTo): bool
    {
        if ($this->getContextOption('rename_fail')) {
            $message = $this->getContextOption('rename_message');
            if (is_string($message)) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

        $src = MockFileSystem::find($pathFrom);
        $parts = $this->getFileParts($pathTo);
        $dest = MockFileSystem::find($parts['dirname']);

        if ($src === null || $dest === null) {
            trigger_error(
                sprintf('rename(%s,%s): No such file or directory', $pathFrom, $pathTo),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$dest instanceof DirectoryInterface) {
            trigger_error(
                sprintf('rename(%s,%s): Not a directory', $pathFrom, $pathTo),
                \E_USER_WARNING
            );

            return false;
        }

        if (!$this->isWritable($dest)) {
            trigger_error(
                sprintf('rename(%s,%s): Permission denied', $pathFrom, $pathTo),
                \E_USER_WARNING
            );

            return false;
        }

        if (
            $src->getType() === FileInterface::TYPE_DIR
            && $dest->hasChild($parts['basename'])
        ) {
            $child = $dest->getChild($parts['basename']);
            if (
                $child instanceof ContainerInterface
                && count($child->getChildren()) > 0
            ) {
                // Destination exists and isn't empty
                trigger_error(
                    sprintf('rename(%s,%s): Directory not empty', $pathFrom, $pathTo),
                    \E_USER_WARNING
                );

                return false;
            }

            if ($child->getType() !== FileInterface::TYPE_DIR) {
                // Destination exists as a different file type
                trigger_error(
                    sprintf('rename(%s,%s): Not a directory', $pathFrom, $pathTo),
                    \E_USER_WARNING
                );

                return false;
            }
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
     */
    public function unlink(string $path): bool
    {
        if ($this->getContextOption('unlink_fail')) {
            $message = $this->getContextOption('unlink_message');
            if (is_string($message)) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

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

        $status = $file->unlink();
        clearstatcache();

        return $status;
    }

    /**
     * Retrieves information about a file.
     *
     * @see https://www.php.net/manual/en/streamwrapper.url-stat.php
     * @see https://www.php.net/manual/en/function.stat.php
     *
     * @return array<int|string,int>|false
     */
    public function url_stat(string $path, int $flags)
    {
        if ($this->getContextOption('stat_fail')) {
            $message = $this->getContextOption('stat_message');
            if (
                is_string($message)
                && ($flags & \STREAM_URL_STAT_QUIET) !== \STREAM_URL_STAT_QUIET
            ) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

        $file = MockFileSystem::find($path);
        if ($file === null) {
            if (($flags & \STREAM_URL_STAT_QUIET) !== \STREAM_URL_STAT_QUIET) {
                trigger_error(
                    sprintf('stat(): stat failed for %s', $path),
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

    /**
     * Parses the file open mode.
     */
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

    /**
     * Creates a new file.
     */
    private function createFile(string $path, int $options = 0): ?RegularFileInterface
    {
        $parts = $this->getFileParts($path);

        /** @var DirectoryInterface|null $parent */
        $parent = MockFileSystem::findByType($parts['dirname'], FileInterface::TYPE_DIR);
        if ($parent === null) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('Path "%s" does not exist.', $path),
                    \E_USER_WARNING
                );
            }

            return null;
        }

        if (!$this->isWritable($parent)) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(
                    sprintf('Directory "%s" is not writable.', $parent->getUrl()),
                    \E_USER_WARNING
                );
            }

            return null;
        }

        if ($parent->hasChild($parts['basename'])) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error(sprintf('Path "%s" already exists.', $path), \E_USER_WARNING);
            }

            return null;
        }

        try {
            $file = MockFileSystem::createFile($parts['basename']);
            $file->addTo($parent);
        } catch (BaseException $exception) {
            if (($options & \STREAM_REPORT_ERRORS) === \STREAM_REPORT_ERRORS) {
                trigger_error($exception->getMessage(), \E_USER_WARNING);
            }

            return null;
        }

        return $file;
    }

    /**
     * Updates the timestamps on a file.
     *
     * If the file does not exists, it tries to create it.
     *
     * @param int[] $value
     */
    private function touch(?FileInterface $file, string $path, array $value): bool
    {
        if ($this->getContextOption('touch_fail')) {
            $message = $this->getContextOption('touch_message');
            if (is_string($message)) {
                trigger_error($message, \E_USER_WARNING);
            }

            return false;
        }

        $file = $file ?? $this->createFile($path);
        if ($file === null) {
            trigger_error(
                sprintf('touch(): Unable to create file %s', $path),
                \E_USER_WARNING
            );

            return false;
        }

        $now = time();
        $file->setLastModifyTime($value[0] ?? $now);
        $file->setLastAccessTime($value[1] ?? $now);

        return true;
    }

    /**
     * Splits the path into "dirname" and "basename" parts.
     *
     * @return array<string,string>
     */
    private function getFileParts(string $path): array
    {
        $fileSystem = MockFileSystem::getFileSystem();
        $clean = $fileSystem->getPath($path);

        $sep = $fileSystem->getConfig()->getFileSeparator();
        $pos = mb_strrpos($clean, $sep);

        if ($pos === false) {
            return ['dirname' => '', 'basename' => $clean];
        }

        return [
            'dirname' => mb_substr($clean, 0, $pos),
            'basename' => mb_substr($clean, $pos + 1),
        ];
    }

    /**
     * Splits the path into segmented sections.
     *
     * Each directory or file is an item in the array, e.g.:
     *
     *  - /home/foo/file.txt -> ['', 'home', 'foo', 'file.txt']
     *
     * @return string[]
     */
    private function explodePath(string $path): array
    {
        $fileSystem = MockFileSystem::getFileSystem();
        $clean = $fileSystem->getPath($path);

        $sep = $fileSystem->getConfig()->getFileSeparator();

        return explode($sep, $clean) ?: [$clean];
    }

    /**
     * Checks if the given file is readable for the current user.
     */
    private function isReadable(FileInterface $file): bool
    {
        $config = $file->getConfig();

        return $file->isReadable($config->getUser(), $config->getGroup());
    }

    /**
     * Checks if the given file is writable for the current user.
     */
    private function isWritable(FileInterface $file): bool
    {
        $config = $file->getConfig();

        return $file->isWritable($config->getUser(), $config->getGroup());
    }

    /**
     * Checks if the given file is owned by the current user.
     */
    private function isOwner(FileInterface $file): bool
    {
        return $file->getUser() === $file->getConfig()->getUser();
    }

    /**
     * Gets the context options for this stream.
     *
     * @return mixed[]
     */
    private function getContextOptions(): array
    {
        $context = $this->context;
        if ($context === null) {
            $context = stream_context_get_default();
        }

        $contextOptions = stream_context_get_options($context);

        if (array_key_exists(self::PROTOCOL, $contextOptions)) {
            return $contextOptions[self::PROTOCOL];
        }

        return [];
    }

    /**
     * Gets a specific context option for this stream.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    private function getContextOption(string $option, $default = null)
    {
        $options = $this->getContextOptions();

        if (isset($options[$option])) {
            return $options[$option];
        }

        return $default;
    }
}
