<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Models;

use DbflowLabs\Core\Contracts\WorkflowRouteResolvable;
use Illuminate\Database\Eloquent\Model;

final class TestRoutableWorkflowSubject extends Model implements WorkflowRouteResolvable
{
    protected $table = 'test_workflow_subjects';

    protected $guarded = [];

    public function getWorkflowShowUrl(): ?string
    {
        return '/test-workflow-subjects/'.$this->getKey();
    }
}
