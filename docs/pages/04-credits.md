---
layout: page
title: Credits
permalink: /credits
hide: false
guide: true
---

## Inspiration

mockfs is inspired by [vfsStream](https://github.com/bovigo/vfsStream). The code is completely different, but the underlying principle is the same &#151; provide a way to mock the file system.


### How It Differs

Here are some of the key differences between mockfs and vfsStream (v1.6.7):

- Static analysis is cranked to the max to help prevent bugs

- Support for using `/` as the root path

- Support for partitions

- Support for testing more complex failure scenarios

- Support for multiple, more detailed quotas

- Support for emulating different file systems (e.g. you can set up mockfs to behave like Windows)

- Support for special files, such as /dev/null or /dev/random

- Support for multibyte files

- Support for opening the same file with two different handles

- Everything has an interface and can easily be replaced (except the stream wrapper and the class that registers it)

- Does not support `flock()` (yet)

- Does not support mocking files with large content (yet)


## Tools Used

### For the Code

The number of tools used always seems endless, but these are the primary tools that mockfs uses extensively. See the [dependency file]({{ site.repository }}/blob/master/composer.json) for the full list.

- [Composer](https://getcomposer.org/) - PHP package manager

- [PHPUnit](https://phpunit.de/) - Testing framework

- [PHPStan](https://github.com/phpstan/phpstan) - Static analysis tool

- [Infection](https://infection.github.io/) - Mutation testing framework

- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) - Coding standard framework


### For the Docs

Again, the list is likely endless, but these are the primary tools that make this site possible.

- [Bootstrap](https://getbootstrap.com/) - The CSS framework used to style this site

- [Font Awesome](https://fontawesome.com/v4.7.0/) - Scalable vector icons

- [Jekyll](https://jekyllrb.com/) - A simple, extendable, static site generator

- [Jekyll Docs Theme](https://github.com/allejo/jekyll-docs-theme) - The original layout this site is based on (which in turn is based on the Bootstrap 3 documentation site). Note: This site's version of the theme has been modified

- [Jekyll ToC](https://github.com/allejo/jekyll-toc) - Table of Contents generator

- [Jekyll Heading Anchors](https://github.com/allejo/jekyll-anchor-headings) - Generates anchors to the headers

- [Trianglify](https://github.com/qrohlf/trianglify) - Used to generate the triangular header background

- [Sass](https://sass-lang.com/) - The CSS is very Sass-y

- [Stickyfill](https://github.com/wilddeer/stickyfill) - Polyfill for CSS `position: sticky`
