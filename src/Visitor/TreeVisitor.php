<?php

declare(strict_types=1);

namespace MockFileSystem\Visitor;

use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Visitor\VisitorInterface;

/**
 * A visitor designed to mimic the default output of the tree command.
 *
 * Example output:
 *
 * mfs://
 * └── /
 *     ├── dev
 *     │   └── null
 *     ├── etc
 *     │   ├── passwd
 *     │   ├── shadow
 *     │   └── ssh
 *     │       └── ssh_config
 *     └── usr
 *         └── local
 *             └── bin
 *                 ├── composer
 *                 ├── php
 *                 └── python
 *
 * 6 directories, 7 files
 *
 * @see https://en.wikipedia.org/wiki/Tree_(command)
 */
final class TreeVisitor implements VisitorInterface
{
    /**
     * @var resource
     */
    private $handle;

    /**
     * @var array<string,string|int>
     *
     * @phpstan-var array{headerPrefix: string, headerSuffix: string, trunk: string, trunkBranch: string, trunkEnd: string, branchPrefix: string, branchSuffix: string, pointer: string, spacing: int}
     */
    private array $config;

    private int $depth = 0;

    /**
     * @var array<int,bool>
     */
    private array $isLastChild = [];

    private int $directories = 0;

    private int $files = 0;

    /**
     * Defaults to writing to STDOUT.
     *
     * Valid configuration options are:
     * - headerPrefix: value to prepend to header line
     * - headerSuffix: value to append to header line
     * - trunk: value to use as the "trunk" of the tree
     * - trunkBranch: value to use when the trunk branches off
     * - trunkEnd: value to use when the trunk/branch ends
     * - branchPrefix: value to prepend to each branch
     * - branchSuffix: value to append to each branch
     * - pointer: value to use when a file points to another
     * - spacing: number of lines to insert between each output
     *
     * @param resource|null $handle
     * @param array<string,string|int> $config
     */
    public function __construct($handle = null, array $config = [])
    {
        $handle = $handle ?? \STDOUT;
        if (!is_resource($handle)) {
            throw new InvalidArgumentException(
                sprintf('File handle must be of type resource; %s given.', gettype($handle))
            );
        }

        $this->handle = $handle;
        $this->setConfig($config);
    }

    public function visit(FileInterface $file): VisitorInterface
    {
        $this->resetFileCounts();
        $this->realVisit($file, true);
        $this->printTotals();

        return $this;
    }

    public function visitFileSystem(FileSystemInterface $fileSystem): VisitorInterface
    {
        $this->resetFileCounts();
        $this->print($fileSystem->getUrl());
        $this->visitFiles($fileSystem->getChildren(), true);
        $this->printTotals();

        return $this;
    }

    /**
     * Resets the file counts.
     */
    private function resetFileCounts(): void
    {
        $this->depth = 0;
        $this->directories = 0;
        $this->files = 0;
    }

    private function realVisit(FileInterface $file, bool $visit): void
    {
        if ($file instanceof PartitionInterface) {
            $this->visitPartition($file, $visit);
        } elseif ($file instanceof DirectoryInterface) {
            $this->visitDirectory($file);
        } else {
            $this->visitFile($file);
        }
    }

    private function visitPartition(PartitionInterface $partition, bool $visit): void
    {
        $this->directories++;
        $name = $partition->getName();
        $path = $partition->getPath();

        if (!$visit) {
            $this->print($name . $this->config['pointer'] . $path);

            return;
        }

        $this->print($path);
        $this->visitFiles(iterator_to_array($partition));
    }

    private function visitDirectory(DirectoryInterface $directory): void
    {
        if ($directory->isDot()) {
            return;
        }

        $this->directories++;
        $name = $directory->getName();

        $this->print($name, $directory->getPath());
        $this->visitFiles(iterator_to_array($directory));
    }

