<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith;

use Bedriox\Api\Plugin\Plugin;
use XZeroMCPE\Worldsmith\Command\WorldCommand;
use XZeroMCPE\Worldsmith\Configuration\SettingsRepository;
use XZeroMCPE\Worldsmith\Message\MessageCatalog;
use XZeroMCPE\Worldsmith\Policy\WorldPolicySubscriber;
use XZeroMCPE\Worldsmith\Support\AuditLogger;
use XZeroMCPE\Worldsmith\World\WorldService;

final class Main extends Plugin
{
    private ?WorldService $worldService = null;

    public function onEnable(): void
    {
        $data = $this->context()->data();
        $data->saveResources();
        $settings = new SettingsRepository($data);
        $messages = new MessageCatalog($data->config('messages.yml'));
        $worldNames = $this->context()->commands()->registerSoftEnum('worlds', $settings->worldIds());
        $audit = new AuditLogger($this->logger(), $data->path(), $settings->settings()->auditEnabled);
        $service = new WorldService(
            $this->context()->server(),
            $this->context()->scheduler(),
            $settings,
            $messages,
            $worldNames,
            $audit,
            $this->logger(),
        );
        $this->worldService = $service;
        $this->context()->events()->registerSubscriber(new WorldPolicySubscriber(
            $settings,
            $service,
            $this->context()->server(),
        ));
        $this->context()->commands()->register(new WorldCommand(
            $service,
            $settings,
            $messages,
            $this->context()->server(),
            $worldNames,
        ));
        $service->start();
        $this->logger()->info('Worldsmith 0.1.0 enabled');
    }

    public function onDisable(): void
    {
        $this->worldService?->shutdown();
        $this->logger()->info('Worldsmith disabled');
    }
}
