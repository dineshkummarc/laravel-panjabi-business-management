<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('business:demo', function () {
    if (app()->environment('production') || ! config('business.demo')) {
        $this->error('Set DEMO_MODE=true in a nonproduction environment first.');

        return 1;
    }
    $this->call('migrate', ['--force' => true]);
    $this->call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    $this->info('Demo ready. Owner: owner@panjabi.demo / DemoOnly!2026');
})->purpose('Install fictional demo businesses without duplicating existing demo records.');
