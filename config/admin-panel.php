<?php

use App\Http\Middleware\OwnerOnly;

return [
    'prefix' => 'tools/admin-panel',
    'middleware' => ['web', 'auth', OwnerOnly::class],
    'theme' => ['brand_name' => 'Panjabi · Package Dashboard', 'default_palette' => 'emerald', 'default_mode' => 'light'],
];
