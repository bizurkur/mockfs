---
layout: page
title: Overview
permalink: /overview
guide: true
---

## What is It?

mockfs is a mock file system for PHP.

It can be used to test file operations without actually interacting with the real file system. This means there's no disk operations or cleanup of any kind that is needed. Create the mock file system, throw data in it, and when you're done the data simply disappears. This is the same concept as [vfsStream](https://github.com/bovigo/vfsStream), but it's written from the ground up to be more extensible and follow more strict coding practices.

Best of all, mockfs can be used in any PHP testing framework.


## Features

mockfs comes with several useful features to test complex scenarios:

- Create mock files and folders

- Context options to test failures in file operations such as `fopen()` or `fread()`

- Configurable file quotas to limit disk space or number of files by user, group, or both

- Support for multibyte files and filenames

- Support for multiple partitions

- Configurable directory separator, case sensitivity, and filename character blacklist

- Supports multiple read/writes to the same file at the same time

- Includes support for special files such as /dev/null, /dev/random, /dev/zero, and /dev/full

- Ability to emulate different filesystem environments

Best of all, mockfs is quite extensible. If there's not already an option to perform a certain style of test, you can create a custom "file" to fail or succeed exactly how you want it to. A good example of this are the special files, such as [FullContent]({{ site.repository }}/blob/master/src/Content/FullContent.php).


## How It Works

Using a [custom stream wrapper](https://www.php.net/manual/en/class.streamwrapper.php), mockfs maps file locations to objects stored in memory. These objects act like real files and can be interacted with like any other file.


## Limitations

Certain functions do not work with custom stream wrappers and thus they do not work with mockfs. There are no known ways around these limitations. If you need to test code using any of these functions, you will likely need to use real files:

  - `tempnam()`

  - `realpath()`

  - `ZipArchive`

As of this time, mockfs doesn't have the code to support the following (yet):

  - `flock()` - Advisory file locking

  - `stream_select()` - Retrieve the underlaying resource

  - `stream_set_blocking()` - Set blocking/non-blocking mode

  - `stream_set_timeout()` - Set timeout period

  - `stream_set_write_buffer()` - Sets write file buffering

  - Symlinks
