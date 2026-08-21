<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $leadership = DB::table('jabatans')->where('nama', 'Pimpinan BPMA')->first();
        $head = DB::table('jabatans')->where('nama', 'Kepala BPMA')->first();

        if ($leadership === null && $head !== null) {
            DB::table('jabatans')->where('id', $head->id)->update([
                'nama' => 'Pimpinan BPMA',
                'tingkat_akses' => 1,
            ]);
            $leadership = DB::table('jabatans')->where('id', $head->id)->first();
        }

        if ($leadership === null) {
            $leadershipId = DB::table('jabatans')->insertGetId([
                'nama' => 'Pimpinan BPMA',
                'tingkat_akses' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $leadershipId = $leadership->id;
        }

        $formerPositions = DB::table('jabatans')
            ->whereIn('nama', ['Kepala BPMA', 'Wakil Kepala BPMA'])
            ->pluck('id');

        if ($formerPositions->isNotEmpty()) {
            DB::table('users')
                ->whereIn('jabatan_id', $formerPositions)
                ->update(['jabatan_id' => $leadershipId, 'updated_at' => now()]);

            DB::table('jabatans')->whereIn('id', $formerPositions)->delete();
        }
    }

    public function down(): void
    {
        DB::table('jabatans')
            ->where('nama', 'Pimpinan BPMA')
            ->update(['nama' => 'Kepala BPMA', 'updated_at' => now()]);
    }
};
