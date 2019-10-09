<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\FinderInterface;

/**
 * Class to find files within the file system.
 */
class Finder implements FinderInterface
{
    /**
     * @var FileSystemInterface
     */
    private $fileSystem = null;

    /**
     * @param FileSystemInterface $fileSystem
     */
    public function __construct(FileSystemInterface $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $path): ?FileInterface
    {
        $parts = $this->getParts($path);
        $file = $this->fileSystem;

        do {
            if (!$file instanceof ContainerInterface) {
                return null;
            }

            $file = $this->getNextPart($file, $parts);
        } while ($file && !empty($parts));

        return $file;
    }

    /**
     * Splits a path into pieces.
     *
     * @param string $path
     *
     * @return string[]
     */
    private function getParts(string $path): array
    {
        $clean = $this->fileSystem->getPath($path);
        $separator = $this->fileSystem->getConfig()->getFileSeparator();

        $parts = explode($separator, $clean);
        if ($parts === false) {
            return [$clean];
        }

        return $parts;
    }

    /**
     * Gets the next piece of the path.
     *
     * @param ContainerInterface $file
     * @param string[] $parts
     *
     * @return FileInterface|null
     */
    private function getNextPart(ContainerInterface $file, array &$parts): ?FileInterface
    {
        $part = array_shift($parts);
        if ($part === null) {
            return null;
        }

        if (!$file->hasChild($part)) {
            return null;
        }

        return $file->getChild($part);
    }
}
