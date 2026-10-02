<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Tests;

use PHPUnit\Framework\TestCase;

final class RepositoryPolicyTest extends TestCase
{
    public function testProductionNeverImportsBedrioxInternals(): void
    {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertStringNotContainsString('Bedriox\\Server', $contents, $file->getPathname());
        }
    }

    public function testManifestTargetsApiPointFour(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__) . '/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame('Worldsmith', $manifest['name']);
        self::assertSame('^0.4', $manifest['api']);
        self::assertSame(['xZeroMCPE'], $manifest['authors']);
    }

    public function testEveryDefaultResourceExists(): void
    {
        $resources = dirname(__DIR__) . '/resources';
        foreach (['config.yml', 'messages.yml', 'profiles.yml', 'worlds.yml'] as $name) {
            self::assertFileExists($resources . '/' . $name);
        }
    }

    public function testTeleportLifecycleMessagesExist(): void
    {
        $messages = (string) file_get_contents(dirname(__DIR__) . '/resources/messages.yml');

        foreach (['world-loading:', 'teleport-started:', 'teleported:', 'teleport-failed:', 'teleport-in-progress:'] as $key) {
            self::assertStringContainsString($key, $messages);
        }
    }

    public function testTeleportVerificationDoesNotResubmitDuringWorldTransfer(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__) . '/src/World/WorldService.php');

        self::assertSame(1, substr_count($service, '$player->teleport($request->destination);'));
        self::assertStringNotContainsString('$request->attempts', $service);
        self::assertStringContainsString('TELEPORT_CONFIRMATION_TIMEOUT_TICKS = 200', $service);
    }

    public function testLazyTeleportResumesFromWorldLoadedEvent(): void
    {
        $subscriber = (string) file_get_contents(dirname(__DIR__) . '/src/Policy/WorldPolicySubscriber.php');
        $service = (string) file_get_contents(dirname(__DIR__) . '/src/World/WorldService.php');

        self::assertStringContainsString('WorldLoadedEvent', $subscriber);
        self::assertStringContainsString('worldLoaded($event->world)', $subscriber);
        self::assertStringContainsString('getPlayerByUuid($uuid)', $service);
    }
}
