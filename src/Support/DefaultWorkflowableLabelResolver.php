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

use DbflowLabs\Core\Contracts\Workflowable;
use DbflowLabs\Filament\Contracts\WorkflowableLabelResolver;
use Illuminate\Database\Eloquent\Model;

final class DefaultWorkflowableLabelResolver implements WorkflowableLabelResolver
{
    public function labelFor(?Model $workflowable): string
    {
        if ($workflowable === null) {
            return '—';
        }

        if ($workflowable instanceof Workflowable) {
            $displayName = trim($workflowable->workflowDisplayName());

            if ($displayName !== '') {
                return $displayName;
            }
        }

        return sprintf(
            '%s #%s',
            class_basename($workflowable),
            (string) $workflowable->getKey(),
        );
    }

    public function morphTypeLabel(?string $workflowableType): string
    {
        if ($workflowableType === null || $workflowableType === '') {
            return '—';
        }

        $classBasename = class_basename($workflowableType);

        return $classBasename !== '' ? $classBasename : '—';
    }

    public function morphIdLabel(?string $workflowableId): string
    {
        if ($workflowableId === null || $workflowableId === '') {
            return '—';
        }

        return $workflowableId;
    }
}
