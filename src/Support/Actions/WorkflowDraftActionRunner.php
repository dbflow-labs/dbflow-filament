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

namespace DbflowLabs\Filament\Support\Actions;

use DbflowLabs\Core\Actions\PublishWorkflowDraft;
use DbflowLabs\Core\Exceptions\InvalidWorkflowDefinitionException;
use DbflowLabs\Core\Models\Workflow;
use DbflowLabs\Filament\Support\WorkflowDraftValidationSync;

final class WorkflowDraftActionRunner
{
    public function __construct(
        private readonly WorkflowDraftValidationSync $validationSync,
        private readonly PublishWorkflowDraft $publishWorkflowDraft,
    ) {}

    public function validateDraft(Workflow $workflow): WorkflowDefinitionActionResult
    {
        if (! $workflow->hasDraft()) {
            return WorkflowDefinitionActionResult::danger(
                'dbflow-filament::dbflow-filament.notifications.definitions.no_draft_available',
            );
        }

        $result = $this->validationSync->syncStoredDraft($workflow);

        if ($result->isValid()) {
            if ($result->warnings() !== []) {
                return WorkflowDefinitionActionResult::success(
                    'dbflow-filament::dbflow-filament.notifications.definitions.validation_passed',
                    'dbflow-filament::dbflow-filament.notifications.definitions.validation_warning_count',
                    ['count' => count($result->warnings())],
                );
            }

            return WorkflowDefinitionActionResult::success(
                'dbflow-filament::dbflow-filament.notifications.definitions.validation_passed',
            );
        }

        return WorkflowDefinitionActionResult::warning(
            'dbflow-filament::dbflow-filament.notifications.definitions.validation_failed',
            'dbflow-filament::dbflow-filament.notifications.definitions.validation_error_count',
            ['count' => count($result->errors())],
        );
    }

    public function publishDraft(Workflow $workflow, int|string|null $publishedBy = null): WorkflowDefinitionActionResult
    {
        if (! $workflow->hasDraft()) {
            return WorkflowDefinitionActionResult::danger(
                'dbflow-filament::dbflow-filament.notifications.definitions.no_draft_available',
            );
        }

        try {
            $version = $this->publishWorkflowDraft->handle($workflow, $publishedBy);

            return WorkflowDefinitionActionResult::published($version);
        } catch (InvalidWorkflowDefinitionException $exception) {
            $validationResult = $exception->validationResult();

            if ($validationResult !== null) {
                $this->validationSync->applyResult($workflow, $validationResult);

                return WorkflowDefinitionActionResult::danger(
                    'dbflow-filament::dbflow-filament.notifications.definitions.draft_publish_failed',
                    'dbflow-filament::dbflow-filament.notifications.definitions.validation_error_count',
                    ['count' => count($validationResult->errors())],
                );
            }

            return WorkflowDefinitionActionResult::danger(
                'dbflow-filament::dbflow-filament.notifications.definitions.draft_publish_failed',
                bodyKey: null,
            );
        }
    }
}
