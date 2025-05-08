<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TableSessionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_session_id',
        'ip_address',
        'status',
        'requested_at',
        'approved_at',
        'denied_at',
    ];

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }
}
