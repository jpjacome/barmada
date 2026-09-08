<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fiscal ledger: immutable, sequenced documents issued against a
 * table session, the venue's fiscal identity, and the gapless sequence
 * counters SRI requires per establishment + emission point + doc type.
 *
 * Transport (signing and transmission to SRI) is a pluggable driver; the
 * ledger records every state a document passes through and keeps the
 * authorised XML SRI returns — the copy the venue must retain 7 years.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Fiscal identity of the venue (infoTributaria / infoFactura).
            $table->string('fiscal_ruc', 13)->nullable()->after('service_charge_rate_bp');
            $table->string('fiscal_razon_social', 300)->nullable()->after('fiscal_ruc');
            $table->string('fiscal_nombre_comercial', 300)->nullable()->after('fiscal_razon_social');
            $table->string('fiscal_dir_matriz', 300)->nullable()->after('fiscal_nombre_comercial');
            $table->string('fiscal_dir_establecimiento', 300)->nullable()->after('fiscal_dir_matriz');
            $table->string('fiscal_estab', 3)->default('001')->after('fiscal_dir_establecimiento');
            $table->string('fiscal_pto_emi', 3)->default('001')->after('fiscal_estab');
            $table->boolean('fiscal_obligado_contabilidad')->default(false)->after('fiscal_pto_emi');
            $table->string('fiscal_contribuyente_especial', 13)->nullable()->after('fiscal_obligado_contabilidad');
            // none | emprendedor | negocio_popular
            $table->string('fiscal_rimpe', 20)->default('none')->after('fiscal_contribuyente_especial');
            // 1 = pruebas, 2 = producción
            $table->unsignedTinyInteger('fiscal_ambiente')->default(1)->after('fiscal_rimpe');
            // Transport driver key from config/fiscal.php providers; null = not configured.
            $table->string('fiscal_provider', 32)->nullable()->after('fiscal_ambiente');
            $table->boolean('fiscal_enabled')->default(false)->after('fiscal_provider');
        });

        Schema::create('fiscal_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editor_id');
            $table->unsignedTinyInteger('ambiente');
            $table->string('doc_type', 2);      // 01 factura, 04 nota de crédito, ...
            $table->string('estab', 3);
            $table->string('pto_emi', 3);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['editor_id', 'ambiente', 'doc_type', 'estab', 'pto_emi'], 'fiscal_sequences_scope_unique');
        });

        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editor_id')->index();
            $table->unsignedBigInteger('table_id')->nullable()->index();
            $table->unsignedBigInteger('table_session_id')->nullable()->index();
            $table->unsignedBigInteger('issued_by')->nullable();

            $table->string('doc_type', 2);                 // 01, 04, ...
            $table->unsignedTinyInteger('ambiente');       // 1 | 2
            $table->string('estab', 3);
            $table->string('pto_emi', 3);
            $table->unsignedInteger('secuencial');
            $table->string('clave_acceso', 49)->unique();
            $table->date('fecha_emision');                 // venue-local date

            // Buyer (infoFactura). 07 = consumidor final.
            $table->string('buyer_id_type', 2);
            $table->string('buyer_identification', 20);
            $table->string('buyer_name', 300);
            $table->string('buyer_address', 300)->nullable();
            $table->string('buyer_email', 255)->nullable();

            // Money snapshot (what the XML says).
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2);
            $table->decimal('propina', 12, 2)->default(0);
            $table->decimal('importe_total', 12, 2);
            $table->json('taxes');                          // [{codigo, codigo_porcentaje, tarifa_bp, base, valor}]
            $table->json('lines');                          // [{codigo_principal, descripcion, cantidad, precio_unitario, descuento, total_sin_impuesto, impuestos[]}]
            $table->json('payments');                       // [{forma_pago, total}]

            // Lifecycle: draft → built → signed → sent → authorized | rejected | error
            $table->string('status', 20)->default('draft')->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref', 128)->nullable();
            $table->string('authorization_number', 49)->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->longText('xml_unsigned')->nullable();
            $table->longText('xml_signed')->nullable();
            $table->longText('xml_authorized')->nullable();  // SRI's copy — the one to retain
            $table->json('sri_response')->nullable();
            $table->string('error_code', 8)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();

            $table->timestamps();
            $table->unique(['editor_id', 'ambiente', 'doc_type', 'estab', 'pto_emi', 'secuencial'], 'fiscal_documents_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
        Schema::dropIfExists('fiscal_sequences');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([
            'fiscal_ruc', 'fiscal_razon_social', 'fiscal_nombre_comercial', 'fiscal_dir_matriz',
            'fiscal_dir_establecimiento', 'fiscal_estab', 'fiscal_pto_emi', 'fiscal_obligado_contabilidad',
            'fiscal_contribuyente_especial', 'fiscal_rimpe', 'fiscal_ambiente', 'fiscal_provider', 'fiscal_enabled',
        ]));
    }
};
