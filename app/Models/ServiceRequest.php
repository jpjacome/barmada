<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A guest signal from the table page: "bring the bill" or "call a waiter".
 * Created through the tokenized guest flow, resolved from the staff board.
 */
class ServiceRequest extends Model
{
    use BelongsToEditor, HasFactory;

    public const TYPE_BILL = 'bill';
    public const TYPE_WAITER = 'waiter';

    protected $fillable = [
        'table_id',
        'table_session_id',
        'editor_id',
        'type',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }
}
