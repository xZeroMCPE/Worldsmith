<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Tests\Domain;

use PHPUnit\Framework\TestCase;
use XZeroMCPE\Worldsmith\Domain\WorldDefinition;

final class WorldDefinitionTest extends TestCase
{
    public function testDefaultsAreSafe(): void
    {
        $world = WorldDefinition::fromArray('creative', []);

        self::assertSame('creative', $world->id);
        self::assertFalse($world->autoload);
        self::assertTrue($world->lazyLoad);
        self::assertSame(0, $world->idleUnloadMinutes);
        self::assertSame('default', $world->profile);
        self::assertFalse($world->maintenance);
    }

    public function testRoundTripPreservesConfiguration(): void
    {
        $world = WorldDefinition::fromArray('arena', [
            'autoload' => true,
            'lazy-load' => false,
            'idle-unload-minutes' => 10,
            'profile' => 'arena',
            'maintenance' => true,
            'access-permission' => 'worldsmith.world.arena',
            'maximum-players' => 16,
            'join-destination' => false,
            'respawn-world' => 'lobby',
        ]);

        self::assertEquals($world, WorldDefinition::fromArray('arena', $world->toArray()));
    }

    public function testInvalidWorldIdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WorldDefinition::fromArray('../world', []);
    }

    public function testWrongTypesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WorldDefinition::fromArray('world', ['autoload' => 'yes']);
    }

    public function testSettingUpdateIsImmutable(): void
    {
        $world = WorldDefinition::fromArray('world', []);
        $updated = $world->with('autoload', true);

        self::assertFalse($world->autoload);
        self::assertTrue($updated->autoload);
    }
}
