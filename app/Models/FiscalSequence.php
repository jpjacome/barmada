<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Gapless, ascending sequence counter per (venue, ambiente, doc type,
 * establishment, emission point). SRI never resets these — not yearly,
 * not ever — and puts the duty to keep them gapless on the software.
 */
class FiscalSequence extends Model
{
    protected $guarded = ['id'];
}
