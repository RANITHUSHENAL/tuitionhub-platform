import React, { useCallback, useEffect, useState } from 'react';
import { SearchIcon, UserPlusIcon, UsersIcon } from 'lucide-react';
import { InstituteShell } from '../../components/institute/InstituteShell';
import { InvitationCodeDialog, type IssuedInvitation } from '../../components/institute/InvitationCodeDialog';
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
import { useLookups } from '../../hooks/useLookups';
import { fieldErrorsOf } from '../../lib/apiErrors';
import {
  ApiError,
  guardianApi,
  studentApi,
  type GuardianRelationship,
  type Page,
  type Student } from
'../../lib/api';
import { toSriLankanMobile } from '../../lib/phone';
import { formatDate, formatDateTime, todayInColombo } from '../../utils/dates';
import { initialsOf } from '../../utils/format';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const relationships: {value: GuardianRelationship;label: string;}[] = [
{ value: 'mother', label: 'Mother' },
{ value: 'father', label: 'Father' },
{ value: 'guardian', label: 'Guardian' },
{ value: 'aunt', label: 'Aunt' },
{ value: 'uncle', label: 'Uncle' }];


/** What the screen keeps about an invitation it created. Never the code. */
interface SentInvitation {
  uuid: string;
  phoneMasked: string;
  relationship: GuardianRelationship;
  expiresAt: string;
  status: 'pending' | 'revoked';
}

/**
 * Students managed by this institute (contract §8.1, §8.2) and their parent invitations (§9.1,
 * §9.3, §9.4). Only real API data is shown. The one-time invitation code is held only while its
 * dialog is open.
 */
