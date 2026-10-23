<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CatalogCsv
{
    public const COLUMNS = ['name', 'slug', 'sku', 'size', 'color', 'price_poisha', 'cost_poisha', 'fabric'];

    public function preview(string $path): array
    {
        $file = fopen($path, 'r');
        $header = fgetcsv($file, 0, ',', '"', '');
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }
        if ($header !== self::COLUMNS) {
            fclose($file);
            throw ValidationException::withMessages(['file' => 'CSV columns must be: '.implode(',', self::COLUMNS)]);
        }
        $rows = [];
        $errors = [];
        $line = 1;
        $seen = [];
        while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
            $line++;
            if ($line > 501) {
                $errors[] = 'Maximum 500 rows per import.';
                break;
            }
            if (count($values) !== count($header)) {
                $errors[] = "Row $line: wrong column count.";

                continue;
            }
            $row = array_combine($header, $values);
            $validator = Validator::make($row, ['name' => 'required|string|max:150', 'slug' => 'required|string|max:150|regex:/^[\pL\pN\pM_-]+$/u', 'sku' => 'required|string|max:100', 'size' => 'required|string|max:50', 'color' => 'required|string|max:50', 'price_poisha' => 'required|integer|min:0|max:100000000', 'cost_poisha' => 'required|integer|min:0|max:100000000', 'fabric' => 'nullable|string|max:100']);
            if ($validator->fails()) {
                $errors[] = "Row $line: ".implode(' ', $validator->errors()->all());

                continue;
            }
            if (isset($seen[$row['sku']]) || Variant::where('sku', $row['sku'])->exists()) {
                $errors[] = "Row $line: duplicate SKU ".$row['sku'];

                continue;
            }
            $seen[$row['sku']] = true;
            $rows[] = $row;
        }
        fclose($file);

        return compact('rows', 'errors');
    }

    public function commit(array $rows): int
    {
        app(Modules::class)->require('inventory');

        return DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $product = Product::firstOrCreate(['slug' => $row['slug']], ['name' => $row['name'], 'fabric' => $row['fabric'], 'active' => false, 'image' => '/images/panjabi-sage.svg']);
                Variant::create(['product_id' => $product->id, 'sku' => $row['sku'], 'size' => $row['size'], 'color' => $row['color'], 'price' => (int) $row['price_poisha'], 'cost' => (int) $row['cost_poisha']]);
            }
            Audit::record('catalog.imported', 'CSV', ['rows' => count($rows)]);

            return count($rows);
        });
    }
}
