import { useEffect, useState } from 'react';
import { PencilIcon } from 'lucide-react';
import { InstituteShell } from '../../components/institute/InstituteShell';
import { InstituteForm } from '../../components/institute/InstituteForm';
import { Badge } from '../../components/ui/Badge';
import { Button, LinkButton } from '../../components/ui/Button';
import { FormAlert, LoadingLine } from '../../components/ui/FormMessage';
import { ErrorState } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import { useApiErrors } from '../../hooks/useApiErrors';
import { instituteApi, type Institute, type InstituteInput } from '../../lib/api';
import { formatSriLankanMobile } from '../../lib/phone';
import { formatDate } from '../../utils/dates';

/**
 * Institute details from GET /institutes/{uuid} (§5.4). Only an admin of an active institute may
 * edit them (PATCH, §5.5); the button is a hint and the API decides. `status` is never sent.
 */
export function InstituteProfile() {
  const { user, refreshSession } = useAuth();
  const describe = useApiErrors();
  const membership = user?.institute;
  const uuid = membership?.uuid;
  const isAdmin = membership?.role === 'institute_admin';
  const [institute, setInstitute] = useState<Institute | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reload, setReload] = useState(0);
  const [editing, setEditing] = useState(false);
  const [saved, setSaved] = useState<string | null>(null);

  useEffect(() => {
    if (!uuid) return;
    let alive = true;
    // A different institute (or account) never shows the previous one's details.
    setInstitute(null);
    setLoadError(null);
    setEditing(false);
    setSaved(null);
    instituteApi.
    get(uuid).
    then((data) => alive && setInstitute(data)).
    catch((error: unknown) => alive && setLoadError(describe(error)));
    return () => {
      alive = false;
    };
  }, [uuid, reload, describe]);

  const save = async (input: InstituteInput) => {
    if (!uuid) return;
    const updated = await instituteApi.update(uuid, input);
    setInstitute(updated);
    setEditing(false);
    setSaved('Institute details saved.');
    // The name appears in the session's roles; re-read it so menus show the new name.
    if (updated.name !== membership?.name) void refreshSession();
  };

  const suspended = institute?.status === 'suspended';

  return (
    <InstituteShell
      title={institute?.name ?? membership?.name ?? 'Institute'}
      subtitle="Institute profile"
      actions={
      institute && isAdmin && !suspended && !editing ?
      <Button variant="secondary" size="sm" onClick={() => {setEditing(true);setSaved(null);}}>
            <PencilIcon className="h-4 w-4" />
            Edit details
          </Button> :
      undefined
      }>

      <div className="grid max-w-5xl gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div className="space-y-4">
          {loadError ?
          <ErrorState
            title="Institute details couldn’t be loaded"
            body={loadError}
            action={<Button variant="secondary" size="sm" onClick={() => setReload((n) => n + 1)}>Try again</Button>} /> :

          !institute ?
          <LoadingLine label="Loading institute details…" /> :
          editing ?
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <h2 className="mb-4 text-h3 text-ink">Edit details</h2>
              <InstituteForm
              initial={institute}
              submitLabel="Save changes"
              busyLabel="Saving…"
              onSubmit={save}
              onCancel={() => setEditing(false)} />

            </section> :

          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-h3 text-ink">Details</h2>
                <Badge tone={suspended ? 'danger' : 'success'}>{suspended ? 'Suspended' : 'Active'}</Badge>
              </div>
              <dl className="mt-4 grid gap-x-6 gap-y-4 text-small sm:grid-cols-2">
                <Detail label="District" value={institute.district} />
                <Detail label="City" value={institute.city} />
                <Detail label="Contact mobile" value={institute.contact_phone ? formatSriLankanMobile(institute.contact_phone) : null} />
                <Detail label="Contact email" value={institute.contact_email} />
                <Detail label="Business registration" value={institute.business_reg_no} />
                <Detail label="Registered" value={formatDate(institute.created_at)} />
              </dl>
              {institute.description ?
            <p className="mt-4 whitespace-pre-line border-t border-line pt-4 text-small text-ink-soft">
                  {institute.description}
                </p> :
            null}
            </section>
          }
          <FormAlert tone="success" message={saved} />
          {suspended ?
          <FormAlert message="This institute is suspended. Its records can still be viewed, but changes are not allowed." /> :
          null}
          {institute && !isAdmin ?
          <p className="text-caption text-ink-soft">Only institute admins can change these details.</p> :
          null}
        </div>

        <aside className="space-y-4">
          <section className="rounded-2xl border border-line bg-surface-muted p-5">
            <h2 className="text-h3 text-ink">Next steps</h2>
            <ul className="mt-3 space-y-3 text-small text-ink-soft">
              <li>Assign teachers using the teacher ID from their own TuitionHub profile.</li>
              <li>Register students, then invite each parent with a one-time code.</li>
            </ul>
            <div className="mt-4 flex flex-wrap gap-2">
              <LinkButton to="/institute/teachers" size="sm" variant="secondary">Teachers</LinkButton>
              <LinkButton to="/institute/students" size="sm" variant="secondary">Students</LinkButton>
            </div>
          </section>
        </aside>
      </div>
    </InstituteShell>);

}

function Detail({ label, value }: {label: string;value: string | null;}) {
  return (
    <div>
      <dt className="text-caption uppercase tracking-wide text-ink-faint">{label}</dt>
      <dd className="mt-0.5 break-words text-ink">{value || '—'}</dd>
    </div>);

}
