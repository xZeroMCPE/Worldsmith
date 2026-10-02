<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Tests\Domain;

use PHPUnit\Framework\TestCase;
use XZeroMCPE\Worldsmith\Domain\Profile;

final class ProfileTest extends TestCase
{
    public function testDefaultProfileAllowsNormalGameplay(): void
    {
        $profile = Profile::fromArray('default', []);

        self::assertTrue($profile->allowBuilding);
        self::assertTrue($profile->allowBlockBreaking);
        self::assertTrue($profile->allowPvp);
        self::assertFalse($profile->lockWeather);
    }

    public function testInvalidWeatherIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Profile::fromArray('bad', ['weather' => 'snow']);
    }
}
