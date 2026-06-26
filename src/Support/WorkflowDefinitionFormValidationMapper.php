<?php

/**
 * This file is part of the dbflowlabs/filament package.
 *
 * Copyright (c) 2026 Baron Wang <hello@dbflow.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 * @link    https://dbflow.dev
 * @see     https://github.com/dbflow-labs/dbflow-filament
 */

declare(strict_types=1);

namespace DbflowLabs\Filament\Support;

use DbflowLabs\Core\Validation\WorkflowDefinitionValidationResult;
use Illuminate\Validation\ValidationException;

final class WorkflowDefinitionFormValidationMapper
{
    /**
     * @param  list<array{path: string, code: string, message: string}>  $errors
     * @return array<string, list<string>>
     */
    public static function messagesForDefinitionJson(array $errors): array
    {
        $messages = [];

        foreach ($errors as $error) {
            $path = self::definitionJsonFieldPath($error['path'] ?? '');
            $messages[$path][] = (string) ($error['message'] ?? 'Invalid workflow definition.');
        }

        return $messages;
    }

    public static function toValidationException(WorkflowDefinitionValidationResult $result): ValidationException
    {
        return ValidationException::withMessages(
            self::messagesForDefinitionJson($result->errors()),
        );
    }

    public static function definitionJsonFieldPath(string $errorPath): string
    {
        if ($errorPath === '' || $errorPath === 'definition') {
            return 'definition_json';
        }

        return 'definition_json';
    }
}
