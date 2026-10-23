<?php

return [
    'engine' => 'local-native',
    'default_font' => 'Noto Sans Bengali',
    'custom_font_path' => resource_path('fonts'),
    'font_path' => resource_path('fonts'),
    'fallback_fonts' => ['Noto Sans Bengali', 'DejaVu Sans'],
    'fonts' => [
        'Noto Sans Bengali' => ['regular' => resource_path('fonts/NotoSansBengali-Regular.ttf')],
        'DejaVu Sans' => ['regular' => resource_path('fonts/DejaVuSans.ttf')],
    ],
];
