<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

/** Applies to credentials, sessions, remember-me and the password broker. */
class DealerUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->loginAccounts();
    }
}
