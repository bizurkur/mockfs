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

// Create the file system
$root = mockfs::create();

// Prefix a path with the mockfs handle
$file = mockfs::getUrl('/test');
// $file = mfs:///test

// Treat the file as any regular file
file_put_contents($file, 'Hello, World!');
chmod($file, 0600);
```


## Creating the File System

The starting point for all mockfs usage is the `mockfs::create()` method. This method allows you to set the name of the first partition, the permissions it has, and any configuration settings the file system should follow.

> Note: Calling `create()` multiple times will destroy any data previously created.


### Setting the Partition Name

The default mockfs partition is nameless and equates to `/` as the path. However, if for some reason you want to use a different name you can:

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

$root = mockfs::create();
// creates /

$root = mockfs::create('root');
// creates root/

$root = mockfs::create('c:');
// creates c:/
```


### Setting the Permissions

Partitions default to using permissions `0777` (readable and writable to everyone). The `create()` method allows you to override that:

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

$root = mockfs::create('', 0750);
```

This can be useful if you need to make a simple "not readable" or "not writable" test:

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

mockfs::create('', 0000);

$file = mockfs::getUrl('/test');

$handle = @fopen($file, 'w');
if ($handle === false) {
    echo 'Failed to open file';
}
```


### Setting the Configuration

mockfs defaults to using a Linux-style file system configuration. This means all of the following rules apply:

- It uses `/` as the file separator

- Filenames are case-sensitive (e.g. `test.txt` is NOT the same as `TEST.TXT`)

- Everything but `/` and the `null` character are allowed for filenames

- And `\` is not the same as `/`

You can change the configuration settings by either passing in an instance of `MockFileSystem\Config\ConfigInterface` or a flat `array`.

In this example, we tell mockfs to ignore the case of the filename. This means if you have a file named `test.txt` you can access it using `test.txt`, `TEST.TXT`, or `TesT.tXt`.

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

$root = mockfs::create('', null, ['ignoreCase' => true]);

file_put_contents($root->getUrl().'test.txt', 'some data');

$data = file_get_contents($root->getUrl().'TeST.txt');
var_dump($data);
// outputs "some data"
```


#### Configuration Options

The following are all of the valid configuration options that can be used:

{:.table}
| Option | Type | Description |
| ----- | ---- | ----------- |
| `umask` | `int` | Octal representation of the umask to apply to new files added to the system. Defaults to `0000` |
| `fileSeparator` | `string` | File separator to use. Defaults to `/` |
| `partitionSeparator` | `string` | Partition separator to use (e.g. Windows would use `:`). Defaults to empty string |
| `ignoreCase` | `bool` | Whether or not to ignore filename casing (e.g. is `test.txt` the same as `TesT.txT`). Defaults to `false` |
| `includeDotFiles` | `bool` | Whether or not to include dot files (`.` and `..`) when listing directory contents. Defaults to `true` |
| `normalizeSlashes` | `bool` | Whether or not to convert `\` and `/` to whatever the `fileSeparator` option is set to. Defaults to `false` |
| `blacklist` | `string[]` | Array of characters to blacklist in filenames. Can be indexed by a human-friendly name. Defaults to an empty array (`[]`). Please note that the value of the `fileSeparator` and `partitionSeparator` options, as well as the `null` character are always in the blacklist, even when the array is empty |
| `user` | `int|null` | The user ID of the current user. Defaults to `null` (gets the UID from the system) |
| `group` | `int|null` | The group ID of the current user. Defaults to `null` (gets the GID from the system) |


#### Using Custom Configuration Presets

You can also create your own defaults by extending `MockFileSystem\Config\Config::getDefaultOptions()` or creating your own `MockFileSystem\Config\ConfigInterface` implementation.

For example, if you prefer to use Windows-style defaults there's a pre-built config for that. It has the following rules:

- It uses `\` as the file separator

- Filenames are case-insensitive (e.g. `test.txt` is the same as `TEST.TXT`)

- `0x00-0x1f`, `0x7f`, `"`, `*`, `/`, `:`, `<`, `>`, `?`, `\`, and `|` are invalid filename characters

- It does not matter if you use `\` or `/`

```php
<?php

use MockFileSystem\Config\WindowsConfig;
use MockFileSystem\MockFileSystem as mockfs;

// Create the file system using Windows-style settings
mockfs::create('', null, new WindowsConfig());
```


#### Blacklisting Filename Characters

When blacklisting characters in filenames, you can use the array keys to provide a more descriptive meaning to what the character is. This is especially useful for non-printable or whitespace characters. If no string is given as the array key, it will try to display the character itself in the exception that gets thrown.

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

$config = [
    'blacklist' => [
        'tab' => "\t",
        '<',
        '>',
        'delete' => "\x7f",
    ],
];

mockfs::create('', null, $config);

$file = mockfs::getUrl('/in<valid');
file_put_contents($file, uniqid());
// triggers warning for 'Name cannot contain a "<" character.'

$file = mockfs::getUrl("/in\tvalid");
file_put_contents($file, uniqid());
// triggers warning for 'Name cannot contain a "tab" character.'
```


