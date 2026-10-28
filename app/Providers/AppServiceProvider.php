<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use App\Models\Product;
use App\Services\LocalPdfEngine;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use ImranDev\UnicodePdf\Facades\UnicodePdf;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    public function boot(): void
    {
		Schema::defaultStringLength(191);
        config(['ai-catalog.tenancy.enabled' => true, 'ai-catalog.tenancy.resolver' => fn () => (string) (app(TenantContext::class)->id() ?? 'none'), 'ai-catalog.taxonomy.source' => 'config', 'ai-catalog.cache.enabled' => false, 'ai-catalog.schema.model' => Product::class]);
        UnicodePdf::extend('local-native', fn ($app, $fonts, $security) => new LocalPdfEngine(viewFactory: $app->make('view'), fontManager: $fonts, securityManager: $security));
        Blade::directive('money', fn ($expression) => "<?php echo '৳'.number_format(($expression)/100, 2); ?>");
    }
}
