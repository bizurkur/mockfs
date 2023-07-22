<?php

declare(strict_types=1);

namespace MockFileSystem;

use MockFileSystem\Components\Block;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\Partition;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Config\Config;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\Content\FullContent;
use MockFileSystem\Content\NullContent;
use MockFileSystem\Content\RandomContent;
use MockFileSystem\Content\ZeroContent;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Visitor\TreeVisitor;
use MockFileSystem\Visitor\VisitorInterface;

/**
 * Class to create the mock file system and register it as a stream.
 */
final class MockFileSystem
{
    /**
     * Whether or not the stream wrapper has been registered.
     */
    private static bool $registered = false;

    private static ?FileSystemInterface $fileSystem = null;

    /**
     * Creates the mock file system.
     *
     * @param array<string,array|string|ContentInterface|null> $structure
     * @param mixed[]|ConfigInterface $options
     */
    public static function create(
        string $name = '',
        ?int $permissions = null,
        array $structure = [],
        $options = []
    ): Partition {
        $config = $options;
        if (is_array($options)) {
            $config = new Config($options);
        }

        if (!$config instanceof ConfigInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Options must be an array or instance of %s; %s given',
                    ConfigInterface::class,
                    gettype($options)
                )
            );
        }

        self::register();

        self::$fileSystem = new FileSystem($config);

        $partition = self::createPartition($name, $permissions, $structure);
        $partition->addTo(self::$fileSystem);

        return $partition;
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
     * If no umask is provided, returns the current umask.
     *
     * @see https://www.php.net/manual/en/function.umask.php
     */
    public static function umask(?int $umask = null): int
    {
        $config = self::getFileSystem()->getConfig();
        $oldMask = $config->getUmask();

        if ($umask !== null) {
            $config->setUmask($umask);
        }

        return $oldMask;
    }

    /**
     * Creates a partition in the file system.
     *
     * @param array<string,array|string|ContentInterface|null> $structure
     */
    public static function createPartition(
        string $name,
        ?int $permissions = null,
        array $structure = []
    ): Partition {
        $config = self::getFileSystem()->getConfig();
        $fileSeparator = $config->getFileSeparator();
        $partitionSeparator = $config->getPartitionSeparator();
        $clean = self::getPath($name);
        $clean = rtrim($clean, $fileSeparator);
        $clean = rtrim($clean, $partitionSeparator);

        $partition = new Partition($clean, $permissions);
        $partition->setConfig($config);

        self::addStructure($structure, $partition);

        return $partition;
    }

    /**
     * Adds a file structure to the given parent.
     *
     * Structure should be an array of data with the file name as the array key.
     * Use a string as the array value to create a file or another array to
     * create a directory. Create a block file by wrapping the name in brackets.
     *
     * [
     *     'foo' => 'this is a file',
     *     'bar' => [
     *         'baz' => 'another file',
     *         'foo2' => [
     *             'foobar' => 'a third file',
     *         ],
     *     ],
     *     'dev' => [
     *         '[null]' => null, // a block file that uses NullContent
     *         '[random]' => null, // a block file that uses RandomContent
     *         '[blar]' => 'a block file with regular content',
     *     ],
     * ]
     *
     * @param array<string,array|string|ContentInterface|null> $structure
     */
    public static function addStructure(array $structure, DirectoryInterface $parent): void
    {
        foreach ($structure as $name => $data) {
            if (!is_string($name)) {
                throw new InvalidArgumentException(
                    sprintf('File name must be a string; received %s', gettype($name))
                );
            }

            self::addStructureComponent($parent, $name, $data);
        }
    }

    /**
     * Creates a directory.
     *
     * @param array<string,array|string|ContentInterface|null> $structure
     */
    public static function createDirectory(
        string $name,
        ?int $permissions = null,
        array $structure = []
    ): Directory {
        $config = self::getFileSystem()->getConfig();

        $directory = new Directory($name, $permissions);
        $directory->setConfig($config);

        self::addStructure($structure, $directory);

        return $directory;
    }

    /**
     * Creates a file.
     *
     * @param ContentInterface|string|null $content
     */
    public static function createFile(
        string $name,
        ?int $permissions = null,
        $content = null
    ): RegularFile {
        $config = self::getFileSystem()->getConfig();

        $file = new RegularFile($name, $permissions, $content);
        $file->setConfig($config);

        return $file;
    }

    /**
     * Creates a block file.
     *
     * @param ContentInterface|string|null $content
     */
    public static function createBlock(
        string $name,
        ?int $permissions = null,
        $content = null
    ): Block {
        $config = self::getFileSystem()->getConfig();

        $block = new Block($name, $permissions, $content);
        $block->setConfig($config);

        return $block;
    }

    /**
     * Visits a file using the given visitor.
     *
     * If a file is not given (null), then it visits the entire mock file system.
     * If a visitor is not given (null), then it defaults to using a TreeVisitor.
     */
    public static function visit(?FileInterface $file = null, ?VisitorInterface $visitor = null): void
    {
        if ($visitor === null) {
            $visitor = new TreeVisitor();
        }

        if ($file === null) {
            $visitor->visitFileSystem(self::getFileSystem());
        } else {
            $visitor->visit($file);
        }
    }

    /**
     * Finds a file.
     *
     * Automatically removes the stream wrapper prefix, if applicable.
     */
    public static function find(string $path): ?FileInterface
    {
        return self::getFileSystem()->find($path);
    }

    /**
     * Finds a file of a given type.
     *
     * Automatically removes the stream wrapper prefix, if applicable.
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
     */
    public static function getPath(string $path): string
    {
        return self::getFileSystem()->getPath($path);
    }

    /**
     * Gets the URL for the file, for use in stream access.
     *
     * Examples:
     * - /foo => mfs:///foo
     * - mfs:///foo => mfs:///foo
     * - /foo/./bar/baz/../ => mfs:///foo/bar/
     */
    public static function getUrl(string $path): string
    {
        return self::getFileSystem()->getUrl($path);
    }

    /**
     * Adds a component to the parent.
     *
     * @param array|string|ContentInterface|null $data
     */
    private static function addStructureComponent(DirectoryInterface $parent, string $name, $data): void
    {
        if (is_array($data)) {
            self::createDirectory($name, null, $data)->addTo($parent);

            return;
        }

        if (substr($name, 0, 1) === '[' && substr($name, -1) === ']') {
            self::addStructureBlock($parent, $name, $data);

            return;
        }

        if (is_string($data) || $data instanceof ContentInterface) {
            self::createFile($name, null, $data)->addTo($parent);

            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Data must be a string (file) or array (directory); received %s',
                gettype($data)
            )
        );
    }

    /**
     * Adds a block file to the parent.
     *
     * @param ContentInterface|string|null $data
     */
    private static function addStructureBlock(DirectoryInterface $parent, string $name, $data): void
    {
        $name = substr($name, 1, -1);
        if ($data === null) {
            $namedContent = [
                'full' => FullContent::class,
                'null' => NullContent::class,
                'random' => RandomContent::class,
                'zero' => ZeroContent::class,
            ];

            $normalized = mb_strtolower($name);
            if (isset($namedContent[$normalized])) {
                $data = new $namedContent[$normalized]();
            }
        }

        self::createBlock($name, null, $data)->addTo($parent);
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
