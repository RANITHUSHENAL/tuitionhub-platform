import React, { useState } from 'react';
import { Button } from '../ui/Button';
import { Label, Select, TextInput } from '../ui/Field';
import { Toast } from '../ui/States';
import { classStudents, type TeacherClass } from '../../data/teaching';

/**
 * Teacher enters marks for one assessment in one of THEIR classes.
 * Save as draft (private) or publish (students and guardians see it).
 */
export function MarksEntry({ classes, fixedClassId }: {classes: TeacherClass[];fixedClassId?: string;}) {
  const [classId, setClassId] = useState(fixedClassId ?? classes[0]?.id ?? '');
  const [assessment, setAssessment] = useState('Model Paper 4');
  const [outOf, setOutOf] = useState(100);
  const [scores, setScores] = useState<Record<string, string>>({});
  const [toast, setToast] = useState<string | null>(null);

  const entered = Object.values(scores).filter((v) => v !== '').length;
  const nums = Object.values(scores).filter((v) => v !== '').map(Number);
  const avg = nums.length ? Math.round(nums.reduce((a, b) => a + b, 0) / nums.length) : null;
  const cls = classes.find((c) => c.id === classId);

  return (
    <section className="rounded-2xl border border-line bg-white shadow-card">
      <div className="grid gap-4 border-b border-line p-5 sm:grid-cols-[1.3fr_1fr_0.5fr]">
        {!fixedClassId ?
        <div>
            <Label htmlFor="mclass">My class</Label>
            <Select id="mclass" value={classId} onChange={(e) => setClassId(e.target.value)}>
              {classes.map((c) =>
            <option key={c.id} value={c.id}>
                  {c.name}
                </option>
            )}
            </Select>
          </div> :
        null}
        <div className={fixedClassId ? 'sm:col-span-2' : ''}>
          <Label htmlFor="massess">Assessment</Label>
          <TextInput id="massess" value={assessment} onChange={(e) => setAssessment(e.target.value)} />
        </div>
        <div>
          <Label htmlFor="mout">Out of</Label>
          <TextInput id="mout" type="number" min={1} value={outOf} onChange={(e) => setOutOf(Number(e.target.value))} />
        </div>
      </div>

      <ul className="divide-y divide-line">
        {classStudents.map((s) => {
          const v = scores[s.id] ?? '';
          const invalid = v !== '' && (Number(v) < 0 || Number(v) > outOf);
          return (
            <li key={s.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
              <div className="min-w-0">
                <p className="truncate text-small font-medium text-ink">{s.name}</p>
                <p className="text-caption text-ink-faint">Last: {s.lastMark}</p>
              </div>
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  inputMode="numeric"
                  value={v}
                  onChange={(e) => setScores((m) => ({ ...m, [s.id]: e.target.value }))}
                  aria-label={`Mark for ${s.name}`}
                  aria-invalid={invalid}
                  placeholder="—"
                  className={`h-10 w-20 rounded-xl border px-3 text-right text-small tabular-nums text-ink focus:outline-none focus:ring-2 ${
                  invalid ? 'border-state-danger focus:ring-state-danger/20' : 'border-line focus:border-forest focus:ring-forest/15'}`
                  } />
                
                <span className="w-10 text-caption text-ink-faint">/ {outOf}</span>
              </div>
            </li>);

        })}
      </ul>

      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line p-5">
        <p className="text-caption text-ink-soft">
          {entered} of {classStudents.length} entered{avg !== null ? ` · class average ${avg}` : ''}
        </p>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" disabled={!entered} onClick={() => setToast('Saved as draft — only you can see it')}>
            Save draft
          </Button>
          <Button
            size="sm"
            disabled={entered < classStudents.length}
            onClick={() => setToast(`Published to ${cls?.students ?? classStudents.length} students and their guardians`)}>
            
            Publish results
          </Button>
        </div>
      </div>
      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </section>);

}