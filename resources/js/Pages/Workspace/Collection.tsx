import { WorkspaceDocumentCard, type WorkspaceDocument } from '@/Components/domain/WorkspaceDocumentCard';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AppLayout } from '@/Layouts/AppLayout';
import { Clock3 } from 'lucide-react';

interface Props { title: string; documents: WorkspaceDocument[]; }

export default function Collection({ title, documents }: Props) {
    return <AppLayout title={title}>{documents.length === 0 ? <EmptyState icon={Clock3} title="Belum ada dokumen" description="Dokumen yang Anda tandai atau buka akan muncul di sini." /> : <div className="grid gap-3 lg:grid-cols-2">{documents.map((document) => <WorkspaceDocumentCard key={document.id} document={document} />)}</div>}</AppLayout>;
}
