import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BuildingIcon, CheckIcon, RefreshCwIcon } from 'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { TeacherProfileCard } from '../../components/teacher/TeacherProfileCard';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { useAuth } from '../../contexts/AuthContext';

/**
 * Where this teacher works: one workspace per active assignment in the session's `teaching_at`
 * (contract §4.4). Assignments are made by institute admins with the teacher ID (§6.2), so there
 * is no request-to-join or independent workspace in this version.
 */
export function TeacherWorkspaces() {
  const { user, activeWorkspace, switchWorkspace, refreshSession } = useAuth();
  const navigate = useNavigate();
  const [refreshing, setRefreshing] = useState(false);
  const workspaces = user?.workspaces?.filter((w) => w.status === 'active') ?? [];

  const refresh = async () => {
    setRefreshing(true);
    await refreshSession();
    setRefreshing(false);
  };

  return (
    <TeacherShell
      title="Workspaces"
      subtitle="Institutes you are assigned to. Each workspace only shows its own classes."
      sampleData={false}
      actions={
      <Button variant="secondary" size="sm" disabled={refreshing} onClick={() => void refresh()}>
          <RefreshCwIcon className="h-4 w-4" />
          {refreshing ? 'Refreshing…' : 'Refresh'}
        </Button>
      }>

      <div className="grid max-w-4xl gap-6">
        <ul className="space-y-3">
          {workspaces.map((w) =>
          <li key={w.id} className="flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-white p-5 shadow-card">
              <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-forest-50 text-forest">
                <BuildingIcon className="h-5 w-5" />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-small font-medium text-ink">{w.name}</p>
                <p className="text-caption text-ink-soft">Institute teacher — assigned classes only</p>
              </div>
              {w.id === activeWorkspace?.id ?
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

        <TeacherProfileCard />

        <section className="rounded-2xl border border-line bg-surface-muted p-5">
          <h2 className="text-h3 text-ink">Joining another institute</h2>
          <p className="mt-1 text-small text-ink-soft">
            Send your teacher ID to that institute’s admin. Once they assign you, press Refresh and the new
            workspace appears here. You see only the classes assigned to you there.
          </p>
        </section>
      </div>
    </TeacherShell>);

}
