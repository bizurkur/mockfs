<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
abstract class AbstractTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MockFileSystem::create();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        MockFileSystem::destroy();

        stream_context_set_default(
            [
                StreamWrapper::PROTOCOL => [
                    'opendir_fail' => false,
                    'opendir_message' => null,
                    'closedir_fail' => false,
                    'readdir_fail' => false,
                    'rewinddir_fail' => false,
                    'mkdir_fail' => false,
                    'mkdir_message' => null,
                    'rmdir_fail' => false,
                    'rmdir_message' => null,
                    'fopen_fail' => false,
                    'fopen_message' => null,
                    'fclose_fail' => false,
                    'fread_fail' => false,
                    'fwrite_fail' => false,
                    'fseek_fail' => false,
                    'ftell_fail' => false,
                    'feof_fail' => false,
                    'feof_response' => false,
                    'fflush_fail' => false,
                    'fstat_fail' => false,
                    'ftruncate_fail' => false,
                    'rename_fail' => false,
                    'rename_message' => null,
                    'stat_fail' => false,
                    'stat_message' => null,
                    'touch_fail' => false,
                    'touch_message' => null,
                    'unlink_fail' => false,
                    'unlink_message' => null,
                ],
            ]
        );
    }

    public function samplePrefixes(): array
    {
        return [
            [StreamWrapper::PROTOCOL . '://'],
            [sys_get_temp_dir()],
        ];
    }

    /**
     * @param mixed[] $options
     */
    protected function setContext(array $options = []): void
    {
        stream_context_set_default([StreamWrapper::PROTOCOL => $options]);
    }

    /**
     * Cleans up temporary files.
     *
     * @param string $file
     */
    protected function cleanup(string $file): void
    {
        register_shutdown_function(
            function () use ($file) {
                if (!@file_exists($file)) {
                    return;
                }

                if (is_file($file)) {
                    @unlink($file);
                } else {
                    @rmdir($file);
                }
            }
        );
    }
}
