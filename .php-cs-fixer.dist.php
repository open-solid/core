<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
;

return new PhpCsFixer\Config()
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        // Disable for properties due to PHP 8.4 asymmetric visibility shorthand (private(set))
        // See: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/8419
        'visibility_required' => ['elements' => ['method', 'const']],
        'modifier_keywords' => false,
        // Keep /** @var */ style for PHPStan inline type hints
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
;