export function InstituteStudents() {
  const { user } = useAuth();
  const describe = useApiErrors();
  const { lookups } = useLookups();
  const membership = user?.institute;
  const uuid = membership?.uuid;
  const isAdmin = membership?.role === 'institute_admin';
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [grade, setGrade] = useState('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<Page<Student> | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reload, setReload] = useState(0);
  const [addOpen, setAddOpen] = useState(false);
  const [selected, setSelected] = useState<Student | null>(null);
  const [sent, setSent] = useState<Record<string, SentInvitation[]>>({});
  const [issued, setIssued] = useState<IssuedInvitation | null>(null);
  const [toast, setToast] = useState<string | null>(null);

  // Wait for typing to pause before searching.
  useEffect(() => {
    const id = window.setTimeout(() => {
      setQ(search.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(id);
  }, [search]);

  // A different institute (or account) starts from a clean slate.
  useEffect(() => {
    setSelected(null);
    setSent({});
    setIssued(null);
  }, [uuid]);

  useEffect(() => {
    if (!uuid) return;
    let alive = true;
    setResult(null);
    setLoadError(null);
    studentApi.
    list(uuid, { q: q || undefined, grade_code: grade || undefined, page }).
    then((data) => alive && setResult(data)).
    catch((error: unknown) => alive && setLoadError(describe(error)));
    return () => {
      alive = false;
    };
  }, [uuid, q, grade, page, reload, describe]);

  const closeCode = useCallback(() => setIssued(null), []);
  const filtered = q !== '' || grade !== '';

  return (
    <InstituteShell
      title="Students"
      subtitle="Students registered by this institute"
      actions={
      <Button size="sm" onClick={() => setAddOpen(true)}>
          <UserPlusIcon className="h-4 w-4" />
          Add student
        </Button>
      }>

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <div className="flex w-full max-w-sm items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5">
          <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
          <input
            value={search}
            maxLength={100}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by name"
            aria-label="Search students by name"
            className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />

        </div>
        <div className="w-48">
          <Label htmlFor="grade-filter">Grade</Label>
          <Select
            id="grade-filter"
            value={grade}
            onChange={(e) => {
              setGrade(e.target.value);
              setPage(1);
            }}>

            <option value="">All grades</option>
            {lookups?.grades.map((g) =>
            <option key={g.code} value={g.code}>{g.name}</option>
            )}
          </Select>
        </div>
      </div>

      {loadError ?
      <ErrorState
        title="Students couldn’t be loaded"
        body={loadError}
        action={<Button variant="secondary" size="sm" onClick={() => setReload((n) => n + 1)}>Try again</Button>} /> :

      !result ?
      <LoadingLine label="Loading students…" /> :
      result.data.length === 0 ?
      filtered ?
      <EmptyState
        icon={<SearchIcon className="h-5 w-5" />}
        title="No students match"
        body="Check the spelling, or clear the search and grade filter."
        action={
        <Button variant="secondary" onClick={() => {setSearch('');setQ('');setGrade('');setPage(1);}}>
                Clear filters
              </Button>
        } /> :


      <EmptyState
        icon={<UsersIcon className="h-5 w-5" />}
        title="No students yet"
        body="Add your first student. You can then invite their parent with a one-time code."
        action={<Button onClick={() => setAddOpen(true)}>Add student</Button>} /> :


      <section className="overflow-x-auto rounded-2xl border border-line bg-white shadow-card">
          <table className="w-full min-w-[44rem] text-left">
            <thead className="border-b border-line bg-surface-muted">
              <tr className="text-caption uppercase tracking-wide text-ink-faint">
                <th scope="col" className="px-6 py-3 font-medium">Student</th>
                <th scope="col" className="px-6 py-3 font-medium">Exam target</th>
                <th scope="col" className="px-6 py-3 font-medium">Medium</th>
                <th scope="col" className="px-6 py-3 font-medium">Parents linked</th>
                <th scope="col" className="px-6 py-3 font-medium"><span className="sr-only">Action</span></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {result.data.map((s) =>
            <tr key={s.uuid} className="text-small">
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      <Avatar initials={initialsOf(s.full_name) || 'S'} size="sm" />
                      <div>
                        <p className="font-medium text-ink">{s.full_name}</p>
                        <p className="text-caption text-ink-soft">{s.grade.name}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 text-ink-soft">{s.exam_target?.name ?? '—'}</td>
                  <td className="px-6 py-4 text-ink-soft">{s.medium.name}</td>
                  <td className="px-6 py-4">
                    <Badge tone={s.active_guardian_count > 0 ? 'success' : 'neutral'}>
                      {s.active_guardian_count > 0 ? `${s.active_guardian_count} linked` : 'None yet'}
                    </Badge>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <Button variant="secondary" size="sm" onClick={() => setSelected(s)}>
                      Open
                    </Button>
                  </td>
                </tr>
            )}
            </tbody>
          </table>
          <Pagination meta={result.meta} onPage={setPage} label="Students" />
        </section>
      }

      {uuid ?
      <Modal open={addOpen} onClose={() => setAddOpen(false)} title="Add a student">
          <AddStudentForm
          instituteUuid={uuid}
          onCreated={(student) => {
            setAddOpen(false);
            setToast(`${student.full_name} added`);
            setReload((n) => n + 1);
          }} />

        </Modal> :
      null}

      <Modal open={selected !== null} onClose={() => setSelected(null)} title={selected?.full_name ?? 'Student'}>
        {selected ?
        <StudentPanel
          key={selected.uuid}
          student={selected}
          isAdmin={isAdmin}
          sent={sent[selected.uuid] ?? []}
          onInvited={(invitation, summary) => {
            setSent((all) => ({ ...all, [selected.uuid]: [summary, ...(all[selected.uuid] ?? [])] }));
            setSelected(null);
            setIssued(invitation);
          }}
          onInvitationRevoked={(invitationUuid) => {
            setSent((all) => ({
              ...all,
              [selected.uuid]: (all[selected.uuid] ?? []).map((i) => i.uuid === invitationUuid ? { ...i, status: 'revoked' } : i)
            }));
            setToast('Invitation revoked');
          }}
          onLinkRevoked={() => {
            setSelected(null);
            setToast('Parent link removed');
            setReload((n) => n + 1);
          }} /> :

        null}
      </Modal>

      {issued ? <InvitationCodeDialog invitation={issued} onClose={closeCode} /> : null}
      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </InstituteShell>);

}

function AddStudentForm({ instituteUuid, onCreated }: {instituteUuid: string;onCreated: (student: Student) => void;}) {
  const describe = useApiErrors();
  const { lookups, loading, failed } = useLookups();
  const today = todayInColombo();
  const [fullName, setFullName] = useState('');
  const [gradeCode, setGradeCode] = useState('');
  const [mediumCode, setMediumCode] = useState('');
  const [examTargetCode, setExamTargetCode] = useState('');
  const [dob, setDob] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const local: Record<string, string> = {};
    const name = fullName.trim().replace(/\s+/g, ' ');
    if (name.length < 2) local.full_name = 'Enter the student’s full name.';
    if (!gradeCode) local.grade_code = 'Choose a grade.';
    if (!mediumCode) local.medium_code = 'Choose a medium.';
    if (dob && dob > today) local.date_of_birth = 'The date of birth can’t be in the future.';
    setErrors(local);
    setMessage(null);
    if (Object.keys(local).length > 0) return;

    setBusy(true);
    try {
      onCreated(
        await studentApi.create(instituteUuid, {
          full_name: name,
          grade_code: gradeCode,
          medium_code: mediumCode,
          exam_target_code: examTargetCode || null,
          date_of_birth: dob || null
        })
      );
    } catch (error) {
      setErrors(fieldErrorsOf(error));
      setMessage(describe(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <div>
        <Label htmlFor="st-name">Full name</Label>
        <TextInput
          id="st-name"
          value={fullName}
          maxLength={170}
          onChange={(e) => setFullName(e.target.value)}
          aria-invalid={errors.full_name ? true : undefined}
          aria-describedby={errors.full_name ? 'st-name-error' : undefined} />

        <FieldError id="st-name-error" message={errors.full_name} />
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="st-grade">Grade</Label>
          <Select
            id="st-grade"
            value={gradeCode}
            disabled={loading || failed}
            onChange={(e) => setGradeCode(e.target.value)}
            aria-invalid={errors.grade_code ? true : undefined}
            aria-describedby={errors.grade_code ? 'st-grade-error' : undefined}>

            <option value="">{loading ? 'Loading…' : 'Choose a grade'}</option>
            {lookups?.grades.map((g) =>
            <option key={g.code} value={g.code}>{g.name}</option>
            )}
          </Select>
          <FieldError id="st-grade-error" message={errors.grade_code} />
        </div>
        <div>
          <Label htmlFor="st-medium">Medium</Label>
          <Select
            id="st-medium"
            value={mediumCode}
            disabled={loading || failed}
            onChange={(e) => setMediumCode(e.target.value)}
            aria-invalid={errors.medium_code ? true : undefined}
            aria-describedby={errors.medium_code ? 'st-medium-error' : undefined}>

            <option value="">{loading ? 'Loading…' : 'Choose a medium'}</option>
            {lookups?.mediums.map((m) =>
            <option key={m.code} value={m.code}>{m.name}</option>
            )}
          </Select>
          <FieldError id="st-medium-error" message={errors.medium_code} />
        </div>
        <div>
          <Label htmlFor="st-exam" hint="Optional">Exam target</Label>
          <Select
            id="st-exam"
            value={examTargetCode}
            disabled={loading || failed}
            onChange={(e) => setExamTargetCode(e.target.value)}
            aria-invalid={errors.exam_target_code ? true : undefined}
            aria-describedby={errors.exam_target_code ? 'st-exam-error' : undefined}>

            <option value="">None</option>
            {lookups?.exam_targets.map((t) =>
            <option key={t.code} value={t.code}>{t.name}</option>
            )}
          </Select>
          <FieldError id="st-exam-error" message={errors.exam_target_code} />
        </div>
        <div>
          <Label htmlFor="st-dob" hint="Optional">Date of birth</Label>
          <TextInput
            id="st-dob"
            type="date"
            max={today}
            value={dob}
            onChange={(e) => setDob(e.target.value)}
            aria-invalid={errors.date_of_birth ? true : undefined}
            aria-describedby={errors.date_of_birth ? 'st-dob-error' : undefined} />

          <FieldError id="st-dob-error" message={errors.date_of_birth} />
        </div>
      </div>
      {failed ? <FormAlert message="Grades and mediums couldn’t be loaded. Refresh the page to try again." /> : null}
      <p className="text-caption text-ink-faint">Use fictional names in this demo. No ID numbers or photos are collected.</p>
      <FormAlert message={message} />
      <Button type="submit" className="w-full" disabled={busy || loading}>
        {busy ? 'Adding…' : 'Add student'}
      </Button>
    </form>);

}

function StudentPanel({
  student,
  isAdmin,
  sent,
  onInvited,
  onInvitationRevoked,
  onLinkRevoked









}: {student: Student;isAdmin: boolean;sent: SentInvitation[];onInvited: (invitation: IssuedInvitation, summary: SentInvitation) => void;onInvitationRevoked: (invitationUuid: string) => void;onLinkRevoked: () => void;}) {
  return (
    <div className="space-y-5">
      <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-small">
        <Detail label="Grade" value={student.grade.name} />
        <Detail label="Medium" value={student.medium.name} />
        <Detail label="Exam target" value={student.exam_target?.name ?? '—'} />
        <Detail label="Date of birth" value={formatDate(student.date_of_birth)} />
        <Detail label="Parents linked" value={String(student.active_guardian_count)} />
        <Detail label="Added" value={formatDate(student.created_at)} />
      </dl>

      <InviteParentForm student={student} onInvited={onInvited} />

      {sent.length > 0 ?
      <SentInvitations invitations={sent} onRevoked={onInvitationRevoked} /> :
      null}

      {isAdmin ?
      <RemoveLinkForm studentUuid={student.uuid} onRevoked={onLinkRevoked} /> :

      <p className="text-caption text-ink-faint">Only institute admins can remove a parent link.</p>
      }
    </div>);

}

function InviteParentForm({
  student,
  onInvited




}: {student: Student;onInvited: (invitation: IssuedInvitation, summary: SentInvitation) => void;}) {
  const describe = useApiErrors();
  const [phone, setPhone] = useState('');
  const [relationship, setRelationship] = useState<GuardianRelationship>('mother');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const e164 = toSriLankanMobile(phone);
    setMessage(null);
    if (!e164) {
      setErrors({ guardian_phone: 'Enter the parent’s Sri Lankan mobile number, for example 077 123 4567.' });
      return;
    }

    setErrors({});
    setBusy(true);
    try {
      const created = await guardianApi.invite(student.uuid, e164, relationship);
      setPhone('');
      onInvited(
        { code: created.code, studentName: created.student.full_name, phoneMasked: created.guardian_phone_masked, expiresAt: created.expires_at },
        { uuid: created.uuid, phoneMasked: created.guardian_phone_masked, relationship: created.relationship, expiresAt: created.expires_at, status: 'pending' }
      );
    } catch (error) {
      setErrors(fieldErrorsOf(error));
      setMessage(describe(error));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-3 rounded-2xl border border-line p-4" noValidate>
      <h3 className="text-small font-semibold text-ink">Invite a parent</h3>
      <p className="text-caption text-ink-soft">
        We create a one-time code for the parent’s mobile number. You give it to them; they link from their own
        account.
      </p>
      <div className="grid gap-3 sm:grid-cols-[1fr_9rem]">
        <div>
          <Label htmlFor="inv-phone">Parent’s mobile</Label>
          <TextInput
            id="inv-phone"
            inputMode="tel"
            autoComplete="off"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="077 123 4567"
            aria-invalid={errors.guardian_phone ? true : undefined}
            aria-describedby={errors.guardian_phone ? 'inv-phone-error' : undefined} />

          <FieldError id="inv-phone-error" message={errors.guardian_phone} />
        </div>
        <div>
          <Label htmlFor="inv-rel">Relationship</Label>
          <Select
            id="inv-rel"
            value={relationship}
            onChange={(e) => setRelationship(e.target.value as GuardianRelationship)}
            aria-invalid={errors.relationship ? true : undefined}
            aria-describedby={errors.relationship ? 'inv-rel-error' : undefined}>

            {relationships.map((r) =>
            <option key={r.value} value={r.value}>{r.label}</option>
            )}
          </Select>
          <FieldError id="inv-rel-error" message={errors.relationship} />
        </div>
      </div>
      <FormAlert message={message} />
      <Button type="submit" size="sm" disabled={busy}>
        {busy ? 'Creating code…' : 'Create invitation code'}
      </Button>
    </form>);

}

function SentInvitations({ invitations, onRevoked }: {invitations: SentInvitation[];onRevoked: (uuid: string) => void;}) {
  const describe = useApiErrors();
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const revoke = async (uuid: string) => {
    setBusy(uuid);
    setMessage(null);
    try {
      await guardianApi.revokeInvitation(uuid);
      onRevoked(uuid);
    } catch (error) {
      setMessage(describe(error));
    } finally {
      setBusy(null);
    }
  };

  return (
    <section className="space-y-2">
      <h3 className="text-small font-semibold text-ink">Invitations you created here</h3>
      <ul className="space-y-2">
        {invitations.map((i) =>
        <li key={i.uuid} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-3.5 py-2.5">
            <div className="min-w-0">
              <p className="text-small text-ink">{i.phoneMasked} · {i.relationship}</p>
              <p className="text-caption text-ink-soft">
                {i.status === 'revoked' ? 'Revoked' : `Pending · expires ${formatDateTime(i.expiresAt)}`}
              </p>
            </div>
            {i.status === 'pending' ?
          <Button variant="danger" size="sm" disabled={busy === i.uuid} onClick={() => void revoke(i.uuid)}>
                {busy === i.uuid ? 'Revoking…' : 'Revoke'}
              </Button> :
          <Badge tone="neutral">Revoked</Badge>}
          </li>
        )}
      </ul>
      <FormAlert message={message} />
      <p className="text-caption text-ink-faint">
        Only invitations created in this browser tab are listed; the code itself is never shown again.
      </p>
    </section>);

}

function RemoveLinkForm({ studentUuid, onRevoked }: {studentUuid: string;onRevoked: () => void;}) {
  const describe = useApiErrors();
  const [guardianUuid, setGuardianUuid] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [error, setError] = useState<string | undefined>();
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const check = (e: React.FormEvent) => {
    e.preventDefault();
    setMessage(null);
    if (!UUID_PATTERN.test(guardianUuid.trim())) {
      setError('Paste the parent’s account ID exactly as they shared it.');
      return;
    }
    setError(undefined);
    setConfirming(true);
  };

  const remove = async () => {
    setBusy(true);
    try {
      await guardianApi.revokeLink(studentUuid, guardianUuid.trim());
      onRevoked();
    } catch (err) {
      // A 404 is the same for an unknown account and for no active link (§9.4).
      setMessage(err instanceof ApiError && err.status === 404 ?
      'Not found. No active link matches that account ID.' :
      describe(err));
      setConfirming(false);
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={check} className="space-y-3 rounded-2xl border border-line bg-surface-muted p-4" noValidate>
      <h3 className="text-small font-semibold text-ink">Remove a parent link</h3>
      <p className="text-caption text-ink-soft">
        The parent loses access on their next request. The link’s history is kept. Ask the parent for the
        account ID shown under <strong className="text-ink">Your account</strong>.
      </p>
      <div>
        <Label htmlFor="rm-guardian">Parent’s account ID</Label>
        <TextInput
          id="rm-guardian"
          value={guardianUuid}
          onChange={(e) => {setGuardianUuid(e.target.value);setConfirming(false);}}
          autoComplete="off"
          spellCheck={false}
          className="font-mono"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? 'rm-guardian-error' : undefined} />

        <FieldError id="rm-guardian-error" message={error} />
      </div>
      <FormAlert message={message} />
      {confirming ?
      <div className="flex flex-wrap gap-2">
          <Button type="button" variant="danger" size="sm" disabled={busy} onClick={() => void remove()}>
            {busy ? 'Removing…' : 'Yes, remove this link'}
          </Button>
          <Button type="button" variant="secondary" size="sm" disabled={busy} onClick={() => setConfirming(false)}>
            Cancel
          </Button>
        </div> :

      <Button type="submit" variant="danger" size="sm">
          Remove link…
        </Button>
      }
    </form>);

}

function Detail({ label, value }: {label: string;value: string;}) {
  return (
    <div>
      <dt className="text-caption uppercase tracking-wide text-ink-faint">{label}</dt>
      <dd className="mt-0.5 text-ink">{value}</dd>
    </div>);

}
