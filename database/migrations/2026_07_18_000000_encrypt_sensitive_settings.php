<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_methods', 'shipping_partners'] as $table) {
            DB::table($table)->orderBy('id')->each(function (object $row) use ($table): void {
                $value = json_decode($row->settings, true);
                if (!is_array($value)) {
                    return;
                }

                DB::table($table)->where('id', $row->id)->update([
                    'settings' => json_encode(Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR),
                ]);
            });
        }

        DB::table('project_settings')
            ->where('setting_key', 'notification_settings')
            ->orderBy('id')
            ->each(function (object $row): void {
                $value = json_decode($row->setting_value, true);
                if (!is_array($value)) {
                    return;
                }

                DB::table('project_settings')->where('id', $row->id)->update([
                    'setting_value' => json_encode(Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        foreach (['payment_methods', 'shipping_partners'] as $table) {
            DB::table($table)->orderBy('id')->each(function (object $row) use ($table): void {
                $value = json_decode($row->settings, true);
                if (!is_string($value)) {
                    return;
                }

                try {
                    DB::table($table)->where('id', $row->id)->update([
                        'settings' => Crypt::decryptString($value),
                    ]);
                } catch (DecryptException) {
                    // Preserve unrecognised values rather than destructively overwriting them.
                }
            });
        }

        DB::table('project_settings')
            ->where('setting_key', 'notification_settings')
            ->orderBy('id')
            ->each(function (object $row): void {
                $value = json_decode($row->setting_value, true);
                if (!is_string($value)) {
                    return;
                }

                try {
                    DB::table('project_settings')->where('id', $row->id)->update([
                        'setting_value' => Crypt::decryptString($value),
                    ]);
                } catch (DecryptException) {
                    // Preserve unrecognised values rather than destructively overwriting them.
                }
            });
    }
};
