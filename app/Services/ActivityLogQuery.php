<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ActivityLogData;
use App\Http\Requests\ActivityLogIndexRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Query riwayat yang menjadi sumber kebenaran batas tampil aktivitas.
 *
 * Tidak memakai eager-load morph subject. Label subjek sudah disalin saat
 * aktivitas dibuat, sedangkan nama pelaku diperoleh melalui satu LEFT JOIN;
 * pagination berisi ratusan baris tetap dua query (count dan data), bukan N+1.
 */
final class ActivityLogQuery
{
    /** @return LengthAwarePaginator<int, ActivityLogData> */
    public function paginate(ActivityLogIndexRequest $request): LengthAwarePaginator
    {
        return $this->visibleTo($request->user())
            ->when($request->string('cari')->toString(), fn (Builder $q, string $kata) => $q->where('activity_log.description', 'like', "%{$kata}%"))
            ->when($request->string('jenis')->toString(), fn (Builder $q, string $jenis) => $q->where('activity_log.log_name', $jenis))
            ->when($request->string('dari')->toString(), fn (Builder $q, string $tanggal) => $q->whereDate('activity_log.created_at', '>=', $tanggal))
            ->when($request->string('sampai')->toString(), fn (Builder $q, string $tanggal) => $q->whereDate('activity_log.created_at', '<=', $tanggal))
            ->orderByDesc('activity_log.id')
            ->paginate(25, ['activity_log.*', 'pelaku.name as nama_pelaku'])
            ->withQueryString()
            ->through(fn (Activity $activity): ActivityLogData => ActivityLogData::fromActivity($activity, $activity->getAttribute('nama_pelaku')));
    }

    /** @return list<ActivityLogData> */
    public function recentForDocument(Document $document, int $limit = 10): array
    {
        return $this->base()
            ->where('activity_log.subject_type', $document->getMorphClass())
            ->where('activity_log.subject_id', $document->id)
            ->orderByDesc('activity_log.id')
            ->limit($limit)
            ->get()
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

        return $query
            ->where('activity_log.subject_type', (new Document)->getMorphClass())
            ->whereIn('activity_log.subject_id', Document::query()->active()->visibleTo($user)->select('documents.id'));
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
