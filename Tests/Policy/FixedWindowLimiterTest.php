<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Tests\Resources\DummyWindow;
use Symfony\Component\RateLimiter\Util\TimeUtil;

/**
 * @group time-sensitive
 */
class FixedWindowLimiterTest extends TestCase
{
    private $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();

        ClockMock::register(InMemoryStorage::class);
    }

    public function testConsume()
    {
        $limiter = $this->createLimiter();

        // fill 9 tokens in 45 seconds
        for ($i = 0; $i < 9; ++$i) {
            $limiter->consume();
            sleep(5);
        }

        $rateLimit = $limiter->consume();
        $this->assertSame(10, $rateLimit->getLimit());
        $this->assertTrue($rateLimit->isAccepted());
        $rateLimit = $limiter->consume();
        $this->assertFalse($rateLimit->isAccepted());
        $this->assertSame(10, $rateLimit->getLimit());
    }

    /**
     * @dataProvider provideConsumeOutsideInterval
     */
    public function testConsumeOutsideInterval(string $dateIntervalString)
    {
        $limiter = $this->createLimiter($dateIntervalString);

        // start window...
        $limiter->consume();
        // ...add a max burst, 5 seconds before the end of the window...
        sleep(TimeUtil::dateIntervalToSeconds(new \DateInterval($dateIntervalString)) - 5);
        $limiter->consume(9);
        // ...try bursting again at the start of the next window, 10 seconds later
        sleep(10);
        $rateLimit = $limiter->consume(10);
        $this->assertEquals(0, $rateLimit->getRemainingTokens());
        $this->assertTrue($rateLimit->isAccepted());
    }

    public function testWrongWindowFromCache()
    {
        $this->storage->save(new DummyWindow());
        $limiter = $this->createLimiter();
        $rateLimit = $limiter->consume();
        $this->assertTrue($rateLimit->isAccepted());
        $this->assertEquals(9, $rateLimit->getRemainingTokens());
    }

    private function createLimiter(string $dateIntervalString = 'PT1M'): FixedWindowLimiter
    {
        return new FixedWindowLimiter('test', 10, new \DateInterval($dateIntervalString), $this->storage);
    }

    public function provideConsumeOutsideInterval(): \Generator
    {
        yield ['PT15S'];

        yield ['PT1M'];

        yield ['PT1H'];

        yield ['P1M'];

        yield ['P1Y'];
    }
}
