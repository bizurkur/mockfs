---
layout: page
title: Installation
permalink: /install
guide: true
---

## Requirements

For mockfs to be installed, your system should have at least:

- [PHP](https://www.php.net/downloads.php) >= 7.2

- [mbstring](https://www.php.net/manual/en/book.mbstring.php) extension

- [POSIX](https://www.php.net/manual/en/book.posix.php) extension (if running on a Linux OS)


## Composer

It's recommended to install mockfs as a dev dependency using [Composer](https://getcomposer.org/):

```sh
composer require bizurkur/mockfs --dev
```

If you don't have Composer installed, be sure to check out their [download page](https://getcomposer.org/download/).


## Download

If you don't want to use Composer to install, you have a couple alternatives:

- [Clone the repo]({{ site.repository }})
- Download the [source files]({{ site.repository }}/releases) directly
