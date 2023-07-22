<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ChildInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\ReferenceableInterface;
use MockFileSystem\Components\SummarizableInterface;
use MockFileSystem\Exception\NotFoundException;

/**
 * Represents a container of files.
 */
interface ContainerInterface extends ChildInterface, ReferenceableInterface, SummarizableInterface
{
    /**
     * Gets all of the children.
     *
     * @return FileInterface[]
     */
    public function getChildren(): array;

    /**
     * Adds the given child.
     */
    public function addChild(FileInterface $child): void;

    /**
     * Checks if the given child exists.
     */
    public function hasChild(string $name): bool;

    /**
     * Gets the given child.
     *
     * @throws NotFoundException If the child is not found.
     */
    public function getChild(string $name): FileInterface;

    /**
     * Removes the given child.
     *
     * @return bool True if the child existed and was removed; else false.
     */
    public function removeChild(string $name): bool;
}
