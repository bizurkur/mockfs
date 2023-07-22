<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Quota;

use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\SummaryInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\Quota\QuotaManager;
use MockFileSystem\Quota\QuotaManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuotaManagerTest extends TestCase
{
    private QuotaManager $fixture;

    /**
     * @var PartitionInterface&MockObject
     */
    private PartitionInterface $partition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partition = $this->createMock(PartitionInterface::class);

        $this->fixture = new QuotaManager($this->partition);
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(QuotaManagerInterface::class, $this->fixture);
    }

    public function testChildIsPartition(): void
    {
        $child = $this->createMock(PartitionInterface::class);

        $actual = $this->fixture->getFreeDiskSpace($child);

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testNoQuotaSet(): void
    {
        $actual = $this->fixture->getFreeDiskSpace();

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testQuotaCallsAppliesTo(): void
    {
        $user = rand();
        $group = rand();

        $quota = $this->setUpQuota(false);
        $this->setUpConfig($user, $group);

        $quota->expects(self::once())
            ->method('appliesTo')
            ->with($user, $group);

        $this->fixture->getFreeDiskSpace();
    }

    public function testQuotaDoesNotApplyToUser(): void
    {
        $this->setUpQuota(false);

        $actual = $this->fixture->getFreeDiskSpace();

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testPartitionCallsGetSummary(): void
    {
        $user = rand();
        $group = rand();

        $this->setUpQuota();
        $this->setUpConfig($user, $group);

        $this->partition->expects(self::once())
            ->method('getSummary')
            ->with($user, $group);

        $this->fixture->getFreeDiskSpace();
    }

    public function testQuotaCallsGetRemainingFileCount(): void
    {
        $user = rand();
        $group = rand();
        $usedCount = rand();

        $quota = $this->setUpQuota();
        $this->setUpConfig($user, $group);
        $this->setUpSummary(rand(), $usedCount);

        $quota->expects(self::once())
            ->method('getRemainingFileCount')
            ->with($usedCount, $user, $group);

        $this->fixture->getFreeDiskSpace();
    }

    public function testQuotaCallsGetRemainingSize(): void
    {
        $user = rand();
        $group = rand();
        $usedSize = rand();

        $quota = $this->setUpQuota();
        $this->setUpConfig($user, $group);
        $this->setUpSummary($usedSize, rand());

        $quota->expects(self::once())
            ->method('getRemainingSize')
            ->with($usedSize, $user, $group);

        $this->fixture->getFreeDiskSpace();
    }

    public function testGetRemainingSizeIsZero(): void
    {
        $this->setUpQuota(true, 0, rand(1, 999));

        $actual = $this->fixture->getFreeDiskSpace();

        self::assertEquals(0, $actual);
    }

    public function testGetRemainingFileCountIsZero(): void
    {
        $this->setUpQuota(true, rand(1, 999), 0);

        $actual = $this->fixture->getFreeDiskSpace();

        self::assertEquals(0, $actual);
    }

    public function testNoChildReturnsRemainingSize(): void
    {
        $remaining = rand(1, 999);

        $this->setUpQuota(true, $remaining, rand(1, 999));

        $actual = $this->fixture->getFreeDiskSpace();

        self::assertEquals($remaining, $actual);
    }

    /**
     * @dataProvider sampleFileData
     */
    public function testChildIsFile(int $remaining, int $size, int $expected): void
    {
        $this->setUpQuota(true, $remaining, rand(1, 999));

        $child = $this->createConfiguredMock(FileInterface::class, ['getSize' => $size]);

        $actual = $this->fixture->getFreeDiskSpace($child);

        self::assertEquals($expected, $actual);
    }

    public function sampleFileData(): array
    {
        $large = rand(100, 999);
        $small = rand(1, 99);

        return [
            'size less than remaining' => [
                'remaining' => $large,
                'size' => $small,
                'expected' => $large - $small,
            ],
            'size equals remaining' => [
                'remaining' => $large,
                'size' => $large,
                'expected' => 0,
            ],
            'size greater than remaining' => [
                'remaining' => $small,
                'size' => $large,
                'expected' => 0,
            ],
        ];
    }

    /**
     * @dataProvider sampleDirectoryData
     */
    public function testChildIsDirectory(
        int $remainingSize,
        int $remainingFileCount,
        int $size,
        int $fileCount,
        int $expected
    ): void {
        $this->setUpQuota(true, $remainingSize, $remainingFileCount);

        $summary = $this->createSummary($size, $fileCount);
        $child = $this->createConfiguredMock(DirectoryInterface::class, ['getSummary' => $summary]);

        $actual = $this->fixture->getFreeDiskSpace($child);

        self::assertEquals($expected, $actual);
    }

    public function sampleDirectoryData(): array
    {
        $large = rand(100, 999);
        $small = rand(1, 99);

        return [
            'child count greater than remaining' => [
                'remainingSize' => QuotaInterface::UNLIMITED,
                'remainingFileCount' => $small,
                'size' => rand(),
                'fileCount' => $large,
                'expected' => 0,
            ],
            'child count less than remaining, no size limit' => [
                'remainingSize' => QuotaInterface::UNLIMITED,
                'remainingFileCount' => $large,
                'size' => rand(),
                'fileCount' => $small,
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'child count less than remaining, with size limit not exceeded' => [
                'remainingSize' => $large,
                'remainingFileCount' => intval($large / 2),
                'size' => $small,
                'fileCount' => intval($small / 2),
                'expected' => $large - $small,
            ],
            'child count less than remaining, with size limit exceeded' => [
                'remainingSize' => $small,
                'remainingFileCount' => intval($large / 2),
                'size' => $large,
                'fileCount' => intval($small / 2),
                'expected' => 0,
            ],
            'child count unlimited, no size limit' => [
                'remainingSize' => QuotaInterface::UNLIMITED,
                'remainingFileCount' => QuotaInterface::UNLIMITED,
                'size' => rand(),
                'fileCount' => rand(),
                'expected' => QuotaInterface::UNLIMITED,
            ],
            'child count unlimited, with size limit not exceeded' => [
                'remainingSize' => $large,
                'remainingFileCount' => QuotaInterface::UNLIMITED,
                'size' => $small,
                'fileCount' => rand(),
                'expected' => $large - $small,
            ],
            'child count unlimited, with size limit exceeded' => [
                'remainingSize' => $small,
                'remainingFileCount' => QuotaInterface::UNLIMITED,
                'size' => $large,
                'fileCount' => rand(),
                'expected' => 0,
            ],
        ];
    }

    /**
     * @param int $user
     * @param int $group
     *
     * @return ConfigInterface&MockObject
     */
    private function setUpConfig(int $user, int $group): ConfigInterface
    {
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            [
                'getUser' => $user,
                'getGroup' => $group,
            ]
        );
        $this->partition->method('getConfig')->willReturn($config);

        return $config;
    }

    /**
     * @param bool $applies
     * @param int $size
     * @param int $fileCount
     *
     * @return QuotaInterface&MockObject
     */
    private function setUpQuota(
        bool $applies = true,
        int $size = 0,
        int $fileCount = 0
    ): QuotaInterface {
        $quota = $this->createConfiguredMock(
            QuotaInterface::class,
            [
                'appliesTo' => $applies,
                'getRemainingSize' => $size,
                'getRemainingFileCount' => $fileCount,
            ]
        );
        $this->partition->method('getQuota')->willReturn($quota);

        return $quota;
    }

    /**
     * @param int $size
     * @param int $fileCount
     *
     * @return SummaryInterface&MockObject
     */
    private function setUpSummary(int $size, int $fileCount): SummaryInterface
    {
        $summary = $this->createSummary($size, $fileCount);
        $this->partition->method('getSummary')->willReturn($summary);

        return $summary;
    }

    /**
     * @param int $size
     * @param int $fileCount
     *
     * @return SummaryInterface&MockObject
     */
    private function createSummary(int $size, int $fileCount): SummaryInterface
    {
        return $this->createConfiguredMock(
            SummaryInterface::class,
            [
                'getSize' => $size,
                'getFileCount' => $fileCount,
            ]
        );
    }
}
