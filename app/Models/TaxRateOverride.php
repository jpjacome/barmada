<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A date-bounded remap of one IVA code to another.
 *
 * editor_id NULL = platform-wide (e.g. the government declares an 8%
 * tourism rate for a long weekend: from_code 4 → to_code 8 for those
 * dates). A venue-specific row takes precedence over a platform row.
 */
class TaxRateOverride extends Model
{
    protected $fillable = ['editor_id', 'from_code', 'to_code', 'starts_on', 'ends_on', 'reason'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];
}
