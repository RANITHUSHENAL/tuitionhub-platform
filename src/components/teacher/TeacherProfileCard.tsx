import React, { useState } from 'react';
import { PresentationIcon } from 'lucide-react';
import { Button } from '../ui/Button';
import { CopyButton } from '../ui/CopyButton';
import { Label, TextInput } from '../ui/Field';
import { FieldError, FormAlert } from '../ui/FormMessage';
import { useAuth } from '../../contexts/AuthContext';
import { useApiErrors } from '../../hooks/useApiErrors';
import { fieldErrorsOf } from '../../lib/apiErrors';
import { teacherApi, type Teacher } from '../../lib/api';

/**
 * The caller's own teacher profile (contract §6.1). Creating one grants no access: an institute
 * admin assigns the teacher by this profile's ID (§6.2), so the ID is shown with a copy button.
 * There is no read endpoint, so an existing profile is fetched with an empty update, which
 * changes nothing and returns the stored profile.
 */
export function TeacherProfileCard() {
  const { account, refreshSession } = useAuth();
  const describe = useApiErrors();
  const [teacher, setTeacher] = useState<Teacher | null>(null);
  const [displayName, setDisplayName] = useState(account?.user.name ?? '');
  const [headline, setHeadline] = useState('');
  const [years, setYears] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const hasProfile = account?.has_teacher_profile ?? false;

  const run = async (action: () => Promise<Teacher>) => {
    setBusy(true);
    setMessage(null);
    try {
      setTeacher(await action());
      setErrors({});
      if (!hasProfile) await refreshSession();
    } catch (error) {
      setErrors(fieldErrorsOf(error));
      setMessage(describe(error));
    } finally {
      setBusy(false);
    }
  };

  const create = (e: React.FormEvent) => {
    e.preventDefault();
    const local: Record<string, string> = {};
    const yearsValue = years.trim() === '' ? undefined : Number(years);
    if (displayName.trim().length < 2) local.display_name = 'Enter the name students will see (at least 2 characters).';
    if (yearsValue !== undefined && (!Number.isInteger(yearsValue) || yearsValue < 0 || yearsValue > 70)) {
      local.years_experience = 'Enter a whole number of years from 0 to 70.';
    }
    setErrors(local);
    if (Object.keys(local).length > 0) return;

    void run(() =>
    teacherApi.saveMyProfile({
      display_name: displayName.trim(),
      ...(headline.trim() ? { headline: headline.trim() } : {}),
      ...(yearsValue !== undefined ? { years_experience: yearsValue } : {})
    })
    );
  };

  return (
    <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
      <div className="flex items-start gap-3">
        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-50 text-forest">
          <PresentationIcon className="h-5 w-5" aria-hidden="true" />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="text-h3 text-ink">Teacher profile</h2>
          <p className="mt-1 text-small text-ink-soft">
            Institutes assign teachers by their teacher ID. A profile on its own gives no access to any
            institute or class.
          </p>
        </div>
      </div>

      <div className="mt-4 space-y-4">
        {teacher ?
        <div className="rounded-xl border border-line bg-surface-muted px-4 py-3.5">
            <p className="text-small font-medium text-ink">{teacher.display_name}</p>
            {teacher.headline ? <p className="text-caption text-ink-soft">{teacher.headline}</p> : null}
            <p className="mt-3 text-caption uppercase tracking-wide text-ink-faint">Your teacher ID</p>
            <p className="mt-1 break-all font-mono text-small text-ink">{teacher.uuid}</p>
            <div className="mt-3">
              <CopyButton value={teacher.uuid} label="Copy teacher ID" />
            </div>
            <p className="mt-2 text-caption text-ink-soft">
              Share it only with your institute’s admin, who uses it to assign you.
            </p>
          </div> :
        hasProfile ?
        <Button variant="secondary" disabled={busy} onClick={() => void run(() => teacherApi.saveMyProfile({}))}>
            {busy ? 'Loading…' : 'Show my teacher ID'}
          </Button> :

        <form onSubmit={create} className="space-y-4" noValidate>
            <div>
              <Label htmlFor="tp-name">Name shown to students</Label>
              <TextInput
              id="tp-name"
              value={displayName}
              maxLength={120}
              onChange={(e) => setDisplayName(e.target.value)}
              placeholder="Mr. Kasun Silva"
              aria-invalid={errors.display_name ? true : undefined}
              aria-describedby={errors.display_name ? 'tp-name-error' : undefined} />

              <FieldError id="tp-name-error" message={errors.display_name} />
            </div>
            <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
              <div>
                <Label htmlFor="tp-headline" hint="Optional">Headline</Label>
                <TextInput
                id="tp-headline"
                value={headline}
                maxLength={150}
                onChange={(e) => setHeadline(e.target.value)}
                placeholder="A/L Combined Maths"
                aria-invalid={errors.headline ? true : undefined}
                aria-describedby={errors.headline ? 'tp-headline-error' : undefined} />

                <FieldError id="tp-headline-error" message={errors.headline} />
              </div>
              <div>
                <Label htmlFor="tp-years" hint="Optional">Years teaching</Label>
                <TextInput
                id="tp-years"
                inputMode="numeric"
                value={years}
                onChange={(e) => setYears(e.target.value.replace(/[^\d]/g, '').slice(0, 2))}
                aria-invalid={errors.years_experience ? true : undefined}
                aria-describedby={errors.years_experience ? 'tp-years-error' : undefined} />

                <FieldError id="tp-years-error" message={errors.years_experience} />
              </div>
            </div>
            <Button type="submit" disabled={busy}>
              {busy ? 'Creating…' : 'Create teacher profile'}
            </Button>
          </form>
        }
        <FormAlert message={message} />
      </div>
    </section>);

}
