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

/**
 * Class to create the mock file system and register it as a stream.
 */
final class MockFileSystem
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
     * @param array<string, array|string|ContentInterface|null> $structure
     * @param mixed[]|ConfigInterface $options
     *
     * @return Partition
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
     * If no umask is provided, returns the current umask.
     *
     * @see https://www.php.net/manual/en/function.umask.php
     *
     * @param int|null $umask
     *
     * @return int
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
     * @param string $name
     * @param int|null $permissions
     * @param array<string, array|string|ContentInterface|null> $structure
     *
     * @return Partition
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
     * @param array<string, array|string|ContentInterface|null> $structure
     * @param DirectoryInterface $parent
     */
    public static function addStructure(array $structure, DirectoryInterface $parent): void
    {
        foreach ($structure as $name => $data) {
            if (!is_string($name)) {
                throw new InvalidArgumentException(
                    sprintf('File name must be a string; received %s', gettype($name))
                );
            }

            if (is_array($data)) {
                $child = self::createDirectory($name, null);
                $child->addTo($parent);
                self::addStructure($data, $child);

                continue;
            }

            if (substr($name, 0, 1) === '[' && substr($name, -1) === ']') {
                $name = substr($name, 1, -1);
                if ($data === null) {
                    switch ($name) {
                        case 'null':
                            $data = new NullContent();
                            break;
                        case 'full':
                            $data = new FullContent();
                            break;
                        case 'random':
                            $data = new RandomContent();
                            break;
                        case 'zero':
                            $data = new ZeroContent();
                            break;
                        default:
                            break;
                    }
                }

                self::createBlock($name, null, $data)->addTo($parent);

                continue;
            }

            if (is_string($data) || $data instanceof ContentInterface) {
                self::createFile($name, null, $data)->addTo($parent);

                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Data must be a string (file) or array (directory); received %s',
                    gettype($data)
                )
            );
        }
    }

    /**
     * Creates a directory.
     *
     * @param string $name
     * @param int|null $permissions
     * @param array<string, array|string|ContentInterface|null> $structure
     *
     * @return Directory
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
     * @param string $name
     * @param int|null $permissions
     * @param ContentInterface|string|null $content
     *
     * @return RegularFile
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
     * @param string $name
     * @param int|null $permissions
     * @param ContentInterface|string|null $content
     *
     * @return Block
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
        return self::getFileSystem()->find($path);
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
     *
     * @return string
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
     *
     * @param string $path
     *
     * @return string
     */
    public static function getUrl(string $path): string
    {
        return self::getFileSystem()->getUrl($path);
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

        $sep = self::getFileSystem()->getConfig()->getFileSeparator();
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
     * @param string|null $sep File separator; defaults to config.
     *
     * @return string[]
     */
    public static function explodePath(string $path, ?string $sep = null): array
    {
        $clean = self::getPath($path);

        if ($sep === null) {
            $sep = self::getFileSystem()->getConfig()->getFileSeparator();
        }

        $parts = explode($sep, $clean);
        if ($parts === false) {
            return [$clean];
        }

        return $parts;
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
