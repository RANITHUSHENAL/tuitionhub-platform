import React, { useEffect, useState } from 'react';
import { PresentationIcon } from 'lucide-react';
import { Button } from '../ui/Button';
import { CopyButton } from '../ui/CopyButton';
import { Label, TextInput } from '../ui/Field';
import { FieldError, FormAlert } from '../ui/FormMessage';
import { useAuth } from '../../contexts/AuthContext';
import { useApiErrors } from '../../hooks/useApiErrors';
import { fieldErrorsOf } from '../../lib/apiErrors';
import { ApiError, teacherApi, type Teacher } from '../../lib/api';

type Load = {state: 'loading';} | {state: 'none';} | {state: 'found';teacher: Teacher;} | {state: 'error';message: string;};

/**
 * The caller's own teacher profile. Read with GET /me/teacher-profile (contract §6.5): a 404 means
 * there is no profile yet, so the create form is shown (POST, §6.1). Creating one grants no access:
 * an institute admin assigns the teacher by this profile's ID (§6.2), so the ID has a copy button.
 */
export function TeacherProfileCard() {
  const { account, refreshSession } = useAuth();
  const describe = useApiErrors();
  const [load, setLoad] = useState<Load>({ state: 'loading' });
  const [reload, setReload] = useState(0);
  const [displayName, setDisplayName] = useState(account?.user.name ?? '');
  const [headline, setHeadline] = useState('');
  const [years, setYears] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const accountId = account?.user.uuid;

  useEffect(() => {
    let alive = true;
    setLoad({ state: 'loading' });
    teacherApi.
    myProfile().
    then((teacher) => alive && setLoad({ state: 'found', teacher })).
    catch((error: unknown) => {
      if (!alive) return;
      if (error instanceof ApiError && error.status === 404) setLoad({ state: 'none' });else
      setLoad({ state: 'error', message: describe(error) });
    });
    return () => {
      alive = false;
    };
  }, [accountId, reload, describe]);

  const create = async (e: React.FormEvent) => {
    e.preventDefault();
    const local: Record<string, string> = {};
    const yearsValue = years.trim() === '' ? undefined : Number(years);
    if (displayName.trim().length < 2) local.display_name = 'Enter the name students will see (at least 2 characters).';
    if (yearsValue !== undefined && (!Number.isInteger(yearsValue) || yearsValue < 0 || yearsValue > 70)) {
      local.years_experience = 'Enter a whole number of years from 0 to 70.';
    }
    setErrors(local);
    setMessage(null);
    if (Object.keys(local).length > 0) return;

    setBusy(true);
    try {
      const teacher = await teacherApi.saveMyProfile({
        display_name: displayName.trim(),
        ...(headline.trim() ? { headline: headline.trim() } : {}),
        ...(yearsValue !== undefined ? { years_experience: yearsValue } : {})
      });
      setLoad({ state: 'found', teacher });
      await refreshSession();
    } catch (error) {
      setErrors(fieldErrorsOf(error));
      setMessage(describe(error));
    } finally {
      setBusy(false);
    }
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
        {load.state === 'loading' ?
        <p role="status" className="text-small text-ink-soft">Loading your teacher profile…</p> :
        load.state === 'error' ?
        <div className="space-y-3">
            <FormAlert message={load.message} />
            <Button variant="secondary" size="sm" onClick={() => setReload((n) => n + 1)}>
              Try again
            </Button>
          </div> :
        load.state === 'found' ?
        <div className="rounded-xl border border-line bg-surface-muted px-4 py-3.5">
            <p className="text-small font-medium text-ink">{load.teacher.display_name}</p>
            {load.teacher.headline ? <p className="text-caption text-ink-soft">{load.teacher.headline}</p> : null}
            <p className="mt-3 text-caption uppercase tracking-wide text-ink-faint">Your teacher ID</p>
            <p className="mt-1 break-all font-mono text-small text-ink">{load.teacher.uuid}</p>
            <div className="mt-3">
              <CopyButton value={load.teacher.uuid} label="Copy teacher ID" />
            </div>
            <p className="mt-2 text-caption text-ink-soft">
              Share it only with your institute’s admin, who uses it to assign you.
            </p>
          </div> :

        <form onSubmit={create} className="space-y-4" noValidate>
            <p className="text-small text-ink-soft">You don’t have a teacher profile yet.</p>
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
            <FormAlert message={message} />
            <Button type="submit" disabled={busy}>
              {busy ? 'Creating…' : 'Create teacher profile'}
            </Button>
          </form>
        }
      </div>
    </section>);

}
