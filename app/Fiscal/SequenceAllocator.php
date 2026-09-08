<?php

namespace App\Fiscal;

use App\Models\FiscalSequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hands out the next secuencial for a venue's (ambiente, doc type,
 * estab, pto_emi) under a row lock, so two terminals issuing at the same
 * moment never get the same number and no number is ever skipped.
 *
 * Rejected documents keep their number and are retried with it (Ficha
 * §5.10); only a document that is abandoned before it is ever sent may
 * be released, and even then only if it is still the latest.
 */
class SequenceAllocator
{
    public function next(User $venue, int $ambiente, string $docType, string $estab, string $ptoEmi): int
    {
        return DB::transaction(function () use ($venue, $ambiente, $docType, $estab, $ptoEmi) {
            $row = FiscalSequence::query()
                ->where('editor_id', $venue->id)
                ->where('ambiente', $ambiente)
                ->where('doc_type', $docType)
                ->where('estab', $estab)
                ->where('pto_emi', $ptoEmi)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = FiscalSequence::create([
                    'editor_id' => $venue->id,
                    'ambiente' => $ambiente,
                    'doc_type' => $docType,
                    'estab' => $estab,
                    'pto_emi' => $ptoEmi,
                    'last_number' => 0,
                ]);
            }

            $row->last_number = $row->last_number + 1;
            $row->save();

            return (int) $row->last_number;
        });
    }

    /**
     * Continue numbering from a previous system: set the last number used
     * so the next document is N+1. Never lower than what already exists.
     */
    public function seed(User $venue, int $ambiente, string $docType, string $estab, string $ptoEmi, int $lastUsed): void
    {
        DB::transaction(function () use ($venue, $ambiente, $docType, $estab, $ptoEmi, $lastUsed) {
            $row = FiscalSequence::firstOrCreate(
                ['editor_id' => $venue->id, 'ambiente' => $ambiente, 'doc_type' => $docType, 'estab' => $estab, 'pto_emi' => $ptoEmi],
                ['last_number' => 0],
            );
            if ($lastUsed > $row->last_number) {
                $row->last_number = $lastUsed;
                $row->save();
            }
        });
    }
}
