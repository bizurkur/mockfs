<?php declare(strict_types = 1);

namespace MockFileSystem;

use MockFileSystem\ConfigInterface;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Quota\Collection;
use MockFileSystem\Quota\QuotaInterface;

/**
 * Configuration settings for the file system.
 */
final class Config implements ConfigInterface
{
    /**
     * @var int
     */
    private $umask = 0000;

    /**
     * @var string
     */
    private $separator = '/';

    /**
     * @var bool
     */
    private $ignoreCase = false;

    /**
     * @var bool
     */
    private $includeDotFiles = true;

    /**
     * @var bool
     */
    private $normalizeSlashes = false;

    /**
     * @var string[]
     */
    private $blacklist = [];

    /**
     * @var int|null
     */
    private $user = null;

    /**
     * @var int|null
     */
    private $group = null;

    /**
     * @var QuotaInterface
     */
    private $quota = null;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $name => $value) {
            $callback = [$this, 'set'.ucfirst($name)];
            if (!is_callable($callback)) {
                throw new InvalidArgumentException(
                    sprintf('Unknown option "%s"', $name)
                );
            }

            call_user_func($callback, $value);
        }

        if ($this->quota === null) {
            // An empty Collection is an unlimited quota
            $this->quota = new Collection();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'umask' => $this->getUmask(),
            'separator' => $this->getSeparator(),
            'ignoreCase' => $this->getIgnoreCase(),
            'includeDotFiles' => $this->getIncludeDotFiles(),
            'normalizeSlashes' => $this->getNormalizeSlashes(),
            'blacklist' => $this->getBlacklist(),
            'user' => $this->getUser(),
            'group' => $this->getGroup(),
            'quota' => $this->getQuota(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getQuota(): QuotaInterface
    {
        return $this->quota;
    }

    /**
     * {@inheritDoc}
     */
    public function getUser(): int
    {
        if ($this->user !== null) {
            return $this->user;
        }

        return function_exists('posix_getuid') ? posix_getuid() : self::ROOT_UID;
    }

    /**
     * {@inheritDoc}
     */
    public function getGroup(): int
    {
        if ($this->group !== null) {
            return $this->group;
        }

        return function_exists('posix_getgid') ? posix_getgid() : self::ROOT_GID;
    }

    /**
     * {@inheritDoc}
     */
    public function getUmask(): int
    {
        return $this->umask;
    }

    /**
     * {@inheritDoc}
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * {@inheritDoc}
     */
    public function getIgnoreCase(): bool
    {
        return $this->ignoreCase;
    }

    /**
     * {@inheritDoc}
     */
    public function getIncludeDotFiles(): bool
    {
        return $this->includeDotFiles;
    }

    /**
     * {@inheritDoc}
     */
    public function getNormalizeSlashes(): bool
    {
        return $this->normalizeSlashes;
    }

    /**
     * {@inheritDoc}
     */
    public function getBlacklist(): array
    {
        return $this->blacklist;
    }

    /**
     * {@inheritDoc}
     */
    public function setQuota(QuotaInterface $quota): void
    {
        $this->quota = $quota;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(?int $user): void
    {
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function setGroup(?int $group): void
    {
        $this->group = $group;
    }

    /**
     * {@inheritDoc}
     */
    public function setUmask(int $mask): void
    {
        $this->umask = $mask & 0777;
    }

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements

    /**
     * Sets the directory separator.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @param string $separator
     */
    private function setSeparator(string $separator): void
    {
        if (empty($separator)) {
            throw new InvalidArgumentException('Separator cannot be empty');
        }

        $this->separator = $separator;
    }

    /**
     * Sets whether to ignore string case when creating/accessing files.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @param bool $ignoreCase
     */
    private function setIgnoreCase(bool $ignoreCase): void
    {
        $this->ignoreCase = $ignoreCase;
    }

    /**
     * Sets whether to include dot files when iterating through directories.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @param bool $includeDotFiles
     */
    private function setIncludeDotFiles(bool $includeDotFiles): void
    {
        $this->includeDotFiles = $includeDotFiles;
    }

    /**
     * Sets whether to normalize slashes in file paths.
     *
     * This is not meant to be set after construction.
     *
     * @internal
     *
     * @param bool $normalizeSlashes
     */
    private function setNormalizeSlashes(bool $normalizeSlashes): void
    {
        $this->normalizeSlashes = $normalizeSlashes;
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
     */
    private function setBlacklist(array $blacklist): void
    {
        $this->blacklist = $blacklist;
    }
}
