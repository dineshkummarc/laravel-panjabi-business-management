<?php

return [
    'demo' => env('DEMO_MODE', false),
    'ai_live' => env('AI_LIVE_ENABLED', false),
    'modules' => [
        'inventory' => ['name' => 'Inventory', 'dependencies' => []],
        'storefront' => ['name' => 'Online storefront', 'dependencies' => ['inventory', 'orders']],
        'orders' => ['name' => 'Order management', 'dependencies' => ['inventory', 'accounting']],
        'courier' => ['name' => 'Courier & COD', 'dependencies' => ['orders']],
        'pos' => ['name' => 'Point of sale', 'dependencies' => ['orders']],
        'purchasing' => ['name' => 'Purchases & suppliers', 'dependencies' => ['inventory', 'accounting']],
        'wholesale' => ['name' => 'Wholesale', 'dependencies' => ['orders']],
        'tailoring' => ['name' => 'Tailoring studio', 'dependencies' => []],
        'production' => ['name' => 'Manufacturing', 'dependencies' => ['inventory']],
        'accounting' => ['name' => 'Accounting', 'dependencies' => []],
        'hr' => ['name' => 'People & payroll', 'dependencies' => ['accounting']],
        'attendance' => ['name' => 'Attendance', 'dependencies' => ['hr']],
        'crm' => ['name' => 'Customers', 'dependencies' => []],
        'ai' => ['name' => 'AI catalog drafts', 'dependencies' => []],
    ],
];