    private function visitFile(FileInterface $file): void
    {
        if ($this->directories > 0) {
            $this->files++;
        }
        $this->print($file->getName(), $file->getPath());
    }

    /**
     * @param FileInterface[] $files
     */
    private function visitFiles(array $files, bool $visit = false): void
    {
        $this->depth++;

        $count = count($files);
        foreach ($this->getSortedFiles($files) as $index => $file) {
            $this->isLastChild[$this->depth] = $index === $count - 1;
            $this->realVisit($file, $visit);
        }

        $this->depth--;
    }

    /**
     * Gets the files sorted alphabetically.
     *
     * @param FileInterface[] $files
     *
     * @return array<int,FileInterface>
     */
    private function getSortedFiles(array $files): array
    {
        usort(
            $files,
            function (FileInterface $a, FileInterface $b): int {
                return $a->getName() <=> $b->getName();
            }
        );

        return array_values($files);
    }

    /**
     * Prints the file name to the output.
     */
    private function print(string $name, ?string $path = null): void
    {
        if ($this->depth === 0) {
            $this->printHeader($path ?: $name);
        } else {
            $this->printSpacer();
            $this->printFile($name);
        }
    }

    private function printHeader(string $header): void
    {
        fwrite(
            $this->handle,
            sprintf(
                '%s%s%s%s',
                $this->config['headerPrefix'],
                $header,
                $this->config['headerSuffix'],
                PHP_EOL
            )
        );
    }

    /**
     * Prints any spacing between files.
     */
    private function printSpacer(): void
    {
        for ($i = 0; $i < $this->config['spacing']; $i++) {
            fwrite(
                $this->handle,
                sprintf(
                    '%s%s%s',
                    $this->getIndent(),
                    $this->config['trunk'],
                    PHP_EOL
                )
            );
        }
    }

    private function printFile(string $name): void
    {
        fwrite(
            $this->handle,
            sprintf(
                '%s%s%s%s%s%s',
                $this->getIndent(),
                $this->isLastChild() ? $this->config['trunkEnd'] : $this->config['trunkBranch'],
                $this->config['branchPrefix'],
                $name,
                $this->config['branchSuffix'],
                PHP_EOL
            )
        );
    }

    /**
     * Prints the total files visited.
     */
    private function printTotals(): void
    {
        $directoryCount = max(0, $this->directories - 1);
        fwrite(
            $this->handle,
            sprintf(
                '%s%d director%s, %d file%s%s',
                PHP_EOL,
                $directoryCount,
                $directoryCount === 1 ? 'y' : 'ies',
                $this->files,
                $this->files === 1 ? '' : 's',
                PHP_EOL
            )
        );
    }

    /**
     * Gets the indentation string.
     */
    private function getIndent(): string
    {
        $prefix = str_repeat(' ', mb_strlen($this->config['headerPrefix']));

        for ($i = 1; $i < $this->depth; $i++) {
            $prefix .= $this->isLastChild($i)
                ? str_repeat(' ', mb_strlen($this->config['trunkBranch']) + mb_strlen($this->config['branchPrefix']))
                : $this->config['trunk'] . str_repeat(' ', mb_strlen($this->config['branchPrefix']));
        }

        return $prefix;
    }

    /**
     * Checks if this is the last file in the directory.
     */
    private function isLastChild(?int $depth = null): bool
    {
        if ($depth === null) {
            $depth = $this->depth;
        }

        return $this->isLastChild[$depth] ?? true;
    }

    /**
     * @param array<string,string|int> $config
     */
    private function setConfig(array $config): void
    {
        $default = [
            'headerPrefix' => '',
            'headerSuffix' => '',
            'trunk' => '│',
            'trunkBranch' => '├',
            'trunkEnd' => '└',
            'branchPrefix' => '── ',
            'branchSuffix' => '',
            'pointer' => ' -> ',
            'spacing' => 0,
        ];

        // @phpstan-ignore-next-line
        $this->config = array_merge($default, $config);
    }
}
