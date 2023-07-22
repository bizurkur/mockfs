<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

/**
 * Represents any component with a path.
 */
interface ReferenceableInterface
{
    /**
     * Gets the full path for the file.
     */
    public function getPath(): string;

    /**
     * Gets the full URL for the file.
     *
     * This is the same as the path, except includes the Mock File System prefix.
     */
    public function getUrl(): string;
}
