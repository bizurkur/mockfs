# mockfs
mockfs is a mock file system for PHP.

It can be used to test file operations without actually interacting with the real file system. This means there's no disk operations or cleanup of any kind that is needed. Create the mock file system, throw data in it, and when you're done the data simply disappears. This is the same concept as [vfsStream](https://github.com/bovigo/vfsStream) but expanded to provide additional functionality.


# Advantages


## Expandable

Everything has an interface and can be replaced by your own implementation. Want to create a semi-persistent mock file system? Simply create a `ContentInterface` implementation that writes to a cache.


## Multiple partition support

In mockfs, you can create multiple partitions for when you need to test more complex filesystem interactions. This also helps to enable support for emulating different operating system environments, such as creating a mock Windows filesystem.


## Configurable filesystem settings

mockfs lets you pick your directory separator, illegal characters for file names, partition names, whether or not to be case-sensitive or normalize slashes, as well as set realistic file quotas.

mockfs defaults to using a Unix-style configuration, but comes with a `WindowsConfig` that tries to match what Windows does.


## Emulate special devices

mockfs has built-in support for emulating special devices such as /dev/null, /dev/full, /dev/zero, and /dev/random.


## Simulate failure conditions

mockfs can help to simulate more complex failure conditions. For example, if you need `file_exists()` to pass but want `fopen()` to fail, mockfs has context options for that.


## Multibyte support

As with most real filesystems, multibyte file names and content are supported.


# Install

Install using composer:

```sh
composer require bizurkur/mockfs --dev
```


# Usage

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


# Advanced

## Simulate failures

Sometimes you need `fopen()` or `fread()` to fail and mockfs makes simulating those conditions easier using stream contexts.

```php
<?php

use MockFileSystem\MockFileSystem as mockfs;

mockfs::create();

$file = mockfs::getUrl('/test');
file_put_contents($file, uniqid());

// Use stream context to tell mockfs to fail on fopen()
stream_context_set_default(
    [
        'mfs' => [
            'fopen_fail' => true,
        ]
    ]
);

if (file_exists($file)) {
    $handle = @fopen($file, 'r');
    if ($handle === false) {
        throw new \Exception("uh-oh spaghetti-o's");
    }
}
```


## Emulate different filesystems

You can use mockfs to emulate different filesystem settings. By default, it uses Unix-style filesystem settings (the "/" file separator, files are case-sensitive, and which slash you use matters).

```php
<?php

use MockFileSystem\Config\WindowsConfig;
use MockFileSystem\MockFileSystem as mockfs;

// Create the default "C:\\" partition
// Set the config to use Windows presets
mockfs::create('C:', null, new WindowsConfig());
mockfs::addPartition('D:');

$fileA = mockfs::getUrl('C:\\myfile.txt');
$fileB = mockfs::getUrl('D:\\other.file');

file_put_contents($fileA, 'file A');
file_put_contents($fileB, 'file B');

// Access the file using a different case
var_dump(file_get_contents(strtoupper($fileA)));
// dumps "file A"
```
