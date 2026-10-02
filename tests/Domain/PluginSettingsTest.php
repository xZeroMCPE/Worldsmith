<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Tests\Domain;

use PHPUnit\Framework\TestCase;
use XZeroMCPE\Worldsmith\Domain\PluginSettings;

final class PluginSettingsTest extends TestCase
{
    public function testConfigurationIsParsed(): void
    {
        $settings = PluginSettings::fromArray([
            'teleport' => ['warmup-seconds' => 3, 'cooldown-seconds' => 10],
            'lifecycle' => ['idle-check-seconds' => 30, 'save-interval-minutes' => 15],
            'routing' => ['join-world' => 'lobby', 'route-every-join' => true],
            'audit' => ['enabled' => false],
        ]);

        self::assertSame(3, $settings->warmupSeconds);
        self::assertSame(10, $settings->cooldownSeconds);
        self::assertSame('lobby', $settings->joinWorld);
        self::assertTrue($settings->routeEveryJoin);
        self::assertFalse($settings->auditEnabled);
    }

    public function testOutOfRangeWarmupIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PluginSettings::fromArray(['teleport' => ['warmup-seconds' => 301]]);
    }

    public function testListSectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PluginSettings::fromArray(['teleport' => ['wrong']]);
    }
}
