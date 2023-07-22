<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\SummaryInterface;

/**
 * Represents any component that can be summarized.
 */
interface SummarizableInterface
{
    /**
     * Gets a summary of all the files.
     *
     * Optionally filter it down to a specific user or group (or both).
     *
     * This can be useful for quotas.
     */
    public function getSummary(?int $user = null, ?int $group = null): SummaryInterface;
}
