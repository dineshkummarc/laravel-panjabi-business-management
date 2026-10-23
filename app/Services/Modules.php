<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class Modules
{
    public function update(Organization $organization, array $enabled): void
    {
        foreach ($enabled as $module) {
            if (! isset(config('business.modules')[$module])) {
                throw ValidationException::withMessages(['modules' => 'Unknown module.']);
            }
            foreach (config("business.modules.$module.dependencies") as $dependency) {
                if (! in_array($dependency, $enabled)) {
                    throw ValidationException::withMessages(['modules' => "$module requires $dependency."]);
                }
            }
        }
        $organization->update(['modules' => array_values($enabled)]);
    }

    public function require(string $module): void
    {
        abort_unless(Organization::findOrFail(app(TenantContext::class)->id())->enabled($module), 403, 'This module is disabled. Historical records remain available.');
    }
}
