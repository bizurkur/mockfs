<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Quota;

use MockFileSystem\Quota\Quota;
use MockFileSystem\Quota\QuotaInterface;
use PHPUnit\Framework\TestCase;

class QuotaTest extends TestCase
{
    public function testInstanceOf(): void
    {
        $fixture = new Quota(rand(), rand());

        self::assertInstanceOf(QuotaInterface::class, $fixture);
    }

    public function testGetSize(): void
    {
        $size = rand();

        $fixture = new Quota($size, rand());

        self::assertEquals($size, $fixture->getSize());
    }

    public function testGetFileCount(): void
    {
        $count = rand();

        $fixture = new Quota(rand(), $count);

        self::assertEquals($count, $fixture->getFileCount());
    }

    public function testGetUserNull(): void
    {
        $fixture = new Quota(rand(), rand());

        self::assertNull($fixture->getUser());
    }

    public function testGetUser(): void
    {
        $user = rand();

        $fixture = new Quota(rand(), rand(), $user);

        self::assertEquals($user, $fixture->getUser());
    }

    public function testGetGroupNull(): void
    {
        $fixture = new Quota(rand(), rand());

        self::assertNull($fixture->getGroup());
    }

    public function testGetGroup(): void
    {
        $group = rand();

        $fixture = new Quota(rand(), rand(), null, $group);

        self::assertEquals($group, $fixture->getGroup());
    }

    /**
     * @dataProvider sampleAppliesTo
     */
    public function testAppliesTo(
        ?int $quotaUser,
        ?int $quotaGroup,
        int $user,
        int $group,
        bool $expected
    ): void {
        $fixture = new Quota(rand(), rand(), $quotaUser, $quotaGroup);

        $actual = $fixture->appliesTo($user, $group);

        self::assertEquals($expected, $actual);
    }

    public function sampleAppliesTo(): array
    {
        $userA = rand(10, 19);
        $userB = rand(20, 29);
        $groupA = rand(30, 39);
        $groupB = rand(40, 49);

        return [
            'all users and groups, applied to any user and group' => [
                'quotaUser' => null,
                'quotaGroup' => null,
                'user' => rand(),
                'group' => rand(),
                'expected' => true,
            ],
            'all users of group A, applied to group A' => [
                'quotaUser' => null,
                'quotaGroup' => $groupA,
                'user' => rand(),
                'group' => $groupA,
                'expected' => true,
            ],
            'all users of group A, not applied to group B' => [
                'quotaUser' => null,
                'quotaGroup' => $groupA,
                'user' => rand(),
                'group' => $groupB,
                'expected' => false,
            ],
            'user A of all groups, applied to user A' => [
                'quotaUser' => $userA,
                'quotaGroup' => null,
                'user' => $userA,
                'group' => rand(),
                'expected' => true,
            ],
            'user A of all groups, not applied to user B' => [
                'quotaUser' => $userA,
                'quotaGroup' => null,
                'user' => $userB,
                'group' => rand(),
                'expected' => false,
            ],
            'user A of group A, applied to user A of group A' => [
                'quotaUser' => $userA,
                'quotaGroup' => $groupA,
                'user' => $userA,
                'group' => $groupA,
                'expected' => true,
            ],
            'user A of group A, not applied to user B of group A' => [
                'quotaUser' => $userA,
                'quotaGroup' => $groupA,
                'user' => $userB,
                'group' => $groupA,
                'expected' => false,
            ],
            'user A of group A, not applied to user A of group B' => [
                'quotaUser' => $userA,
                'quotaGroup' => $groupA,
                'user' => $userA,
                'group' => $groupB,
                'expected' => false,
            ],
            'user A of group A, not applied to user B of group B' => [
                'quotaUser' => $userA,
                'quotaGroup' => $groupA,
                'user' => $userB,
                'group' => $groupB,
                'expected' => false,
            ],
        ];
    }

    public function testDefaultAppliesToAll(): void
    {
        $fixture = new Quota(rand(), rand());

        $actual = $fixture->appliesTo(rand(), rand());

        self::assertTrue($actual);
    }

    /**
     * @dataProvider sampleRemainingSize
     */
    public function testGetRemainingSize(
        array $args,
        int $used,
        int $user,
        int $group,
        int $expected
    ): void {
        $fixture = new Quota(...$args);

        $actual = $fixture->getRemainingSize($used, $user, $group);

        self::assertEquals($expected, $actual);
    }

