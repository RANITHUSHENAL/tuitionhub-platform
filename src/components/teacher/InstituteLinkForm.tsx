import React, { useMemo, useState } from 'react';
import { BuildingIcon, CheckCircle2Icon, ClockIcon, SearchIcon } from 'lucide-react';
import { Button } from '../ui/Button';
import { Badge } from '../ui/Badge';
import { TextArea } from '../ui/Field';
import { institutes } from '../../data/directory';

/**
 * Teacher → institute link. A teacher can only REQUEST; the institute approves.
 * Nothing is granted until then.
 */
export function InstituteLinkForm({ onRequested }: {onRequested: (instituteName: string) => void;}) {
  const [query, setQuery] = useState('');
  const [picked, setPicked] = useState<string | null>(null);
  const [note, setNote] = useState('');

  const results = useMemo(
    () =>
    institutes.filter((i) =>
    `${i.name} ${i.city}`.toLowerCase().includes(query.trim().toLowerCase())
    ),
    [query]
  );
  const inst = institutes.find((i) => i.id === picked);

  if (inst) {
    return (
      <div className="space-y-4">
        <div className="flex items-center gap-3 rounded-xl border border-forest bg-forest-50 px-4 py-3">
          <BuildingIcon className="h-5 w-5 text-forest" aria-hidden="true" />
          <div className="min-w-0 flex-1">
            <p className="text-small font-medium text-ink">{inst.name}</p>
            <p className="text-caption text-ink-soft">{inst.city}</p>
          </div>
          <button onClick={() => setPicked(null)} className="text-caption font-medium text-forest">
            Change
          </button>
        </div>
        <div>
          <label htmlFor="link-note" className="mb-1.5 block text-small font-medium text-ink">
            Note to the institute <span className="font-normal text-ink-faint">(optional)</span>
          </label>
          <TextArea
            id="link-note"
            rows={3}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="e.g. I teach the Saturday A/L Physics batch." />
          
        </div>
        <ol className="space-y-2 rounded-xl bg-surface-muted px-4 py-3 text-caption text-ink-soft">
          <li className="flex gap-2">
            <ClockIcon className="h-3.5 w-3.5 shrink-0 translate-y-0.5" /> {inst.name} reviews your request.
          </li>
          <li className="flex gap-2">
            <CheckCircle2Icon className="h-3.5 w-3.5 shrink-0 translate-y-0.5" /> Once approved, they assign
            your classes. You see only those classes and their students.
          </li>
        </ol>
        <Button className="w-full" onClick={() => onRequested(inst.name)}>
          Send request to {inst.name}
        </Button>
      </div>);

  }

  return (
    <div>
      <div className="flex items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5 focus-within:border-forest">
        <SearchIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search institute name or city"
          aria-label="Search institutes"
          className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
        
      </div>
      <ul className="mt-3 max-h-64 space-y-2 overflow-y-auto th-scroll">
        {results.map((i) =>
        <li key={i.id}>
            <button
            onClick={() => setPicked(i.id)}
            className="flex w-full items-center justify-between gap-3 rounded-xl border border-line bg-white px-4 py-3 text-left transition-colors duration-150 ease-out hover:border-forest">
            
              <span className="min-w-0">
                <span className="block truncate text-small font-medium text-ink">{i.name}</span>
                <span className="block truncate text-caption text-ink-soft">
                  {i.city} · {i.subjects.slice(0, 3).join(', ')}
                </span>
              </span>
              {i.verified ? <Badge tone="success">Verified</Badge> : null}
            </button>
          </li>
        )}
        {results.length === 0 ?
        <li className="rounded-xl border border-dashed border-line-strong px-4 py-5 text-center text-caption text-ink-soft">
            Not on TuitionHub yet. You can start as an independent teacher and link later.
          </li> :
        null}
      </ul>
    </div>);

}