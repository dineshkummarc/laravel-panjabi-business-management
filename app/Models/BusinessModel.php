<?php

namespace App\Models;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

abstract class BusinessModel extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $id = app(TenantContext::class)->id();
            $id ? $query->where($query->getModel()->qualifyColumn('organization_id'), $id) : $query->whereRaw('1 = 0');
            $user = auth()->user();
            if ($user && $user->role !== 'customer' && $user->branch_id && ! in_array($user->role, ['owner', 'admin', 'accountant'])) {

                if (in_array($query->getModel()->getTable(), ['orders', 'stock_levels', 'stock_movements', 'shifts', 'employees', 'tailoring_jobs', 'production_batches', 'expenses', 'purchases', 'journal_entries'])) {
                    $query->where($query->getModel()->qualifyColumn('branch_id'), $user->branch_id);
                }
            }
        });
        static::saving(function (Model $model) {
            if ($model->exists && $model->isDirty('organization_id')) {
                throw new \LogicException('Business ownership cannot be changed.');
            }
            $links = ['branch_id' => Branch::class, 'source_id' => Branch::class, 'destination_id' => Branch::class, 'product_id' => Product::class, 'category_id' => Category::class, 'variant_id' => Variant::class, 'material_id' => Variant::class, 'customer_id' => Customer::class, 'supplier_id' => Supplier::class, 'employee_id' => Employee::class, 'order_id' => Order::class, 'journal_entry_id' => JournalEntry::class];
            foreach ($links as $key => $class) {
                if ($model->getAttribute($key) !== null && ! $class::whereKey($model->getAttribute($key))->exists()) {
                    throw ValidationException::withMessages([$key => 'The selected record does not belong to the accessible business.']);
                }
            }
        });
        static::creating(function (Model $model) {
            $id = app(TenantContext::class)->id();
            if (! $id) {
                throw new \LogicException('Business context is required.');
            }
            $model->organization_id = $id;
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
