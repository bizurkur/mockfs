<?php declare(strict_types = 1);

namespace MockFileSystem;

use MockFileSystem\Components\Block;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\Partition;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Config;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\Exception\NotFoundException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\Quota\Collection;
use MockFileSystem\Quota\Quota;
use MockFileSystem\StreamWrapper;

/**
 * Class to create the mock file system and register it as a stream.
 */
class MockFileSystem
{
    /**
     * Whether or not the stream wrapper has been registered.
     *
     * @var bool
     */
    private static $registered = false;

    /**
     * @var FileSystemInterface|null
     */
    private static $fileSystem = null;

    /**
     * Creates the mock file system.
     *
     * @param string $name
     * @param int|null $permissions
     * @param mixed[] $options
     *
     * @return FileSystem
     */
    public static function create(
        string $name = '',
        ?int $permissions = null,
        array $options = []
    ): FileSystem {
        self::register();

        self::$fileSystem = new FileSystem(new Config($options));
        self::createPartition($name, $permissions);

        return self::$fileSystem;
    }

    /**
     * Destroys the file system.
     */
    public static function destroy(): void
    {
        self::unregister();
        self::$fileSystem = null;
    }

    /**
     * Gets the file system.
     *
     * @return FileSystemInterface
     */
    public static function getFileSystem(): FileSystemInterface
    {
        if (self::$fileSystem === null) {
            throw new RuntimeException('File system has not been created.');
        }

        return self::$fileSystem;
    }

    /**
     * Changes the current umask.
     *
     * Sets the umask and returns the old umask.
     *
     * If no mask is provided, returns the current umask.
     *
     * @see https://www.php.net/manual/en/function.umask.php
     *
     * @param int|null $mask
     *
     * @return int
     */
    public static function umask(?int $mask = null): int
    {
        $config = self::getFileSystem()->getConfig();
        $oldMask = $config->getUmask();

        if ($mask !== null) {
            $config->setUmask($mask);
        }

        return $oldMask;
    }

    /**
     * Creates a partition in the file system.
     *
     * @param string $name
     * @param int|null $permissions
     *
     * @return Partition
     */
    public static function createPartition(string $name, ?int $permissions = null): Partition
    {
        $sep = self::getFileSystem()->getConfig()->getSeparator();
        $clean = rtrim(self::getPath($name), $sep);
        $parts = self::getFileParts($clean);

        $partition = new Partition($parts['basename'], $permissions);

        // Add all partitions at the base of the file system
        self::getFileSystem()->addChild($partition);

        // If this partition is also a child directory then add it there, too
        if (mb_strpos($clean, $sep) !== false) {
            self::getDirectory($parts['dirname'])->addChild($partition);
        }

        return $partition;
    }

    /**
     * Creates a directory.
     *
     * @param string $path
     * @param int|null $permissions
     *
     * @return Directory
     */
    public static function createDirectory(string $path, ?int $permissions = null): Directory
    {
        /** @var Directory $file */
        $file = self::createAbstractFile(Directory::class, $path, $permissions);

        return $file;
    }

    /**
     * Creates a file.
     *
     * @param string $path
     * @param int|null $permissions
     * @param ContentInterface|null $content
     *
     * @return RegularFile
     */
    public static function createFile(
        string $path,
        ?int $permissions = null,
        ?ContentInterface $content = null
    ): RegularFile {
        /** @var RegularFile $file */
        $file = self::createAbstractFile(RegularFile::class, $path, $permissions, $content);

        return $file;
    }

    /**
     * Creates a block file.
     *
     * @param string $path
     * @param int|null $permissions
     *
     * @return Block
     */
    public static function createBlock(string $path, ?int $permissions = null): Block
    {
        /** @var Block $file */
        $file = self::createAbstractFile(Block::class, $path, $permissions);

        return $file;
    }

    /**
     * Adds a quota to the file system.
     *
     * @param int $size Number of bytes to limit to; -1 for no limit.
     * @param int $fileCount Number of files to limit to; -1 for no limit.
     * @param int|null $user User ID to apply limit to; null for all users.
     * @param int|null $group Group ID to apply limit to; null for all groups.
     */
    public static function addQuota(
        int $size,
        int $fileCount,
        ?int $user = null,
        ?int $group = null
    ): void {
        $quota = new Quota($size, $fileCount, $user, $group);

        $oldQuota = self::getFileSystem()->getConfig()->getQuota();
        if ($oldQuota instanceof Collection) {
            $oldQuota->addQuota($quota);

            return;
        }

        $newQuota = new Collection([$oldQuota, $quota]);

        self::getFileSystem()->getConfig()->setQuota($newQuota);
    }

    /**
     * Finds a file.
     *
     * Automatically removes the stream wrapper prefix, if applicable.
     *
     * @param string $path
     *
     * @return FileInterface|null
     */
    public static function find(string $path): ?FileInterface
    {
        $clean = self::getPath($path);

        return self::getFileSystem()->find($clean);
    }

