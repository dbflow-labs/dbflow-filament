<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Models;

use Illuminate\Database\Eloquent\Model;

final class TestWorkflowSubject extends Model
{
    protected $table = 'test_workflow_subjects';

    protected $guarded = [];
}
