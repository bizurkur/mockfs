<?php

declare(strict_types=1);

namespace MockFileSystem\Config;

use MockFileSystem\Config\Config;

/**
 * Configuration settings for a Windows file system.
 */
class WindowsConfig extends Config
{
    public function getDefaultOptions(): array
    {
        return [
            'umask' => 0000,
            'fileSeparator' => '\\',
            'partitionSeparator' => ':',
            'ignoreCase' => true,
            'includeDotFiles' => true,
            'normalizeSlashes' => true,
            // Based on https://en.wikipedia.org/wiki/Filename
            // Summary: 0x00-0x1f, 0x7f, ", *, /, :, <, >, ?, \, and |
            'blacklist' => [
                'start of heading' => "\x01",
                'start of text' => "\x02",
                'end of text' => "\x03",
                'end of transmission' => "\x04",
                'enquiry' => "\x05",
                'acknowledge' => "\x06",
                'bell' => "\x07",
                'backspace' => "\x08",
                'horizontal tab' => "\x09",
                'new line' => "\x0a",
                'vertical tab' => "\x0b",
                'new page' => "\x0c",
                'carriage return' => "\x0d",
                'shift out' => "\x0e",
                'shift in' => "\x0f",
                'data link escape' => "\x10",
                'device control 1' => "\x11",
                'device control 2' => "\x12",
                'device control 3' => "\x13",
                'device control 4' => "\x14",
                'negative acknowledge' => "\x15",
                'synchronous idle' => "\x16",
                'end of transmission block' => "\x17",
                'cancel' => "\x18",
                'end of medium' => "\x19",
                'substitute' => "\x1a",
                'escape' => "\x1b",
                'file separator' => "\x1c",
                'group separator' => "\x1d",
                'record separator' => "\x1e",
                'unit separator' => "\x1f",
                'delete' => "\x7f",
                '<',
                '>',
                ':',
                'double quote' => '"',
                '/',
                '\\',
                '|',
                '?',
                '*',
            ],
            'user' => null,
            'group' => null,
        ];
    }
}
