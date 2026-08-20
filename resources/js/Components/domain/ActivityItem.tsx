import { Avatar } from '@/Components/ui/Avatar';
import { formatWaktu } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

export function ActivityItem({ activity }: { activity: App.Data.ActivityLogData }) {
    const changes = Object.entries(activity.perubahan.baru);

    return (
        <article className="flex gap-3 px-3 py-3">
            <Avatar initials={activity.inisial_pelaku} name={activity.pelaku} size="sm" />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-sm">
                    <span className="font-medium text-ink">{activity.pelaku}</span>
                    <span className="text-ink-muted">{activity.deskripsi}</span>
                </div>
                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-subtle">
                    <span>{formatWaktu(activity.terjadi_pada)}</span>
                    <span aria-hidden>·</span>
                    {activity.document_id ? (
                        <Link href={`/documents/${activity.document_id}`} className="font-medium text-brand-700 hover:text-brand-800">
                            {activity.subjek}
                        </Link>
                    ) : (
                        <span>{activity.subjek}</span>
                    )}
                </div>
                {changes.length > 0 && (
                    <dl className="mt-2 space-y-1 rounded-lg bg-surface-sunken p-2 text-xs">
                        {changes.map(([field, value]) => (
                            <div key={field} className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
                                <dt className="col-span-3 font-medium text-ink-muted">{field}</dt>
                                <dd className="truncate text-ink-subtle">{activity.perubahan.lama[field] ?? '—'}</dd>
                                <ArrowRight className="size-3 text-ink-subtle" aria-hidden />
                                <dd className="truncate text-ink">{value}</dd>
                            </div>
                        ))}
                    </dl>
                )}
            </div>
        </article>
    );
}
