<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ActivityLogName;
use App\Http\Requests\ActivityLogIndexRequest;
use App\Services\ActivityLogQuery;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman riwayat aktivitas terotorisasi (FR-52). */
final class ActivityLogController extends Controller
{
    public function index(ActivityLogIndexRequest $request, ActivityLogQuery $aktivitas): Response
    {
        return Inertia::render('ActivityLog/Index', [
            'aktivitas' => $aktivitas->paginate($request),
            'filter' => $request->filterAktif(),
            'opsi' => array_map(
                static fn (ActivityLogName $jenis): array => ['value' => $jenis->value, 'label' => $jenis->label()],
                ActivityLogName::cases(),
            ),
        ]);
    }
}
