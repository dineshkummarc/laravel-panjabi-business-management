<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class Ledger
{
    public function post(int $branch, string $reference, string $description, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($branch, $reference, $description, $lines) {
            $debit = array_sum(array_column($lines, 'debit'));
            $credit = array_sum(array_column($lines, 'credit'));
            if ($debit !== $credit || $debit < 0) {
                throw new \LogicException('Journal entries must balance.');
            }
            if ($entry = JournalEntry::where('reference', $reference)->first()) {
                return $entry;
            }
            $entry = JournalEntry::create(['branch_id' => $branch, 'reference' => $reference, 'description' => $description]);
            foreach ($lines as $line) {
                if (($line['debit'] ?? 0) < 0 || ($line['credit'] ?? 0) < 0) {
                    throw new \LogicException('Negative journal line.');
                } $entry->lines()->create($line);
            }

            return $entry;
        });
    }

    public function pair(int $branch, string $ref, string $description, string $debit, string $credit, int $amount): JournalEntry
    {
        return $this->post($branch, $ref, $description, [['account' => $debit, 'debit' => $amount, 'credit' => 0], ['account' => $credit, 'debit' => 0, 'credit' => $amount]]);
    }
}
