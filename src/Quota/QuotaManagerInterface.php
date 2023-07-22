<?php

declare(strict_types=1);

namespace MockFileSystem\Quota;

use MockFileSystem\Components\FileInterface;

/**
 * Manages the remaining disk space after a quota is applied.
 */
interface QuotaManagerInterface
{
    /**
     * Checks if there's enough free disk space for the child file.
     *
     * If no child is given, returns the remaining disk space.
     *
     * This uses the current user to determine if there are any quotas that apply.
     *
     * @return int Returns -1 for unlimited space.
     */
    public function getFreeDiskSpace(?FileInterface $child = null): int;
}
