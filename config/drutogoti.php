<?php

use App\Http\Middleware\OwnerOnly;

return ['enabled' => false, 'dashboard' => ['enabled' => false, 'middleware' => ['web', 'auth', OwnerOnly::class]], 'watchers' => []];
