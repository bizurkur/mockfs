<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Quota;

use MockFileSystem\Quota\Collection;
use MockFileSystem\Quota\QuotaInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testInstanceOf(): void
    {
        $fixture = new Collection();

        self::assertInstanceOf(QuotaInterface::class, $fixture);
    }

    public function testAppliesToWhenEmpty(): void
    {
        $fixture = new Collection();

        $actual = $fixture->appliesTo(rand(), rand());

        self::assertFalse($actual);
    }

    public function testGetRemainingSizeWhenEmpty(): void
    {
        $fixture = new Collection();

        $actual = $fixture->getRemainingSize(rand(), rand(), rand());

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testGetRemainingFileCountWhenEmpty(): void
    {
        $fixture = new Collection();

        $actual = $fixture->getRemainingFileCount(rand(), rand(), rand());

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testGetQuotas(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $actual = $fixture->getQuotas();

        self::assertEquals([$quotaA, $quotaB], $actual);
    }

    public function testGetQuotasWhenEmpty(): void
    {
        $fixture = new Collection();

        $actual = $fixture->getQuotas();

        self::assertEquals([], $actual);
    }

    public function testAppliesToCallsAppliesTo(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $user = rand();
        $group = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('appliesTo')
            ->with($user, $group);

        $quotaB->expects(self::once())
            ->method('appliesTo')
            ->with($user, $group);

        $fixture->appliesTo($user, $group);
    }

    public function testAppliesToFirstResponseTrue(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('appliesTo')
            ->willReturn(true);

        $quotaB->expects(self::never())->method('appliesTo');

        $actual = $fixture->appliesTo(rand(), rand());

        self::assertTrue($actual);
    }

    public function testAppliesToSecondResponseTrue(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('appliesTo')
            ->willReturn(false);

        $quotaB->expects(self::once())
            ->method('appliesTo')
            ->willReturn(true);

        $actual = $fixture->appliesTo(rand(), rand());

        self::assertTrue($actual);
    }

    public function testAppliesToResponseFalse(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->method('appliesTo')->willReturn(false);
        $quotaB->method('appliesTo')->willReturn(false);

        $actual = $fixture->appliesTo(rand(), rand());

        self::assertFalse($actual);
    }

    public function testGetRemainingSizeCallsGetRemainingSize(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $used = rand();
        $user = rand();
        $group = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingSize')
            ->with($used, $user, $group)
            ->willReturn(QuotaInterface::UNLIMITED);

        $quotaB->expects(self::once())
            ->method('getRemainingSize')
            ->with($used, $user, $group)
            ->willReturn(QuotaInterface::UNLIMITED);

        $fixture->getRemainingSize($used, $user, $group);
    }

    public function testGetRemainingSizeFirstResponseUsed(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $remaining = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingSize')
            ->willReturn($remaining);

        $quotaB->expects(self::never())->method('getRemainingSize');

        $actual = $fixture->getRemainingSize(rand(), rand(), rand());

        self::assertEquals($remaining, $actual);
    }

    public function testGetRemainingSizeSecondResponseUsed(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $remaining = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingSize')
            ->willReturn(QuotaInterface::UNLIMITED);

        $quotaB->expects(self::once())
            ->method('getRemainingSize')
            ->willReturn($remaining);

        $actual = $fixture->getRemainingSize(rand(), rand(), rand());

        self::assertEquals($remaining, $actual);
    }

    public function testGetRemainingSizeUnlimited(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingSize')
            ->willReturn(QuotaInterface::UNLIMITED);

        $quotaB->expects(self::once())
            ->method('getRemainingSize')
            ->willReturn(QuotaInterface::UNLIMITED);

        $actual = $fixture->getRemainingSize(rand(), rand(), rand());

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testGetRemainingFileCountCallsGetRemainingFileCount(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $used = rand();
        $user = rand();
        $group = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingFileCount')
            ->with($used, $user, $group)
            ->willReturn(QuotaInterface::UNLIMITED);

        $quotaB->expects(self::once())
            ->method('getRemainingFileCount')
            ->with($used, $user, $group)
            ->willReturn(QuotaInterface::UNLIMITED);

        $fixture->getRemainingFileCount($used, $user, $group);
    }

    public function testGetRemainingFileCountFirstResponseUsed(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $remaining = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingFileCount')
            ->willReturn($remaining);

        $quotaB->expects(self::never())->method('getRemainingFileCount');

        $actual = $fixture->getRemainingFileCount(rand(), rand(), rand());

        self::assertEquals($remaining, $actual);
    }

    public function testGetRemainingFileCountSecondResponseUsed(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();
        $remaining = rand();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingFileCount')
            ->willReturn(QuotaInterface::UNLIMITED);

        $quotaB->expects(self::once())
            ->method('getRemainingFileCount')
            ->willReturn($remaining);

        $actual = $fixture->getRemainingFileCount(rand(), rand(), rand());

        self::assertEquals($remaining, $actual);
    }

    public function testGetRemainingFileCountUnlimited(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        $quotaA->expects(self::once())
            ->method('getRemainingFileCount')
            ->willReturn(QuotaInterface::UNLIMITED);

        $quotaB->expects(self::once())
            ->method('getRemainingFileCount')
            ->willReturn(QuotaInterface::UNLIMITED);

        $actual = $fixture->getRemainingFileCount(rand(), rand(), rand());

        self::assertEquals(QuotaInterface::UNLIMITED, $actual);
    }

    public function testCount(): void
    {
        $quotaA = $this->createQuota();
        $quotaB = $this->createQuota();

        $fixture = new Collection([$quotaA]);
        $fixture->addQuota($quotaB);

        self::assertEquals(2, $fixture->count());
    }

    public function testCountEmpty(): void
    {
        $fixture = new Collection();

        self::assertEquals(0, $fixture->count());
    }

    /**
     * @return QuotaInterface&MockObject
     */
    private function createQuota(): QuotaInterface
    {
        return $this->createMock(QuotaInterface::class);
    }
}
