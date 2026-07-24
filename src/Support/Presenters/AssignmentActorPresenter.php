<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Support\Presenters;

use DbflowLabs\Core\Models\WorkflowTaskAssignment;
use DbflowLabs\Core\Support\DbflowAuth;
use DbflowLabs\Filament\Contracts\UserDisplayResolver;

final class AssignmentActorPresenter
{
    /**
     * @var array<string, string>
     */
    private array $labelCache = [];

    public function __construct(
        private readonly UserDisplayResolver $userDisplayResolver,
    ) {}

    public function originalActorLabel(WorkflowTaskAssignment $assignment): string
    {
        return $this->labelForUserId($assignment->originalAssigneeUserId());
    }

    public function effectiveActorLabel(WorkflowTaskAssignment $assignment): string
    {
        return $this->labelForUserId($assignment->effectiveAssigneeUserId());
    }

    /**
     * @return array{
     *     original: string,
     *     effective: string,
     *     show_both: bool,
     *     combined: string,
     * }
     */
    public function displayActors(WorkflowTaskAssignment $assignment): array
    {
        $original = $this->originalActorLabel($assignment);
        $effective = $this->effectiveActorLabel($assignment);
        $showBoth = $original !== $effective;

        return [
            'original' => $original,
            'effective' => $effective,
            'show_both' => $showBoth,
            'combined' => $showBoth ? $effective : $original,
        ];
    }

    public function labelForUserId(int|string|null $userId): string
    {
        if ($userId === null || $userId === '') {
            return (string) __('dbflow-filament::dbflow-filament.labels.unknown_user');
        }

        $cacheKey = (string) $userId;

        if (array_key_exists($cacheKey, $this->labelCache)) {
            return $this->labelCache[$cacheKey];
        }

        $userModelClass = DbflowAuth::userModelClass();
        $user = $userModelClass::query()->find($userId);

        return $this->labelCache[$cacheKey] = $this->userDisplayResolver->displayName($user);
    }
}
