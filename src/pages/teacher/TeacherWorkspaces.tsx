import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BuildingIcon, CheckIcon, ClockIcon, PlusIcon, PresentationIcon } from 'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { InstituteLinkForm } from '../../components/teacher/InstituteLinkForm';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Toast } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import type { TeachingWorkspace } from '../../types';

export function TeacherWorkspaces() {
  const { user, update, activeWorkspace, switchWorkspace } = useAuth();
  const navigate = useNavigate();
  const [linkOpen, setLinkOpen] = useState(false);
  const [toast, setToast] = useState<string | null>(null);
  const workspaces = user?.workspaces ?? [];
  const hasIndependent = workspaces.some((w) => w.kind === 'independent');

  const setAll = (ws: TeachingWorkspace[]) => update({ workspaces: ws });

  return (
    <TeacherShell title="Workspaces" subtitle="Where you teach. Each workspace only shows its own classes and students.">
      <div className="grid max-w-4xl gap-6">
        <ul className="space-y-3">
          {workspaces.map((w) =>
          <li key={w.id} className="flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-white p-5 shadow-card">
              <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-forest-50 text-forest">
                {w.kind === 'independent' ? <PresentationIcon className="h-5 w-5" /> : <BuildingIcon className="h-5 w-5" />}
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-small font-medium text-ink">{w.name}</p>
                <p className="text-caption text-ink-soft">
                  {w.kind === 'independent' ?
                'Independent — your classes, students, fees' :
                w.status === 'pending' ?
                'Request sent — the institute has not approved yet' :
                'Institute teacher — assigned classes only'}
                </p>
              </div>
              {w.status === 'pending' ?
            <div className="flex items-center gap-2">
                  <Badge tone="info">
                    <ClockIcon className="h-3 w-3" /> Awaiting approval
                  </Badge>
                  <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  setAll(workspaces.map((x) => x.id === w.id ? { ...x, status: 'active', detail: 'Institute teacher' } : x));
                  setToast(`${w.name} approved you`);
                }}>
                
                    Prototype: approve
                  </Button>
                </div> :
            w.id === activeWorkspace?.id ?
            <Badge tone="success">
                  <CheckIcon className="h-3 w-3" /> Current
                </Badge> :

            <Button
              variant="secondary"
              size="sm"
              onClick={() => {
                switchWorkspace(w.id);
                navigate('/teacher');
              }}>
              
                  Switch to this
                </Button>
            }
            </li>
          )}
        </ul>

        <div className="flex flex-wrap gap-3">
          <Button variant="secondary" onClick={() => setLinkOpen(true)}>
            <BuildingIcon className="h-4 w-4" />
            Join an institute
          </Button>
          {!hasIndependent ?
          <Button
            variant="secondary"
            onClick={() => {
              setAll([
              ...workspaces,
              { id: 'ws-independent', kind: 'independent', name: 'Independent classes', detail: 'Your own classes', status: 'active' }]
              );
              setToast('Independent workspace created');
            }}>
            
              <PlusIcon className="h-4 w-4" />
              Start teaching independently
            </Button> :
          null}
        </div>

        <section className="rounded-2xl border border-line bg-surface-muted p-5">
          <h2 className="text-h3 text-ink">What each workspace can see</h2>
          <div className="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
              <p className="text-small font-medium text-ink">Institute teacher</p>
              <p className="mt-1 text-caption text-ink-soft">
                Your assigned classes, their students, attendance, marks and exams. Not
                institute fees, settlements, other teachers’ classes, or guardian contact details.
              </p>
            </div>
            <div>
              <p className="text-small font-medium text-ink">Independent</p>
              <p className="mt-1 text-caption text-ink-soft">
                Everything for classes you create — including fees and payments, settled to you.
              </p>
            </div>
          </div>
        </section>
      </div>

      <Modal open={linkOpen} onClose={() => setLinkOpen(false)} title="Join an institute">
        <InstituteLinkForm
          onRequested={(n) => {
            setAll([
            ...workspaces,
            { id: `ws-${Date.now()}`, kind: 'institute', name: n, detail: 'Waiting for approval', status: 'pending' }]
            );
            setLinkOpen(false);
            setToast(`Request sent to ${n}`);
          }} />
        
      </Modal>
      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </TeacherShell>);

}