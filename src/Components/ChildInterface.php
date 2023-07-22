<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Exception\RecursionException;

/**
 * Represents a child component.
 */
interface ChildInterface
{
    /**
     * Gets the parent container.
     */
    public function getParent(): ?ContainerInterface;

    /**
     * Sets the parent container.
     *
     * @throws RecursionException If the parent is invalid, e.g. a circular reference.
     */
    public function setParent(?ContainerInterface $parent): ChildInterface;
}