    public function sampleRemainingSize(): array
    {
        $userA = rand(10, 19);
        $userB = rand(20, 29);
        $groupA = rand(30, 39);
        $groupB = rand(40, 49);
        $limit = rand(100, 199);
        $expected = rand(1, 99);

        return [
            'unlimited size' => [
                'args' => [QuotaInterface::UNLIMITED, rand()],
                'used' => rand(),
                'user' => rand(),
                'group' => rand(),
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'all users and groups, no used' => [
                'args' => [$limit, rand()],
                'used' => 0,
                'user' => rand(),
                'group' => rand(),
                'expected' => $limit,
            ],
            'all users and groups, some used' => [
                'args' => [$limit, rand()],
                'used' => $limit - $expected,
                'user' => rand(),
                'group' => rand(),
                'expected' => $expected,
            ],
            'all users and groups, all used' => [
                'args' => [$limit, rand()],
                'used' => $limit,
                'user' => rand(),
                'group' => rand(),
                'expected' => 0,
            ],
            'all users and groups, over used' => [
                'args' => [$limit - $expected, rand()],
                'used' => $limit,
                'user' => rand(),
                'group' => rand(),
                'expected' => 0,
            ],
            'all users of group A, applied to group A, no used' => [
                'args' => [$limit, rand(), null, $groupA],
                'used' => 0,
                'user' => rand(),
                'group' => $groupA,
                'expected' => $limit,
            ],
            'all users of group A, applied to group A, some used' => [
                'args' => [$limit, rand(), null, $groupA],
                'used' => $limit - $expected,
                'user' => rand(),
                'group' => $groupA,
                'expected' => $expected,
            ],
            'all users of group A, applied to group A, all used' => [
                'args' => [$limit, rand(), null, $groupA],
                'used' => $limit,
                'user' => rand(),
                'group' => $groupA,
                'expected' => 0,
            ],
            'all users of group A, applied to group A, over used' => [
                'args' => [$limit - $expected, rand(), null, $groupA],
                'used' => $limit,
                'user' => rand(),
                'group' => $groupA,
                'expected' => 0,
            ],
            'all users of group A, not applied to group B' => [
                'args' => [$limit, rand(), null, $groupA],
                'used' => rand(),
                'user' => rand(),
                'group' => $groupB,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of all groups, applied to user A, no used' => [
                'args' => [$limit, rand(), $userA, null],
                'used' => 0,
                'user' => $userA,
                'group' => rand(),
                'expected' => $limit,
            ],
            'user A of all groups, applied to user A, some used' => [
                'args' => [$limit, rand(), $userA, null],
                'used' => $limit - $expected,
                'user' => $userA,
                'group' => rand(),
                'expected' => $expected,
            ],
            'user A of all groups, applied to user A, all used' => [
                'args' => [$limit, rand(), $userA, null],
                'used' => $limit,
                'user' => $userA,
                'group' => rand(),
                'expected' => 0,
            ],
            'user A of all groups, applied to user A, over used' => [
                'args' => [$limit - $expected, rand(), $userA, null],
                'used' => $limit,
                'user' => $userA,
                'group' => rand(),
                'expected' => 0,
            ],
            'user A of all groups, not applied to user B' => [
                'args' => [$limit, rand(), $userA, null],
                'used' => rand(),
                'user' => $userB,
                'group' => rand(),
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of group A, applied to user A of group A, no used' => [
                'args' => [$limit, rand(), $userA, $groupA],
                'used' => 0,
                'user' => $userA,
                'group' => $groupA,
                'expected' => $limit,
            ],
            'user A of group A, applied to user A of group A, some used' => [
                'args' => [$limit, rand(), $userA, $groupA],
                'used' => $limit - $expected,
                'user' => $userA,
                'group' => $groupA,
                'expected' => $expected,
            ],
            'user A of group A, applied to user A of group A, all used' => [
                'args' => [$limit, rand(), $userA, $groupA],
                'used' => $limit,
                'user' => $userA,
                'group' => $groupA,
                'expected' => 0,
            ],
            'user A of group A, applied to user A of group A, over used' => [
                'args' => [$limit - $expected, rand(), $userA, $groupA],
                'used' => $limit,
                'user' => $userA,
                'group' => $groupA,
                'expected' => 0,
            ],
            'user A of group A, not applied to user A of group B' => [
                'args' => [$limit, rand(), $userA, $groupA],
                'used' => rand(),
                'user' => $userA,
                'group' => $groupB,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of group A, not applied to user B of group A' => [
                'args' => [$limit, rand(), $userA, $groupA],
                'used' => rand(),
                'user' => $userB,
                'group' => $groupA,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of group A, not applied to user B of group B' => [
                'args' => [$limit, rand(), $userA, $groupA],
                'used' => rand(),
                'user' => $userB,
                'group' => $groupB,
                'expected' => QuotaInterface::UNLIMITED,
            ],
        ];
    }

    /**
     * @dataProvider sampleRemainingFileCount
     */
    public function testGetRemainingFileCount(
        array $args,
        int $used,
        int $user,
        int $group,
        int $expected
    ): void {
        $fixture = new Quota(...$args);

        $actual = $fixture->getRemainingFileCount($used, $user, $group);

        self::assertEquals($expected, $actual);
    }

    public function sampleRemainingFileCount(): array
    {
        $userA = rand(10, 19);
        $userB = rand(20, 29);
        $groupA = rand(30, 39);
        $groupB = rand(40, 49);
        $limit = rand(100, 199);
        $expected = rand(1, 99);

        return [
            'unlimited file count' => [
                'args' => [rand(), QuotaInterface::UNLIMITED],
                'used' => rand(),
                'user' => rand(),
                'group' => rand(),
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'all users and groups, no used' => [
                'args' => [rand(), $limit],
                'used' => 0,
                'user' => rand(),
                'group' => rand(),
                'expected' => $limit,
            ],
            'all users and groups, some used' => [
                'args' => [rand(), $limit],
                'used' => $limit - $expected,
                'user' => rand(),
                'group' => rand(),
                'expected' => $expected,
            ],
            'all users and groups, all used' => [
                'args' => [rand(), $limit],
                'used' => $limit,
                'user' => rand(),
                'group' => rand(),
                'expected' => 0,
            ],
            'all users and groups, over used' => [
                'args' => [rand(), $limit - $expected],
                'used' => $limit,
                'user' => rand(),
                'group' => rand(),
                'expected' => 0,
            ],
            'all users of group A, applied to group A, no used' => [
                'args' => [rand(), $limit, null, $groupA],
                'used' => 0,
                'user' => rand(),
                'group' => $groupA,
                'expected' => $limit,
            ],
            'all users of group A, applied to group A, some used' => [
                'args' => [rand(), $limit, null, $groupA],
                'used' => $limit - $expected,
                'user' => rand(),
                'group' => $groupA,
                'expected' => $expected,
            ],
            'all users of group A, applied to group A, all used' => [
                'args' => [rand(), $limit, null, $groupA],
                'used' => $limit,
                'user' => rand(),
                'group' => $groupA,
                'expected' => 0,
            ],
            'all users of group A, applied to group A, over used' => [
                'args' => [rand(), $limit - $expected, null, $groupA],
                'used' => $limit,
                'user' => rand(),
                'group' => $groupA,
                'expected' => 0,
            ],
            'all users of group A, not applied to group B' => [
                'args' => [rand(), $limit, null, $groupA],
                'used' => rand(),
                'user' => rand(),
                'group' => $groupB,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of all groups, applied to user A, no used' => [
                'args' => [rand(), $limit, $userA, null],
                'used' => 0,
                'user' => $userA,
                'group' => rand(),
                'expected' => $limit,
            ],
            'user A of all groups, applied to user A, some used' => [
                'args' => [rand(), $limit, $userA, null],
                'used' => $limit - $expected,
                'user' => $userA,
                'group' => rand(),
                'expected' => $expected,
            ],
            'user A of all groups, applied to user A, all used' => [
                'args' => [rand(), $limit, $userA, null],
                'used' => $limit,
                'user' => $userA,
                'group' => rand(),
                'expected' => 0,
            ],
            'user A of all groups, applied to user A, over used' => [
                'args' => [rand(), $limit - $expected, $userA, null],
                'used' => $limit,
                'user' => $userA,
                'group' => rand(),
                'expected' => 0,
            ],
            'user A of all groups, not applied to user B' => [
                'args' => [rand(), $limit, $userA, null],
                'used' => rand(),
                'user' => $userB,
                'group' => rand(),
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of group A, applied to user A of group A, no used' => [
                'args' => [rand(), $limit, $userA, $groupA],
                'used' => 0,
                'user' => $userA,
                'group' => $groupA,
                'expected' => $limit,
            ],
            'user A of group A, applied to user A of group A, some used' => [
                'args' => [rand(), $limit, $userA, $groupA],
                'used' => $limit - $expected,
                'user' => $userA,
                'group' => $groupA,
                'expected' => $expected,
            ],
            'user A of group A, applied to user A of group A, all used' => [
                'args' => [rand(), $limit, $userA, $groupA],
                'used' => $limit,
                'user' => $userA,
                'group' => $groupA,
                'expected' => 0,
            ],
            'user A of group A, applied to user A of group A, over used' => [
                'args' => [rand(), $limit - $expected, $userA, $groupA],
                'used' => $limit,
                'user' => $userA,
                'group' => $groupA,
                'expected' => 0,
            ],
            'user A of group A, not applied to user A of group B' => [
                'args' => [rand(), $limit, $userA, $groupA],
                'used' => rand(),
                'user' => $userA,
                'group' => $groupB,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of group A, not applied to user B of group A' => [
                'args' => [rand(), $limit, $userA, $groupA],
                'used' => rand(),
                'user' => $userB,
                'group' => $groupA,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'user A of group A, not applied to user B of group B' => [
                'args' => [rand(), $limit, $userA, $groupA],
                'used' => rand(),
                'user' => $userB,
                'group' => $groupB,
                'expected' => QuotaInterface::UNLIMITED,
            ],
        ];
    }
}
