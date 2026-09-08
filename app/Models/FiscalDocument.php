<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Model;

/**
 * An immutable fiscal document (factura, nota de crédito, …) issued
 * against a table session. Once built, its money and lines never change;
 * only its transport lifecycle does.
 */
class FiscalDocument extends Model
{
    use BelongsToEditor;

    public const TYPE_FACTURA = '01';
    public const TYPE_NOTA_CREDITO = '04';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_BUILT = 'built';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_SENT = 'sent';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ERROR = 'error';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_emision' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'propina' => 'decimal:2',
        'importe_total' => 'decimal:2',
        'taxes' => 'array',
        'lines' => 'array',
        'payments' => 'array',
        'sri_response' => 'array',
        'authorized_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function session()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** e.g. 001-001-000000123 */
    public function number(): string
    {
        return sprintf('%s-%s-%09d', $this->estab, $this->pto_emi, $this->secuencial);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_AUTHORIZED, self::STATUS_REJECTED], true);
    }
}
