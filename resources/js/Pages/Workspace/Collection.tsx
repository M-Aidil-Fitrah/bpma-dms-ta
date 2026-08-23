import { WorkspaceDocumentCard, type WorkspaceDocument } from '@/Components/domain/WorkspaceDocumentCard';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Card } from '@/Components/ui/Card';
import { ViewToggle, type ModeTampilan } from '@/Components/data/ViewToggle';
import { AppLayout } from '@/Layouts/AppLayout';
import { Clock3 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Props { title: string; documents: WorkspaceDocument[]; }

export default function Collection({ title, documents }: Props) {
    const { t } = useTranslation(['workspace', 'common']);
    const [mode, setMode] = useState<ModeTampilan>('grid');

    return <AppLayout title={title} actions={<ViewToggle nilai={mode} onChange={setMode} labels={{ tabel: t('workspace:collection.viewToggle.tabel'), grid: t('workspace:collection.viewToggle.grid') }} />}>{documents.length === 0 ? <EmptyState icon={Clock3} title={t('workspace:collection.kosong.judul')} description={t('workspace:collection.kosong.deskripsi')} /> : mode === 'grid' ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{documents.map((document) => <WorkspaceDocumentCard key={document.id} document={document} mode="grid" />)}</div> : <Card><ul className="divide-y divide-line">{documents.map((document) => <li key={document.id}><WorkspaceDocumentCard document={document} /></li>)}</ul></Card>}</AppLayout>;
}
