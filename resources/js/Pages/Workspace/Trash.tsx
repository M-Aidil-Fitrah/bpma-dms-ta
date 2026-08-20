import { FileTypeBadge } from '@/Components/domain/FileTypeBadge';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { formatTanggalPanjang } from '@/lib/format';
import { router } from '@inertiajs/react';
import { CalendarClock, FileText, Folder, RotateCcw, ShieldCheck, Trash2, type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface DocumentItem { id: number; judul: string; tipe: string; purge_after: string | null; }
interface FolderItem { id: number; name: string; purge_after: string | null; }
interface Props { documents: DocumentItem[]; folders: FolderItem[]; }

export default function Trash({ documents, folders }: Props) {
    const empty = documents.length === 0 && folders.length === 0;
    const jumlahItem = documents.length + folders.length;

    return (
        <AppLayout title="Sampah">
            <div className="space-y-5">
                <section className="flex flex-col gap-4 rounded-card border border-warning/20 bg-warning-soft/50 p-4 sm:flex-row sm:items-center">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-warning-soft text-warning-strong"><Trash2 className="size-5" aria-hidden /></span>
                    <div className="min-w-0 flex-1"><h2 className="font-semibold text-ink">Item di Sampah disimpan selama 30 hari</h2><p className="mt-1 text-sm text-ink-muted">Pulihkan dokumen atau folder sebelum tanggal penghapusan permanen. Setelah lewat, berkas tidak dapat dikembalikan.</p></div>
                    {!empty && <Badge variant="warning" className="self-start sm:self-auto">{jumlahItem} item</Badge>}
                </section>

                {empty ? <EmptyState icon={Trash2} title="Sampah kosong" description="Dokumen dan folder yang dipindahkan ke Sampah akan berada di sini selama 30 hari." /> : <div className="space-y-5">
                    {folders.length > 0 && <section><SectionHeading icon={Folder} label="Folder" count={folders.length} /><div className="grid gap-3 lg:grid-cols-2">{folders.map((folder) => <TrashItem key={folder.id} name={folder.name} purgeAfter={folder.purge_after} icon={<Folder className="size-5 text-brand-700" aria-hidden />} kind="Folder" onRestore={() => router.patch(`/folders/${folder.id}/restore`)} />)}</div></section>}
                    {documents.length > 0 && <section><SectionHeading icon={FileText} label="Dokumen" count={documents.length} /><div className="grid gap-3 lg:grid-cols-2">{documents.map((document) => <TrashItem key={document.id} name={document.judul} purgeAfter={document.purge_after} icon={<FileTypeBadge mime={document.tipe} size="md" />} kind="Dokumen" onRestore={() => router.patch(`/documents/${document.id}/restore-trash`)} />)}</div></section>}
                </div>}
            </div>
        </AppLayout>
    );
}

function SectionHeading({ icon: Icon, label, count }: { icon: LucideIcon; label: string; count: number; }) {
    return <div className="mb-3 flex items-center gap-2"><Icon className="size-4 text-ink-muted" aria-hidden /><h2 className="text-sm font-semibold text-ink">{label}</h2><Badge variant="neutral" size="sm">{count}</Badge></div>;
}

function TrashItem({ name, purgeAfter, icon, kind, onRestore }: { name: string; purgeAfter: string | null; icon: ReactNode; kind: string; onRestore: () => void; }) {
    return <article className="flex min-w-0 items-center gap-3 rounded-card border border-line bg-surface p-4 transition-shadow hover:shadow-pop"><span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-sunken">{icon}</span><div className="min-w-0 flex-1"><div className="flex min-w-0 items-center gap-2"><p className="truncate text-sm font-medium text-ink">{name}</p><Badge variant="neutral" size="sm">{kind}</Badge></div><p className="mt-1 flex items-center gap-1 text-xs text-ink-muted"><CalendarClock className="size-3.5 shrink-0" aria-hidden />Dihapus permanen {formatTanggalPanjang(purgeAfter)}</p><p className="mt-1 flex items-center gap-1 text-xs text-ink-muted"><ShieldCheck className="size-3.5 shrink-0" aria-hidden />{sisaRetensi(purgeAfter)}</p></div><Button variant="secondary" size="sm" icon={RotateCcw} onClick={onRestore}><span className="hidden sm:inline">Pulihkan</span><span className="sr-only sm:hidden">Pulihkan {name}</span></Button></article>;
}

function sisaRetensi(purgeAfter: string | null): string {
    if (!purgeAfter) return 'Tanggal penghapusan belum tersedia';

    const days = Math.max(0, Math.ceil((new Date(purgeAfter).getTime() - Date.now()) / 86_400_000));

    return days === 0 ? 'Dihapus hari ini' : `Tersisa ${days} hari untuk dipulihkan`;
}
