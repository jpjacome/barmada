<?php

namespace App\Actions\Settings;

use App\Models\User;

/**
 * The venue's fiscal identity for electronic invoicing. Not
 * mass-assignable; validated at the boundary and set explicitly.
 */
class UpdateFiscalProfile
{
    public function handle(User $editor, array $validated): User
    {
        $strings = ['fiscal_ruc', 'fiscal_razon_social', 'fiscal_nombre_comercial', 'fiscal_dir_matriz',
            'fiscal_dir_establecimiento', 'fiscal_contribuyente_especial', 'fiscal_rimpe', 'fiscal_provider'];
        foreach ($strings as $key) {
            if (array_key_exists($key, $validated)) {
                $value = $validated[$key];
                $editor->forceFill([$key => ($value === null || $value === '') ? null : trim((string) $value)]);
            }
        }

        foreach (['fiscal_estab', 'fiscal_pto_emi'] as $key) {
            if (! empty($validated[$key])) {
                $editor->forceFill([$key => str_pad(preg_replace('/\D/', '', (string) $validated[$key]), 3, '0', STR_PAD_LEFT)]);
            }
        }

        if (array_key_exists('fiscal_ambiente', $validated) && $validated['fiscal_ambiente'] !== null) {
            $editor->forceFill(['fiscal_ambiente' => (int) $validated['fiscal_ambiente']]);
        }

        foreach (['fiscal_obligado_contabilidad', 'fiscal_enabled'] as $flag) {
            if (array_key_exists($flag, $validated) && $validated[$flag] !== null) {
                $editor->forceFill([$flag => filter_var($validated[$flag], FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        // Defaults the XML relies on.
        if (! $editor->fiscal_rimpe) {
            $editor->forceFill(['fiscal_rimpe' => 'none']);
        }

        $editor->save();

        return $editor->refresh();
    }

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        $providers = implode(',', array_keys(config('fiscal.providers', ['none' => null])));

        return [
            'fiscal_ruc' => ['nullable', 'regex:/^\d{13}$/'],
            'fiscal_razon_social' => ['nullable', 'string', 'max:300'],
            'fiscal_nombre_comercial' => ['nullable', 'string', 'max:300'],
            'fiscal_dir_matriz' => ['nullable', 'string', 'max:300'],
            'fiscal_dir_establecimiento' => ['nullable', 'string', 'max:300'],
            'fiscal_estab' => ['nullable', 'regex:/^\d{1,3}$/'],
            'fiscal_pto_emi' => ['nullable', 'regex:/^\d{1,3}$/'],
            'fiscal_obligado_contabilidad' => ['nullable', 'boolean'],
            'fiscal_contribuyente_especial' => ['nullable', 'regex:/^\d{3,13}$/'],
            'fiscal_rimpe' => ['nullable', 'in:none,emprendedor,negocio_popular'],
            'fiscal_ambiente' => ['nullable', 'in:1,2'],
            'fiscal_provider' => ['nullable', 'in:'.$providers],
            'fiscal_enabled' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public static function payload(User $venue): array
    {
        return [
            'fiscal_enabled' => (bool) $venue->fiscal_enabled,
            'fiscal_ruc' => $venue->fiscal_ruc,
            'fiscal_razon_social' => $venue->fiscal_razon_social,
            'fiscal_nombre_comercial' => $venue->fiscal_nombre_comercial,
            'fiscal_dir_matriz' => $venue->fiscal_dir_matriz,
            'fiscal_dir_establecimiento' => $venue->fiscal_dir_establecimiento,
            'fiscal_estab' => $venue->fiscal_estab ?: '001',
            'fiscal_pto_emi' => $venue->fiscal_pto_emi ?: '001',
            'fiscal_obligado_contabilidad' => (bool) $venue->fiscal_obligado_contabilidad,
            'fiscal_contribuyente_especial' => $venue->fiscal_contribuyente_especial,
            'fiscal_rimpe' => $venue->fiscal_rimpe ?: 'none',
            'fiscal_ambiente' => (int) ($venue->fiscal_ambiente ?: 1),
            'fiscal_provider' => $venue->fiscal_provider ?: 'none',
            'providers' => array_keys(config('fiscal.providers', [])),
        ];
    }
}
