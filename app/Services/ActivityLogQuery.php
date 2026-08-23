<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ActivityLogData;
use App\Enums\ActivityLogName;
use App\Http\Requests\ActivityLogIndexRequest;
use App\Http\Requests\Admin\ActivityLogIndexRequest as AdminActivityLogIndexRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Query riwayat yang menjadi sumber kebenaran batas tampil aktivitas.
 *
 * Tidak memakai eager-load morph subject. Label subjek sudah disalin saat
 * aktivitas dibuat, sedangkan nama pelaku diperoleh melalui satu LEFT JOIN.
 */
final class ActivityLogQuery
{
    /** @return LengthAwarePaginator<int, ActivityLogData> */
    public function paginate(ActivityLogIndexRequest $request): LengthAwarePaginator
    {
        return $this->terapkanFilter($this->visibleTo($request->user()), $request)
            ->orderByDesc('activity_log.id')
            ->paginate(25, ['activity_log.*', 'pelaku.name as nama_pelaku'])
            ->withQueryString()
            ->through(fn (Activity $activity): ActivityLogData => ActivityLogData::fromActivity($activity, $activity->getAttribute('nama_pelaku')));
    }

    /**
     * Halaman pemantauan Superadmin (FEAT-15b): lintas seluruh pengguna,
     * tanpa batas `visibleTo()` — akses ke halamannya sendiri sudah dijaga
     * middleware `superadmin`, jadi query di sini sengaja tidak terbatas.
     *
     * @return LengthAwarePaginator<int, ActivityLogData>
     */
    public function paginateAdmin(AdminActivityLogIndexRequest $request): LengthAwarePaginator
    {
        return $this->terapkanFilter($this->base(), $request)
            ->when($request->integer('pelaku') ?: null, fn (Builder $q, int $id) => $q->where('activity_log.causer_id', $id))
            ->when($request->integer('unit') ?: null, fn (Builder $q, int $id) => $q->where('pelaku.unit_id', $id))
            ->orderByDesc('activity_log.id')
            ->paginate(25, ['activity_log.*', 'pelaku.name as nama_pelaku'])
            ->withQueryString()
            ->through(fn (Activity $activity): ActivityLogData => ActivityLogData::fromActivity($activity, $activity->getAttribute('nama_pelaku')));
    }

    /** @return list<ActivityLogData> */
    public function forDocument(Document $document): array
    {
        $akarVersiId = $document->version_root_id ?? $document->id;

        return $this->base()
            ->where('activity_log.subject_type', $document->getMorphClass())
            // Setiap versi adalah baris dokumen tersendiri. Riwayat pada
            // halaman detail harus tetap satu jejak audit, bukan terpotong
            // hanya karena pengguna sedang melihat versi yang terbaru.
            ->whereIn('activity_log.subject_id', Document::query()
                ->where('version_root_id', $akarVersiId)
                ->select('documents.id'))
            ->orderByDesc('activity_log.id')
            ->get(['activity_log.*', 'pelaku.name as nama_pelaku'])
            ->map(fn (Activity $activity): ActivityLogData => ActivityLogData::fromActivity($activity, $activity->getAttribute('nama_pelaku')))
            ->all();
    }

    /** @return list<ActivityLogData> */
    public function latestFor(User $user, int $limit = 5): array
    {
        return $this->visibleTo($user)
            ->orderByDesc('activity_log.id')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity): ActivityLogData => ActivityLogData::fromActivity($activity, $activity->getAttribute('nama_pelaku')))
            ->all();
    }

    /** @return Builder<Activity> */
    private function visibleTo(User $user): Builder
    {
        $query = $this->base();

        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where(function (Builder $documentQuery) use ($user): void {
                    $documentQuery
                        ->where('activity_log.subject_type', (new Document)->getMorphClass())
                        ->whereIn('activity_log.subject_id', Document::query()
                            ->where(function (Builder $visibleDocuments) use ($user): void {
                                $visibleDocuments
                                    ->active()
                                    ->visibleTo($user)
                                    // Riwayat pengunggah tetap tersedia setelah
                                    // dokumennya masuk Sampah atau dinonaktifkan.
                                    // Tanpa cabang ini, aksi hapus/pulihkan justru
                                    // hilang dari jejak audit pelakunya sendiri.
                                    ->orWhere('documents.uploaded_by', $user->id);
                            })
                            ->select('documents.id'));
                })
                // Folder bersifat pribadi dan tidak memiliki policy akses dokumen.
                // Pemilik tetap perlu melihat jejak pindah/Sampah foldernya sendiri,
                // tetapi aktivitas ruang kerja pengguna lain tidak boleh bocor.
                ->orWhere(function (Builder $workspaceQuery) use ($user): void {
                    $workspaceQuery
                        ->where('activity_log.log_name', ActivityLogName::DocumentWorkspace->value)
                        ->where('activity_log.causer_id', $user->id);
                });
        });
    }

    /**
     * Penyaring `cari`/`jenis`/`dari`/`sampai` dipakai bersama oleh halaman
     * riwayat pribadi dan halaman pemantauan admin — hanya cakupan awal
     * query dan penyaring tambahannya (pelaku, unit) yang berbeda.
     *
     * @return Builder<Activity>
     */
    private function terapkanFilter(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->string('cari')->toString(), fn (Builder $q, string $kata) => $q->where('activity_log.description', 'like', "%{$kata}%"))
            ->when($request->string('jenis')->toString(), fn (Builder $q, string $jenis) => $q->where('activity_log.log_name', $jenis))
            ->when($request->string('dari')->toString(), fn (Builder $q, string $tanggal) => $q->whereDate('activity_log.created_at', '>=', $tanggal))
            ->when($request->string('sampai')->toString(), fn (Builder $q, string $tanggal) => $q->whereDate('activity_log.created_at', '<=', $tanggal));
    }

    /** @return Builder<Activity> */
    private function base(): Builder
    {
        return Activity::query()
            ->leftJoin('users as pelaku', function ($join): void {
                $join->on('pelaku.id', '=', 'activity_log.causer_id')
                    ->where('activity_log.causer_type', User::class);
            })
            ->select('activity_log.*')
            ->addSelect('pelaku.name as nama_pelaku');
    }
}
