<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2x0' => true,
        'declare_strict_types' => true,
        'ordered_imports' => true,
    ])
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
