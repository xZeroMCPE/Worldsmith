<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Command;

use Bedriox\Api\Command\AbstractCommand;
use Bedriox\Api\Command\CommandArguments;
use Bedriox\Api\Command\CommandContext;
use Bedriox\Api\Command\CommandOverload;
use Bedriox\Api\Command\CommandParameter;
use Bedriox\Api\Command\CommandResult;
use Bedriox\Api\Command\CommandSoftEnum;
use Bedriox\Api\Command\PlayerCommandSender;
use Bedriox\Api\Server;
use XZeroMCPE\Worldsmith\Configuration\SettingsRepository;
use XZeroMCPE\Worldsmith\Message\MessageCatalog;
use XZeroMCPE\Worldsmith\World\WorldService;

final class WorldCommand extends AbstractCommand
{
    public function __construct(
        private readonly WorldService $service,
        private readonly SettingsRepository $settings,
        private readonly MessageCatalog $messages,
        private readonly Server $server,
        private readonly CommandSoftEnum $worldNames,
    ) {
        parent::__construct('world', 'Manage Bedriox worlds with Worldsmith.');
    }

    protected function aliases(): array
    {
        return ['ws'];
    }

    public function defineArguments(): CommandArguments
    {
        $world = fn(string $name = 'world'): CommandParameter => CommandParameter::softEnum($name, $this->worldNames);
        $arguments = CommandArguments::create();

        return $arguments
            ->addOverload(CommandOverload::create())
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('help'))->addArgument(CommandParameter::integer('page')->minimum(1)->optional(1)))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('version')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('list'))->addArgument(CommandParameter::choice('filter', ['loaded', 'managed', 'all'])->optional('all')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('info'))->addArgument($world()->optional('')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('status'))->addArgument($world()->optional('')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('create'))->addArgument(CommandParameter::string('worldId'))->addArgument(CommandParameter::string('generator')->optional('default'))->addArgument(CommandParameter::integer('seed')->optional(0))->addArgument(CommandParameter::choice('difficulty', ['peaceful', 'easy', 'normal', 'hard'])->optional('normal')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('load'))->addArgument($world()))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('save'))->addArgument($world()))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('save-all')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('unload'))->addArgument($world())->addArgument(CommandParameter::boolean('saveWorld')->optional(true)))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('unload'))->addArgument(CommandParameter::literal('--force', 'force'))->addArgument($world())->addArgument(CommandParameter::boolean('saveWorld')->optional(true)))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('teleport'))->addArgument($world()))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('teleport'))->addArgument(CommandParameter::onlinePlayer('player'))->addArgument($world()))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('spawn'))->addArgument($world()->optional('')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('back')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('reload')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('maintenance'))->addArgument($world())->addArgument(CommandParameter::boolean('enabled')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('autoload'))->addArgument($world())->addArgument(CommandParameter::boolean('enabled')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('lazyload'))->addArgument($world())->addArgument(CommandParameter::boolean('enabled')))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('idleunload'))->addArgument($world())->addArgument(CommandParameter::integer('minutes')->minimum(0)->maximum(10080)))
            ->addOverload(CommandOverload::create()->addArgument(CommandParameter::literal('profile'))->addArgument($world())->addArgument(CommandParameter::string('profileName')));
    }

    public function execute(CommandContext $context): CommandResult
    {
        $values = $context->values();
        $sender = $context->sender();
        if ($values->all() === [] || $values->has('help')) {
            return $this->help($context);
        }
        if ($values->has('version')) {
            $sender->sendMessage($this->messages->heading('Worldsmith 0.1.0 by xZeroMCPE | Bedriox API ^0.4'));
            $sender->sendMessage($this->messages->detail('https://github.com/xZeroMCPE/Worldsmith'));

            return $this->success();
        }
        if ($values->has('list')) {
            if (!$sender->hasPermission('worldsmith.command.list')) {
                return $this->denied();
            }
            $filter = $values->choice('filter');
            $loaded = array_map(static fn($world): string => $world->id(), $this->service->loaded());
            $managed = $this->settings->worldIds();
            $ids = match ($filter) {
                'loaded' => $loaded,
                'managed' => $managed,
                default => array_values(array_unique([...$loaded, ...$managed])),
            };
            sort($ids, SORT_STRING);
            $sender->sendMessage($this->messages->info('Worlds (' . count($ids) . '): ' . ($ids === [] ? 'none' : implode(', ', $ids))));

            return $this->success();
        }
        if ($values->has('info')) {
            if (!$sender->hasPermission('worldsmith.command.info')) {
                return $this->denied();
            }
            $id = $values->string('world');
            if ($id === '' && $sender instanceof PlayerCommandSender) {
                $id = $sender->player()->position->world?->id() ?? '';
            }
            $world = $this->server->getWorldManager()->get($id);
            if ($world === null) {
                return $this->failure($this->messages->get('world-not-loaded', ['world' => $id]));
            }
            $info = $this->server->getWorldManager()->info($world);
            $definition = $this->settings->world($id);
            $sender->sendMessage($this->messages->heading("{$info->displayName} ({$world->id()}) - {$this->service->status($id)}"));
            $sender->sendMessage($this->messages->detail("Generator: {$info->generator}; seed: {$info->seed}; difficulty: {$info->difficulty->value}"));
            $sender->sendMessage($this->messages->detail("Players: {$info->playerCount}; time: {$info->time}; weather: {$world->getWeather()->type->value}"));
            $sender->sendMessage($this->messages->detail("Spawn: {$info->spawn->x}, {$info->spawn->y}, {$info->spawn->z}"));
            $sender->sendMessage($this->messages->detail('Managed: ' . ($definition === null ? 'no' : "yes; profile={$definition->profile}; autoload=" . ($definition->autoload ? 'true' : 'false'))));

            return $this->success();
        }
        if ($values->has('status')) {
            if (!$sender->hasPermission('worldsmith.command.status')) {
                return $this->denied();
            }
            $id = $values->string('world');
            if ($id !== '') {
                $sender->sendMessage($this->messages->info("{$id}: {$this->service->status($id)}"));
            } else {
                foreach ($this->service->loaded() as $world) {
                    $sender->sendMessage($this->messages->info("{$world->id()}: {$this->service->status($world->id())}"));
                }
            }

            return $this->success();
        }
        if ($values->has('create')) {
            if (!$sender->hasPermission('worldsmith.command.create')) {
                return $this->denied();
            }
            $this->service->create($sender, $values->string('worldId'), $values->string('generator'), $values->integer('seed'), $values->choice('difficulty'));

            return $this->success();
        }
        if ($values->has('load')) {
            if (!$sender->hasPermission('worldsmith.command.load')) {
                return $this->denied();
            }
            $this->service->load($sender, $values->string('world'));

            return $this->success();
        }
        if ($values->has('save_all')) {
            if (!$sender->hasPermission('worldsmith.command.saveall')) {
                return $this->denied();
            }
            $this->service->saveAll($sender);

            return $this->success();
        }
        if ($values->has('save')) {
            if (!$sender->hasPermission('worldsmith.command.save')) {
                return $this->denied();
            }
            $this->service->save($sender, $values->string('world'));

            return $this->success();
        }
        if ($values->has('unload')) {
            $force = $values->has('force');
            if (!$sender->hasPermission($force ? 'worldsmith.command.unload.force' : 'worldsmith.command.unload')) {
                return $this->denied();
            }
            $this->service->unload($sender, $values->string('world'), $values->boolean('saveWorld'), $force);

            return $this->success();
        }
        if ($values->has('teleport') || $values->has('spawn')) {
            $other = $values->has('player');
            if (!$sender->hasPermission($other ? 'worldsmith.command.teleport.other' : 'worldsmith.command.teleport')) {
                return $this->denied();
            }
            if ($other) {
                $player = $values->player('player');
            } elseif ($sender instanceof PlayerCommandSender) {
                $player = $sender->player();
            } else {
                return $this->failure($this->messages->get('player-only'));
            }
            $id = $values->string('world');
            if ($id === '') {
                $id = $player->position->world?->id() ?? $this->server->getWorldManager()->getDefault()->id();
            }
            $this->service->teleport($sender, $player, $id);

            return $this->success();
        }
        if ($values->has('back')) {
            if (!$sender->hasPermission('worldsmith.command.back')) {
                return $this->denied();
            }
            if (!$sender instanceof PlayerCommandSender) {
                return $this->failure($this->messages->get('player-only'));
            }
            $this->service->back($sender, $sender->player());

            return $this->success();
        }
        if ($values->has('reload')) {
            if (!$sender->hasPermission('worldsmith.command.reload')) {
                return $this->denied();
            }
            $this->service->reload();

            return $this->success($this->messages->get('reloaded'));
        }
        foreach (['maintenance', 'autoload', 'lazyload', 'idleunload', 'profile'] as $setting) {
            if (!$values->has($setting)) {
                continue;
            }
            if (!$sender->hasPermission('worldsmith.command.configure')) {
                return $this->denied();
            }
            $key = match ($setting) {
                'lazyload' => 'lazy-load',
                'idleunload' => 'idle-unload-minutes',
                'profile' => 'profile',
                default => $setting,
            };
            $value = match ($setting) {
                'idleunload' => $values->integer('minutes'),
                'profile' => $values->string('profileName'),
                default => $values->boolean('enabled'),
            };
            $id = $values->string('world');
            $this->service->updateSetting($id, $key, $value);

            return $this->success($this->messages->get('saved-setting', ['setting' => $key, 'world' => $id]));
        }

        return $this->failure($this->messages->error('Unsupported Worldsmith command form.'));
    }

    private function help(CommandContext $context): CommandResult
    {
        $sender = $context->sender();
        $sender->sendMessage($this->messages->heading('Worldsmith commands:'));
        $commands = [
            'worldsmith.command.list' => '/world list [loaded|managed|all]',
            'worldsmith.command.info' => '/world info [world]',
            'worldsmith.command.teleport' => '/world teleport <world> | /world back',
            'worldsmith.command.create' => '/world create <id> [generator] [seed] [difficulty]',
            'worldsmith.command.load' => '/world load <world>',
            'worldsmith.command.save' => '/world save <world> | /world save-all',
            'worldsmith.command.unload' => '/world unload <world> [save]',
            'worldsmith.command.configure' => '/world maintenance|autoload|lazyload|idleunload|profile ...',
        ];
        foreach ($commands as $permission => $line) {
            if ($sender->hasPermission($permission)) {
                $sender->sendMessage($this->messages->info($line));
            }
        }
        $sender->sendMessage($this->messages->info('/world version'));

        return $this->success();
    }

    private function denied(): CommandResult
    {
        return $this->failure($this->messages->get('no-permission'));
    }
}
