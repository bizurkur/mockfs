<?php

declare(strict_types=1);

namespace MockFileSystem\Config;

use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;

/**
 * Configuration settings for the file system.
 */
class Config implements ConfigInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $options = [];

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(array $options = [])
    {
        $options = array_merge(
            $this->getDefaultOptions(),
            $options
        );

        foreach ($options as $name => $value) {
            $callback = [$this, 'set' . ucfirst($name)];
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(
                    sprintf('Unknown option "%s"', $name)
                );
            }

            call_user_func($callback, $value);
        }
    }

    public function getDefaultOptions(): array
    {
        return [
            'umask' => 0000,
            'fileSeparator' => '/',
            'partitionSeparator' => '',
            'ignoreCase' => false,
            'includeDotFiles' => true,
            'normalizeSlashes' => false,
            'blacklist' => [],
            'user' => null,
            'group' => null,
        ];
    }

    public function toArray(): array
    {
        return [
            'umask' => $this->getUmask(),
            'fileSeparator' => $this->getFileSeparator(),
            'partitionSeparator' => $this->getPartitionSeparator(),
            'ignoreCase' => $this->getIgnoreCase(),
            'includeDotFiles' => $this->getIncludeDotFiles(),
            'normalizeSlashes' => $this->getNormalizeSlashes(),
            'blacklist' => $this->getBlacklist(),
            'user' => $this->getUser(),
            'group' => $this->getGroup(),
        ];
    }

    public function getUser(): int
    {
        if ($this->options['user'] !== null) {
            /** @var int */
            return $this->options['user'];
        }

        return function_exists('posix_getuid') ? posix_getuid() : self::ROOT_UID;
    }

    public function getGroup(): int
    {
        if ($this->options['group'] !== null) {
            /** @var int */
            return $this->options['group'];
        }

        return function_exists('posix_getgid') ? posix_getgid() : self::ROOT_GID;
    }

    public function getUmask(): int
    {
        /** @var int */
        return $this->options['umask'];
    }

    public function getFileSeparator(): string
    {
        /** @var string */
        return $this->options['fileSeparator'];
    }

    public function getPartitionSeparator(): string
    {
        /** @var string */
        return $this->options['partitionSeparator'];
    }

    public function getIgnoreCase(): bool
    {
        /** @var bool */
        return $this->options['ignoreCase'];
    }

    public function getIncludeDotFiles(): bool
    {
        /** @var bool */
        return $this->options['includeDotFiles'];
    }

    public function getNormalizeSlashes(): bool
    {
        /** @var bool */
        return $this->options['normalizeSlashes'];
    }

    public function getBlacklist(): array
    {
        /** @var array<string> */
        return $this->options['blacklist'];
    }

    public function setUser(?int $user): ConfigInterface
    {
        $this->options['user'] = $user;

        return $this;
    }

    public function setGroup(?int $group): ConfigInterface
    {
        $this->options['group'] = $group;

        return $this;
    }

    public function setUmask(int $mask): ConfigInterface
    {
        $this->options['umask'] = $mask & 0777;

        return $this;
    }

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements

    /**
     * Sets the file separator.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @phpstan-ignore-next-line
     */
    private function setFileSeparator(string $separator): void
    {
        if (empty($separator)) {
            throw new InvalidArgumentException('Separator cannot be empty');
        }

        $this->options['fileSeparator'] = $separator;
    }

    /**
     * Sets the partition separator.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @phpstan-ignore-next-line
     */
    private function setPartitionSeparator(string $separator): void
    {
        $this->options['partitionSeparator'] = $separator;
    }

    /**
     * Sets whether to ignore string case when creating/accessing files.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @phpstan-ignore-next-line
     */
    private function setIgnoreCase(bool $ignoreCase): void
    {
        $this->options['ignoreCase'] = $ignoreCase;
    }

    /**
     * Sets whether to include dot files when iterating through directories.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @phpstan-ignore-next-line
     */
    private function setIncludeDotFiles(bool $includeDotFiles): void
    {
        $this->options['includeDotFiles'] = $includeDotFiles;
    }

    /**
     * Sets whether to normalize slashes in file paths.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @phpstan-ignore-next-line
     */
    private function setNormalizeSlashes(bool $normalizeSlashes): void
    {
        $this->options['normalizeSlashes'] = $normalizeSlashes;
    }

    /**
     * Sets the blacklist of characters that cannot be used in file names.
     *
     * This should be an array of single characters, optionally with a key that
     * includes a more readable name (very useful for non-printable or
     * whitespace characters).
     *
     * Example:
     * [
     *     ':',
     *     '>',
     *     '<',
     *     'tab' => "\t",
     *     'newline' => "\n",
     *     'backspace' => "\010",
     * ]
     *
     * The value of self::$separator is automatically blacklisted, as well as
     * the "null" character. You do not need to include them in this list.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @param string[] $blacklist
     *
     * @phpstan-ignore-next-line
     */
    private function setBlacklist(array $blacklist): void
    {
        $this->options['blacklist'] = $blacklist;
    }
}
