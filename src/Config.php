<?php declare(strict_types = 1);

namespace MockFileSystem;

use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Quota\Collection;
use MockFileSystem\Quota\QuotaInterface;

/**
 * Configuration settings for the file system.
 */
class Config
{
    /**
     * ID of root user.
     *
     * This is only used on a non-POSIX system (i.e. Windows) when the user has
     * not been manually set.
     *
     * @var int
     */
    public const ROOT_UID = 0;

    /**
     * ID of root group.
     *
     * This is only used on a non-POSIX system (i.e. Windows) when the group has
     * not been manually set.
     *
     * @var int
     */
    public const ROOT_GID = 0;

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
     * Returns the config as an array.
     *
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'umask' => $this->umask,
            'separator' => $this->separator,
            'ignoreCase' => $this->ignoreCase,
            'normalizeSlashes' => $this->normalizeSlashes,
            'blacklist' => $this->blacklist,
            'user' => $this->user,
            'group' => $this->group,
            'quota' => $this->quota,
        ];
    }

    /**
     * Gets the quota for the file system.
     *
     * @return QuotaInterface
     */
    public function getQuota(): QuotaInterface
    {
        return $this->quota;
    }

    /**
     * Gets the user ID.
     *
     * On non-POSIX system (i.e. Windows) this returns root (0).
     *
     * @see https://www.php.net/manual/en/function.posix-getuid.php
     *
     * @return int
     */
    public function getUser(): int
    {
        if ($this->user !== null) {
            return $this->user;
        }

        return function_exists('posix_getuid') ? posix_getuid() : self::ROOT_UID;
    }

    /**
     * Gets the group ID.
     *
     * On non-POSIX system (i.e. Windows) this returns root (0).
     *
     * @see https://www.php.net/manual/en/function.posix-getgid.php
     *
     * @return int
     */
    public function getGroup(): int
    {
        if ($this->group !== null) {
            return $this->group;
        }

        return function_exists('posix_getgid') ? posix_getgid() : self::ROOT_GID;
    }

    /**
     * Gets the umask.
     *
     * @return int
     */
    public function getUmask(): int
    {
        return $this->umask;
    }

    /**
     * Gets the directory separator.
     *
     * @return string
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * Gets whether to ignore string case when creating/accessing files.
     *
     * @return bool
     */
    public function getIgnoreCase(): bool
    {
        return $this->ignoreCase;
    }

    /**
     * Gets whether to normalize slashes in file paths.
     *
     * @return bool
     */
    public function getNormalizeSlashes(): bool
    {
        return $this->normalizeSlashes;
    }

    /**
     * Gets the blacklist of characters that cannot be used in file names.
     *
     * @return string[]
     */
    public function getBlacklist(): array
    {
        return $this->blacklist;
    }

    /**
     * Sets the quota for the file system.
     *
     * @param QuotaInterface $quota
     */
    public function setQuota(QuotaInterface $quota): void
    {
        $this->quota = $quota;
    }

    /**
     * Sets the user ID.
     *
     * Set to null to default to the real system's user ID.
     *
     * @param int|null $user
     */
    public function setUser(?int $user): void
    {
        $this->user = $user;
    }

    /**
     * Sets the group ID.
     *
     * Set to null to default to the real system's group ID.
     *
     * @param int|null $group
     */
    public function setGroup(?int $group): void
    {
        $this->group = $group;
    }

    /**
     * Sets the umask.
     *
     * @param int $mask
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
