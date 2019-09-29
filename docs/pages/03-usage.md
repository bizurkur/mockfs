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


## Advanced Usage

Soon!