## Setting File/Disk Quotas

You can set quotas per partition to restrict the disk space used or the number of files allowed. Each quota can be applied to a user, group, both, or none (all users).


### Basic Quota

Here is a basic example in which we only allow the file system to contain one file:

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;
use MockFileSystem\Quota\Quota;

$size = Quota::UNLIMITED;
$files = 1; // Only allow one file to exist

$quota = new Quota($size, $files);

$root = mockfs::create();
$root->setQuota($quota);

// This will work
file_put_contents('mfs:///file1', uniqid());

// This will fail because only one file is allowed
file_put_contents('mfs:///file2', uniqid());
```

When creating a quota, there are four parameters: `$size`, `$fileCount`, `$user`, and `$group`:

{:.table}
| Parameter | Type | Description |
| ----- | ---- | ----------- |
| `$size` | `int` | Total number of bytes allowed to be used. Use `-1` (`Quota::UNLIMITED`) for unlimited bytes. |
| `$fileCount` | `int` | Total number of files and directories allowed to be created. Use `-1` (`Quota::UNLIMITED`) for unlimited files. |
| `$user` | `int|null` | The user ID to apply the quota to, or `null` to apply to all users. Defaults to `null`. |
| `$group` | `int|null` | The group ID to apply the quota to, or `null` to apply to all groups. Defaults to `null`. |


### Multiple Quotas

You can also set multiple quotas using a quota collection:

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;
use MockFileSystem\Quota\Collection;
use MockFileSystem\Quota\Quota;

$quotaA = new Quota(1024, Quota::UNLIMITED); // Only 1024 bytes allowed for all users
$quotaB = new Quota(Quota::UNLIMITED, 1, 123); // User 123 is allowed one file

$collection = new Collection([$quotaA]); // Add quotas in the constructor
$collection->addQuota($quotaB); // Or add them individually

$root = mockfs::create();
$root->setQuota($collection);
```


### Custom Quotas

If you need to do anything more complex, you can create your own quota with whatever specific rules you need by implementing `MockFileSystem\Quota\QuotaInterface`.

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;
use MockFileSystem\Quota\QuotaInterface;

class MyQuota implements QuotaInterface
{
    // ...
}

$root = mockfs::create();
$root->setQuota(new MyQuota());
```


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

We can test a class like this by using the stream context options that are built into mockfs. Since we can't pass a stream context directly into the `fopen()` call, we'll have to use `stream_context_set_default()`. Here we want `fopen()` to fail so that is the context option we will set.

We'll use [PHPUnit](https://phpunit.de/) to test this example, but the same concept applies to any other testing framework.

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

    protected function tearDown(): void
    {
        // Unset the option we're going to use in our test
        stream_context_set_default(
            [
                'mfs' => [
                    'fopen_fail' => false,
                ]
            ]
        );
    }

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

Also be sure to unset any context options in the `tearDown()` method or tag the test as `@runInSeparateProcess` to isolate it. If you don't, you'll risk other tests being affected.


### Supported Options

The following context options are available to force different types of failures. You can set any combination of them at the same time. With all the options available, this allows you to get extremely granular in your tests.


#### Directory Operations

{:.table}
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

{:.table}
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


## Browsing the File System

If for any reason you need to view/browse the file system, you can call `mockfs::visit()`. It defaults to using a `MockFileSystem\Visitor\TreeVisitor` to print the file system contents to `STDOUT`.

```php
<?php

mockfs::create('/', null ['bar' => ['baz' => ''], 'foo' => '']);

mockfs::visit();
// mfs://
// └── /
//     ├── bar
//     │   └── baz
//     └── foo
```

If you only want to browse part of the file system, you can pass in a file as the first parameter:

```php
<?php

mockfs::create('/', null ['bar' => ['baz' => ''], 'foo' => '']);
$file = mockfs::find('/bar');

mockfs::visit($file);
// /bar
// └── baz
```

If you want to do something other than print the tree to the screen, you can create a custom visitor by implementing `MockFileSystem\Visitor\VisitorInterface` and pass that in as the second parameter.

```php
<?php

use MockFileSystem\Visitor\VisitorInterface;

mockfs::create('/', null ['bar' => ['baz' => ''], 'foo' => '']);

/** @var VisitorInterface $visitor */
$visitor = ...;
mockfs::visit(null, $visitor);
```
