<?php

declare(strict_types=1);

$header = <<<'EOF'
This file is part of the dbflowlabs/filament package.

Copyright (c) 2026 Baron Wang <hello@dbflow.dev>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.

@license MIT
@link    https://dbflow.dev
@see     https://github.com/dbflow-labs/dbflow-filament
EOF;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        'header_comment' => [
            'header' => $header,
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'both',
        ],
    ])
    ->setFinder($finder);