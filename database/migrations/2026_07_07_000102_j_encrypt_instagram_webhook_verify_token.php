<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Batch J / finding #41 — encrypt-at-rest backfill for the newly-listed
 * SystemSetting key `instagram_webhook_verify_token`.
 *
 * SystemSetting::get() already tolerates plaintext (Crypt::decrypt throws and
 * it falls back to the raw value), so this backfill is defensive: it flips any
 * existing plaintext row to ciphertext without waiting for the next admin save.
 * The `value` column is TEXT, so the ~200-char ciphertext fits.
 *
 * Idempotent: only rows that fail to decrypt (still plaintext) are touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $row = DB::table('system_settings')
            ->where('key', 'instagram_webhook_verify_token')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->first(['id', 'value']);

        if (! $row) {
            return;
        }

        try {
            Crypt::decrypt($row->value); // already ciphertext → leave it
        } catch (DecryptException $e) {
            DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['value' => Crypt::encrypt($row->value)]);
        }
    }

    public function down(): void
    {
        // Irreversible by design — we never write secrets back as plaintext.
    }
};
