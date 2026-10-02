<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Policy;

use Bedriox\Api\Event\Block\BlockBreakEvent;
use Bedriox\Api\Event\Block\BlockPlaceEvent;
use Bedriox\Api\Event\EventHandler;
use Bedriox\Api\Event\EventPriority;
use Bedriox\Api\Event\Player\PlayerAttackEvent;
use Bedriox\Api\Event\Player\PlayerDamageEvent;
use Bedriox\Api\Event\Player\PlayerDropItemEvent;
use Bedriox\Api\Event\Player\PlayerJoinEvent;
use Bedriox\Api\Event\Player\PlayerMoveEvent;
use Bedriox\Api\Event\Player\PlayerPickupItemEvent;
use Bedriox\Api\Event\Player\PlayerQuitEvent;
use Bedriox\Api\Event\Player\PlayerRespawnEvent;
use Bedriox\Api\Event\Player\PlayerTeleportedEvent;
use Bedriox\Api\Event\World\WeatherChangeEvent;
use Bedriox\Api\Event\World\WorldLoadedEvent;
use Bedriox\Api\Player\GameMode;
use Bedriox\Api\Server;
use Bedriox\Api\World\WeatherState;
use Bedriox\Api\World\WeatherType;
use XZeroMCPE\Worldsmith\Configuration\SettingsRepository;
use XZeroMCPE\Worldsmith\World\WorldService;

final readonly class WorldPolicySubscriber
{
    public function __construct(
        private SettingsRepository $settings,
        private WorldService $worlds,
        private Server $server,
    ) {}

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $id = $event->player->position->world?->id();
        if ($id !== null && !$this->settings->profileFor($id)->allowBlockBreaking) {
            $event->cancel();
        }
    }

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $id = $event->player->position->world?->id();
        if ($id !== null && !$this->settings->profileFor($id)->allowBuilding) {
            $event->cancel();
        }
    }

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onAttack(PlayerAttackEvent $event): void
    {
        $id = $event->attacker->position->world?->id();
        if ($id !== null && !$this->settings->profileFor($id)->allowPvp) {
            $event->cancel();
        }
    }

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onDrop(PlayerDropItemEvent $event): void
    {
        $id = $event->player->position->world?->id();
        if ($id !== null && !$this->settings->profileFor($id)->allowItemDropping) {
            $event->cancel();
        }
    }

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onPickup(PlayerPickupItemEvent $event): void
    {
        $id = $event->player->position->world?->id();
        if ($id !== null && !$this->settings->profileFor($id)->allowItemPickup) {
            $event->cancel();
        }
    }

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onWeather(WeatherChangeEvent $event): void
    {
        $profile = $this->settings->profileFor($event->world->id());
        if (!$profile->lockWeather) {
            return;
        }
        $type = WeatherType::tryFrom($profile->weather) ?? WeatherType::CLEAR;
        if ($event->weather()->type !== $type) {
            $event->setWeather(new WeatherState($type, WeatherState::MAXIMUM_DURATION_TICKS));
        }
    }

    #[EventHandler]
    public function onTeleported(PlayerTeleportedEvent $event): void
    {
        $this->worlds->confirmTeleport($event->player);
        $id = $event->destination->world?->id();
        if ($id === null) {
            return;
        }
        $profile = $this->settings->profileFor($id);
        $gameMode = GameMode::tryFrom($profile->gameMode);
        if ($gameMode !== null && $event->player->getGameMode() !== $gameMode) {
            $event->player->setGameMode($gameMode);
        }
    }

    #[EventHandler]
    public function onWorldLoaded(WorldLoadedEvent $event): void
    {
        $this->worlds->worldLoaded($event->world);
    }

    #[EventHandler]
    public function onJoin(PlayerJoinEvent $event): void
    {
        $configuration = $this->settings->settings();
        if ($configuration->routeEveryJoin && $configuration->joinWorld !== '') {
            $this->worlds->routePlayer($event->player, $configuration->joinWorld);
        }
    }

    #[EventHandler(priority: EventPriority::HIGH)]
    public function onRespawn(PlayerRespawnEvent $event): void
    {
        $currentId = $event->player->position->world?->id() ?? '';
        $target = $this->settings->world($currentId)?->respawnWorld;
        $target = $target !== null && $target !== '' ? $target : $this->settings->settings()->defaultRespawnWorld;
        if ($target === '') {
            return;
        }
        $world = $this->server->getWorldManager()->get($target);
        if ($world !== null) {
            $event->setPosition($this->server->getWorldManager()->info($world)->spawn);
        }
    }

    #[EventHandler(priority: EventPriority::HIGHEST)]
    public function onMove(PlayerMoveEvent $event): void
    {
        if (!$this->settings->settings()->cancelOnMove || !$this->worlds->hasPending($event->player)) {
            return;
        }
        $origin = $this->worlds->warmupOrigin($event->player);
        if ($origin !== null && ($origin->world?->id() !== $event->to->world?->id()
            || abs($origin->x - $event->to->x) > 0.1
            || abs($origin->y - $event->to->y) > 0.1
            || abs($origin->z - $event->to->z) > 0.1)) {
            $this->worlds->cancelPending($event->player);
        }
    }

    #[EventHandler(priority: EventPriority::HIGHEST)]
    public function onDamage(PlayerDamageEvent $event): void
    {
        if ($this->settings->settings()->cancelOnDamage) {
            $this->worlds->cancelPending($event->player);
        }
    }

    #[EventHandler]
    public function onQuit(PlayerQuitEvent $event): void
    {
        $this->worlds->discardPending($event->player);
    }
}
