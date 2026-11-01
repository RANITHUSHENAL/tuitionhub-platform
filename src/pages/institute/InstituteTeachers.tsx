import React, { useEffect, useState } from 'react';
import { UserPlusIcon, UsersIcon } from 'lucide-react';
import { InstituteShell } from '../../components/institute/InstituteShell';
import { Avatar } from '../../components/ui/Avatar';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { FieldError, FormAlert, LoadingLine } from '../../components/ui/FormMessage';
import { Pagination } from '../../components/ui/Pagination';
import { EmptyState, ErrorState, Toast } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import { useApiErrors } from '../../hooks/useApiErrors';
import { fieldErrorsOf } from '../../lib/apiErrors';
import { teacherApi, type Page, type TeacherAssignment } from '../../lib/api';
import { formatDate, todayInColombo } from '../../utils/dates';
import { initialsOf } from '../../utils/format';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

type StatusFilter = '' | 'active' | 'former';

/**
 * The institute's teachers from GET /institutes/{uuid}/teachers (§6.3). Admins assign a teacher by
 * the teacher ID from that teacher's own profile (§6.2); staff can only view the list.
 */
export function InstituteTeachers() {
  const { user } = useAuth();
  const describe = useApiErrors();
  const membership = user?.institute;
  const uuid = membership?.uuid;
  const isAdmin = membership?.role === 'institute_admin';
  const [status, setStatus] = useState<StatusFilter>('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<Page<TeacherAssignment> | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reload, setReload] = useState(0);
  const [assignOpen, setAssignOpen] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  useEffect(() => {
    if (!uuid) return;
    let alive = true;
    setResult(null);
    setLoadError(null);
    teacherApi.
    list(uuid, { status: status || undefined, page }).
    then((data) => alive && setResult(data)).
    catch((error: unknown) => alive && setLoadError(describe(error)));
    return () => {
      alive = false;
    };
  }, [uuid, status, page, reload, describe]);

  return (
    <InstituteShell
      title="Teachers"
      subtitle="Teachers assigned to this institute"
      actions={
      isAdmin ?
      <Button size="sm" onClick={() => setAssignOpen(true)}>
            <UserPlusIcon className="h-4 w-4" />
            Assign teacher
          </Button> :
      undefined
      }>

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-4">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-48">
              <Label htmlFor="teacher-status">Show</Label>
              <Select
                id="teacher-status"
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value as StatusFilter);
                  setPage(1);
                }}>

                <option value="">All teachers</option>
                <option value="active">Active</option>
                <option value="former">Former</option>
              </Select>
            </div>
          </div>

          {loadError ?
          <ErrorState
            title="Teachers couldn’t be loaded"
            body={loadError}
            action={<Button variant="secondary" size="sm" onClick={() => setReload((n) => n + 1)}>Try again</Button>} /> :

          !result ?
          <LoadingLine label="Loading teachers…" /> :
          result.data.length === 0 ?
          <EmptyState
            icon={<UsersIcon className="h-5 w-5" />}
            title={status ? 'No teachers with this status' : 'No teachers yet'}
            body={
            isAdmin ?
            'Ask a teacher to create a teacher profile from their TuitionHub account and send you their teacher ID, then assign them here.' :
            'Teachers appear here once an institute admin assigns them.'
            } /> :


          <section className="overflow-x-auto rounded-2xl border border-line bg-white shadow-card">
              <table className="w-full min-w-[36rem] text-left">
                <thead className="border-b border-line bg-surface-muted">
                  <tr className="text-caption uppercase tracking-wide text-ink-faint">
                    <th scope="col" className="px-5 py-3 font-medium">Teacher</th>
                    <th scope="col" className="px-5 py-3 font-medium">Status</th>
                    <th scope="col" className="px-5 py-3 font-medium">Joined</th>
                    <th scope="col" className="px-5 py-3 font-medium">Left</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line text-small">
                  {result.data.map((a) =>
                <tr key={`${a.teacher.uuid}-${a.joined_on}-${a.status}`}>
                      <td className="px-5 py-3.5">
                        <span className="flex items-center gap-3">
                          <Avatar initials={initialsOf(a.teacher.display_name) || 'T'} size="sm" />
                          <span>
                            <span className="block font-medium text-ink">{a.teacher.display_name}</span>
                            <span className="block text-caption text-ink-soft">
                              {a.teacher.headline ?? `${a.teacher.years_experience} years teaching`}
                            </span>
                          </span>
                        </span>
                      </td>
                      <td className="px-5 py-3.5">
                        <Badge tone={a.status === 'active' ? 'success' : 'neutral'}>
                          {a.status === 'active' ? 'Active' : 'Former'}
                        </Badge>
                      </td>
                      <td className="px-5 py-3.5 text-ink-soft">{formatDate(a.joined_on)}</td>
                      <td className="px-5 py-3.5 text-ink-soft">{formatDate(a.left_on)}</td>
                    </tr>
                )}
                </tbody>
              </table>
              <Pagination meta={result.meta} onPage={setPage} label="Teachers" />
            </section>
          }
        </div>

        <aside>
          <p className="rounded-2xl border border-line bg-surface-muted p-5 text-caption text-ink-soft">
            {isAdmin ?
            'An assignment takes effect immediately. Teachers see only the classes assigned to them, never the whole institute.' :
            'Only institute admins can assign teachers.'}
          </p>
        </aside>
      </div>

      {uuid ?
      <Modal open={assignOpen} onClose={() => setAssignOpen(false)} title="Assign a teacher">
          <AssignTeacherForm
          instituteUuid={uuid}
          onAssigned={(name) => {
            setAssignOpen(false);
            setToast(`${name} is now assigned to this institute`);
            setReload((n) => n + 1);
          }} />

        </Modal> :
      null}
      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </InstituteShell>);

}

