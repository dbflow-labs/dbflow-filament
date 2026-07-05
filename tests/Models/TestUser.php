<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Models;

use Illuminate\Database\Eloquent\Model;

final class TestUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
