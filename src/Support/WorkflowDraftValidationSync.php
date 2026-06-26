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

use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Core\Validation\WorkflowDefinitionValidationResult;
use DbflowLabs\Core\Validation\WorkflowDefinitionValidator;

final class WorkflowDraftValidationSync
{
    public function __construct(
        private readonly WorkflowDefinitionValidator $validator,
    ) {}

    public function syncStoredDraft(Workflow $workflow): WorkflowDefinitionValidationResult
    {
        $result = $this->validator->validate($workflow->draftDefinition());

        $this->applyResult($workflow, $result);

        return $result;
    }

    public function applyResult(Workflow $workflow, WorkflowDefinitionValidationResult $result): Workflow
    {
        $workflow->draft_validation_errors = $result->isValid()
            ? null
            : $result->errors();
        $workflow->draft_validation_warnings = $result->warnings() !== []
            ? $result->warnings()
            : null;
        $workflow->save();

        return $workflow->refresh();
    }
}