function AssignTeacherForm({ instituteUuid, onAssigned }: {instituteUuid: string;onAssigned: (name: string) => void;}) {
  const describe = useApiErrors();
  const today = todayInColombo();
  const [teacherUuid, setTeacherUuid] = useState('');
  const [joinedOn, setJoinedOn] = useState(today);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const id = teacherUuid.trim();
    const local: Record<string, string> = {};
    if (!UUID_PATTERN.test(id)) local.teacher_uuid = 'Paste the teacher ID exactly as the teacher shared it.';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(joinedOn)) local.joined_on = 'Choose the date they started.';else
    if (joinedOn > today) local.joined_on = 'The start date can’t be in the future.';
    setErrors(local);
    setMessage(null);
    if (Object.keys(local).length > 0) return;

    setBusy(true);
    try {
      const assignment = await teacherApi.assign(instituteUuid, id, joinedOn);
      onAssigned(assignment.teacher.display_name);
    } catch (error) {
      setErrors(fieldErrorsOf(error));
      setMessage(describe(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <p className="text-small text-ink-soft">
        The teacher finds their teacher ID under <strong className="text-ink">Your account → Teacher profile</strong>.
      </p>
      <div>
        <Label htmlFor="assign-teacher">Teacher ID</Label>
        <TextInput
          id="assign-teacher"
          value={teacherUuid}
          onChange={(e) => setTeacherUuid(e.target.value)}
          autoComplete="off"
          spellCheck={false}
          className="font-mono"
          aria-invalid={errors.teacher_uuid ? true : undefined}
          aria-describedby={errors.teacher_uuid ? 'assign-teacher-error' : undefined} />

        <FieldError id="assign-teacher-error" message={errors.teacher_uuid} />
      </div>
      <div>
        <Label htmlFor="assign-joined">Started on</Label>
        <TextInput
          id="assign-joined"
          type="date"
          max={today}
          value={joinedOn}
          onChange={(e) => setJoinedOn(e.target.value)}
          className="max-w-xs"
          aria-invalid={errors.joined_on ? true : undefined}
          aria-describedby={errors.joined_on ? 'assign-joined-error' : undefined} />

        <FieldError id="assign-joined-error" message={errors.joined_on} />
      </div>
      <FormAlert message={message} />
      <Button type="submit" className="w-full" disabled={busy}>
        {busy ? 'Assigning…' : 'Assign teacher'}
      </Button>
    </form>);

}
