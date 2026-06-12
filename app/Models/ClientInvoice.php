<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tax-invoice details a guest provides for a table session, shown on
 * the printed bill.
 */
class ClientInvoice extends Model
{
    use BelongsToEditor, HasFactory;

    protected $fillable = [
        'table_session_id',
        'table_id',
        'editor_id',
        'name',
        'tax_id',
        'address',
        'email',
        'phone',
    ];

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }
}
