---
layout: page
title: Usage
permalink: /usage
guide: true
---

## Basic Usage

An example of the most basic usage:

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

// Create the filesystem
mockfs::create();

// Prefix a path with the mockfs handle
$file = mockfs::getUrl('/test');

// Treat the file as any regular file
file_put_contents($file, 'Hello, World!');
chmod($file, 0600);
```


## Configuration

Soon!


## Testing Complex Failure

Lets say you have a class that checks for an unusual failure condition, such as a file that both exists and is readable yet somehow fails to open. Normally this would be difficult to test but it becomes easy with mockfs.

Here is our file we want to test:

```php
<?php

namespace Acme\File;

class Processor
{
    public function process(string $file)
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new \Exception('File does not exist or is not readable');
        }

        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            throw new \Exception('Failed to open file');
        }

        // More code...
    }
}
```

In this example, we'll use [PHPUnit](https://phpunit.de/) to test it but the same concept applies to any other testing framework.

```php
<?php

namespace Acme\Tests\File;

use Acme\File\Processor;
use MockFileSystem\MockFileSystem as mockfs;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{
    /**
     * @var Processor
     */
    private $fixture = null;

    protected function setUp(): void
    {
        mockfs::create();

        $this->fixture = new Processor();
    }

    /**
     * @runInSeparateProcess
     */
    public function testProcessFailsToOpenFile(): void
    {
        $file = mockfs::getUrl('/example');
        file_put_contents($file, uniqid());

        $this->assertTrue(file_exists($file));
        $this->assertTrue(is_readable($file));
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to open file');

        // Use stream context to tell mockfs to fail on fopen()
        stream_context_set_default(
            [
                // "mfs" is the mockfs stream wrapper
                'mfs' => [
                    'fopen_fail' => true,
                ]
            ]
        );

        $this->fixture->process($file);
    }
}
```

Be careful about where you place the `stream_context_set_default()` call. If you set it too early, you may cause file operations such as `file_put_contents()` to fail. Try not to set it until just before your expected failure.

Also note that we added `@runInSeparateProcess`. We do this because `stream_context_set_default()` has global effects and we don't want this test to change the behavior of any other tests. The safest way to do that is to run it in a separate process so it remains isolated.


### Supported Failures

The following context options are available to force different types of failures. You can set any combination of them at the same time. With all the options available, this allows you to get extremely granular in your tests.


#### Directory Operations

{:.table.table-hover}
| Option | Type | Description |
| ----- | ---- | ----------- |
| `opendir_fail` | `bool` | Force calls to `opendir()` to fail |
| `opendir_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `opendir_fail` is set to `true` |
| `closedir_fail` | `bool` | Force calls to `closedir()` to fail. Please note that even when this fails, PHP still destroys the stream handle |
| `readdir_fail` | `bool` | Force calls to `readdir()` to fail |
| `rewinddir_fail` | `bool` | Force calls to `rewinddir()` to fail. Please note that even when this fails, PHP still reports it as a success |
| `mkdir_fail` | `bool` | Force calls to `mkdir()` to fail |
| `mkdir_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `mkdir_fail` is set to `true` |
| `rmdir_fail` | `bool` | Force calls to `rmdir()` to fail |
| `rmdir_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `rmdir_fail` is set to `true` |


#### File Operations

{:.table.table-hover}
| Option | Type | Description |
| ----- | ---- | ----------- |
| `fopen_fail` | `bool` | Force calls to `fopen()`, `file_get_contents()`, or `file_put_contents()` to fail |
| `fopen_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `fopen_fail` is set to `true` |
| `fclose_fail` | `bool` | Force calls to `fclose()` to fail. Please note that PHP does not report this failure |
| `fread_fail` | `bool` | Force calls to `fread()`, `fgets()`, `fgetcsv()`, and similar to fail
| `fwrite_fail` | `bool` | Force calls to `fwrite()` to fail
| `fseek_fail` | `bool` | Force calls to `fseek()` to fail
| `ftell_fail` | `bool` | Force calls to `ftell()` to fail. Please note that PHP does not correctly report the stream wrapper failure and will return `0` instead
| `feof_fail` | `bool` | Force calls to `feof()` to fail
| `feof_response` | `bool` | Response to return for `feof()`. Defaults to `false`
| `fflush_fail` | `bool` | Force calls to `fflush()` to fail
| `fstat_fail` | `bool` | Force calls to `fstat()` to fail
| `ftruncate_fail` | `bool` | Force calls to `ftruncate()` to fail
| `rename_fail` | `bool` | Force calls to `rename()` to fail
| `rename_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `rename_fail` is set to `true` |
| `stat_fail` | `bool` | Force calls to `stat()` to fail
| `stat_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `stat_fail` is set to `true` |
| `touch_fail` | `bool` | Force calls to `touch()` to fail
| `touch_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `touch_fail` is set to `true` |
| `unlink_fail` | `bool` | Force calls to `unlink()` to fail
| `unlink_message` | `string|null` | The user warning to trigger on failure. This has no effect unless `unlink_fail` is set to `true` |
