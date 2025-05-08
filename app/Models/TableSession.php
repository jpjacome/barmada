<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableSession extends Model
{
    use HasFactory;

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

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
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

    public function sessionRequests()
    {
        return $this->hasMany(TableSessionRequest::class);
    }
}
