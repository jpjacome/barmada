<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableSession extends Model
{
    protected $fillable = [
        'table_id',
        'session_number',
        'date',
        'unique_token',
        'status',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'editor_id',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
