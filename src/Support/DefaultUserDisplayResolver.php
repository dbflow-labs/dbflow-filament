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

use DbflowLabs\Filament\Contracts\UserDisplayResolver;
use Illuminate\Database\Eloquent\Model;

final class DefaultUserDisplayResolver implements UserDisplayResolver
{
    public function displayName(mixed $user): string
    {
        if (! is_object($user)) {
            return (string) __('dbflow-filament::dbflow-filament.labels.unknown_user');
        }

        $attribute = (string) config('dbflow-filament.user_name_attribute', 'name');

        if ($attribute !== '' && isset($user->{$attribute}) && filled($user->{$attribute})) {
            return (string) $user->{$attribute};
        }

        if (isset($user->name) && filled($user->name)) {
            return (string) $user->name;
        }

        if (isset($user->email) && filled($user->email)) {
            return (string) $user->email;
        }

        if ($user instanceof Model && $user->getKey() !== null) {
            return (string) $user->getKey();
        }

        return (string) __('dbflow-filament::dbflow-filament.labels.unknown_user');
    }
}