    /**
     * Finds a file of a given type.
     *
     * Automatically removes the stream wrapper prefix, if applicable.
     *
     * @param string $path
     * @param int $type
     *
     * @return FileInterface|null
     */
    public static function findByType(string $path, int $type): ?FileInterface
    {
        $file = self::find($path);
        if ($file && $file->getType() === $type) {
            return $file;
        }

        return null;
    }

    /**
     * Gets the real path for a file.
     *
     * Automatically removes the stream wrapper prefix, if applicable.
     *
     * Examples:
     * - /foo => /foo
     * - mfs:///foo => /foo
     * - mfs:///foo/./bar/baz/../ => /foo/bar/
     *
     * @param string $path
     * @param string|null $sep Directory separator; defaults to config.
     *
     * @return string
     */
    public static function getPath(string $path, ?string $sep = null): string
    {
        $config = self::getFileSystem()->getConfig();
        if ($sep === null) {
            $sep = $config->getSeparator();
        }
        $clean = $path;

        if ($config->getNormalizeSlashes()) {
            $clean = str_replace(['\\', '/'], $sep, $clean);
        }

        $prefix = StreamWrapper::PROTOCOL.'://';
        if ($prefix === mb_substr($clean, 0, mb_strlen($prefix))) {
            $clean = mb_substr($clean, mb_strlen($prefix));
        }

        if (mb_substr($clean, -strlen($sep)) === $sep) {
            $clean = mb_substr($clean, 0, -strlen($sep));
        }

        $parts = explode($sep, $clean);
        if ($parts === false) {
            return $clean;
        }

        $files = [];

        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }

            if ($part !== '..') {
                $files[] = $part;
            } elseif (count($files) > 1) {
                array_pop($files);
            }
        }

        return implode($sep, $files);
    }

    /**
     * Gets the URL for the file, for use in stream access.
     *
     * Examples:
     * - /foo => mfs:///foo
     * - mfs:///foo => mfs:///foo
     * - /foo/./bar/baz/../ => /foo/bar/
     *
     * @param string $path
     * @param string|null $sep Directory separator; defaults to config.
     *
     * @return string
     */
    public static function getUrl(string $path, ?string $sep = null): string
    {
        return StreamWrapper::PROTOCOL.'://'.self::getPath($path, $sep);
    }

    /**
     * Splits the path into "dirname" and "basename" parts.
     *
     * @param string $path
     *
     * @return array<string, string>
     */
    public static function getFileParts(string $path): array
    {
        $clean = self::getPath($path);

        $sep = self::getFileSystem()->getConfig()->getSeparator();
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
     * @param string $path
     * @param string|null $sep Directory separator; defaults to config.
     *
     * @return string[]
     */
    public static function explodePath(string $path, ?string $sep = null): array
    {
        $clean = self::getPath($path, $sep);

        if ($sep === null) {
            $sep = self::getFileSystem()->getConfig()->getSeparator();
        }

        $parts = explode($sep, $clean);
        if ($parts === false) {
            return [$clean];
        }

        return $parts;
    }

    /**
     * Gets the directory for path.
     *
     * @param string $path
     *
     * @return DirectoryInterface
     *
     * @throws NotFoundException If the path is not a directory.
     */
    public static function getDirectory(string $path): DirectoryInterface
    {
        /** @var DirectoryInterface|null $parent */
        $parent = self::findByType($path, FileInterface::TYPE_DIR);
        if ($parent === null) {
            throw new NotFoundException(
                sprintf('Directory "%s" does not exist.', $path)
            );
        }

        return $parent;
    }

    /**
     * Creates a file of the given class.
     *
     * @param string $class
     * @param string $path
     * @param int|null $permissions
     * @param array<int, mixed> $args
     *
     * @return FileInterface
     */
    private static function createAbstractFile(
        string $class,
        string $path,
        ?int $permissions,
        ...$args
    ): FileInterface {
        $parts = self::getFileParts($path);
        $parent = self::getDirectory($parts['dirname']);

        if ($parent->hasChild($parts['basename'])) {
            throw new RuntimeException(
                sprintf('Path "%s" already exists.', $path)
            );
        }

        $child = new $class($parts['basename'], $permissions, ...$args);
        $parent->addChild($child);

        return $child;
    }

    /**
     * Registers the stream wrapper, if not already registered.
     */
    private static function register(): void
    {
        if (self::$registered) {
            return;
        }

        if (!stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to register %s:// protocol.',
                    StreamWrapper::PROTOCOL
                )
            );
        }

        self::$registered = true;
    }

    /**
     * Unregisters the stream wrapper, if registered.
     */
    private static function unregister(): void
    {
        if (!self::$registered) {
            return;
        }

        if (!in_array(StreamWrapper::PROTOCOL, stream_get_wrappers(), true)) {
            self::$registered = false;

            return;
        }

        if (!stream_wrapper_unregister(StreamWrapper::PROTOCOL)) {
            // TODO: Find a way to test this
            throw new RuntimeException(
                sprintf(
                    'Unable to unregister %s:// protocol.',
                    StreamWrapper::PROTOCOL
                )
            );
        }

        self::$registered = false;
    }
}
