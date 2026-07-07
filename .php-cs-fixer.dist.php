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
        'blank_line_after_opening_tag' => true,
        'blank_line_after_namespace' => true,
        'blank_lines_before_namespace' => true,
        'single_line_after_imports' => true,
        'no_blank_lines_after_class_opening' => true,
        'single_blank_line_at_eof' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'curly_brace_block',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
                'return',
                'continue',
                'break',
                'case',
                'default',
            ],
        ],
    ])
    ->setFinder($finder);