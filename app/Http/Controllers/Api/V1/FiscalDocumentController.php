<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fiscal\IssueFactura;
use App\Actions\Settings\UpdateFiscalProfile;
use App\Exceptions\DomainActionException;
use App\Http\Controllers\Controller;
use App\Models\FiscalDocument;
use App\Models\Table;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FiscalDocumentController extends Controller
{
    use AuthorizesRequests;

    /** POST /tables/{table}/factura — issue for the current session. */
    public function issue(Request $request, Table $table, IssueFactura $issue)
    {
        Gate::authorize('issue', [FiscalDocument::class, $table]);

        try {
            $document = $issue->handle($table, $request->user());
        } catch (DomainActionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['document' => $this->row($document)], 201);
    }

    public function show(FiscalDocument $fiscalDocument)
    {
        $this->authorize('view', $fiscalDocument);

        return response()->json(['document' => $this->row($fiscalDocument, withXml: true)]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|max:20',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = FiscalDocument::query()->orderByDesc('id');
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? 25));

        return response()->json([
            'documents' => collect($page->items())->map(fn ($d) => $this->row($d)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** GET/PATCH /settings/fiscal — the venue's fiscal profile. */
    public function profile(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->is_editor, 403);

        return response()->json(['fiscal' => UpdateFiscalProfile::payload($user)]);
    }

    public function updateProfile(Request $request, UpdateFiscalProfile $update)
    {
        $user = $request->user();
        abort_unless($user && $user->is_editor, 403);

        $validated = $request->validate(UpdateFiscalProfile::rules());
        $update->handle($user, $validated);

        return response()->json(['fiscal' => UpdateFiscalProfile::payload($user->refresh())]);
    }

    private function row(FiscalDocument $d, bool $withXml = false): array
    {
        $row = [
            'id' => $d->id,
            'type' => $d->doc_type,
            'number' => $d->number(),
            'clave_acceso' => $d->clave_acceso,
            'authorization_number' => $d->authorization_number,
            'status' => $d->status,
            'ambiente' => $d->ambiente,
            'fecha_emision' => $d->fecha_emision?->toDateString(),
            'table_id' => $d->table_id,
            'table_session_id' => $d->table_session_id,
            'buyer' => [
                'id_type' => $d->buyer_id_type,
                'identification' => $d->buyer_identification,
                'name' => $d->buyer_name,
                'email' => $d->buyer_email,
            ],
            'totals' => [
                'subtotal' => (float) $d->subtotal,
                'tax_total' => (float) $d->tax_total,
                'propina' => (float) $d->propina,
                'importe_total' => (float) $d->importe_total,
                'taxes' => $d->taxes,
            ],
            'payments' => $d->payments,
            'provider' => $d->provider,
            'error' => $d->error_code ? ['code' => $d->error_code, 'message' => $d->error_message] : null,
            'authorized_at' => $d->authorized_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
        ];

        if ($withXml) {
            $row['lines'] = $d->lines;
            $row['xml'] = $d->xml_authorized ?: $d->xml_signed ?: $d->xml_unsigned;
        }

        return $row;
    }
}
