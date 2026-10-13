import React, { useState } from 'react';
import { MegaphoneIcon } from 'lucide-react';
import { Button } from '../ui/Button';
import { Label, Select, TextArea, TextInput } from '../ui/Field';
import { Toast } from '../ui/States';
import type { TeacherClass } from '../../data/teaching';

/** A teacher's announcement is always scoped to one of their classes. */
export function ClassAnnouncementForm({ classes, fixedClassId }: {classes: TeacherClass[];fixedClassId?: string;}) {
  const [classId, setClassId] = useState(fixedClassId ?? classes[0]?.id ?? '');
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [sms, setSms] = useState(true);
  const [toast, setToast] = useState<string | null>(null);
  const cls = classes.find((c) => c.id === classId);

  return (
    <form
      className="space-y-4 rounded-2xl border border-line bg-white p-5 shadow-card"
      onSubmit={(e) => {
        e.preventDefault();
        setToast(`Sent to ${cls?.students ?? 0} students${sms ? ' and their guardians by SMS' : ''}`);
        setTitle('');
        setBody('');
      }}>
      
      <div className="flex items-center gap-2">
        <MegaphoneIcon className="h-4 w-4 text-forest" aria-hidden="true" />
        <h2 className="text-h3 text-ink">Class announcement</h2>
      </div>
      {!fixedClassId ?
      <div>
          <Label htmlFor="aclass">To</Label>
          <Select id="aclass" value={classId} onChange={(e) => setClassId(e.target.value)}>
            {classes.map((c) =>
          <option key={c.id} value={c.id}>
                {c.name}
              </option>
          )}
          </Select>
        </div> :
      null}
      <p className="rounded-xl bg-surface-muted px-3.5 py-2.5 text-caption text-ink-soft">
        Reaches only <strong className="text-ink">{cls?.students} students in {cls?.name}</strong> and
        their guardians. Institute-wide notices are sent by the institute.
      </p>
      <div>
        <Label htmlFor="atitle">Title</Label>
        <TextInput id="atitle" required value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Saturday class moved to Sunday" />
      </div>
      <div>
        <Label htmlFor="abody">Message</Label>
        <TextArea id="abody" required rows={4} value={body} onChange={(e) => setBody(e.target.value)} />
      </div>
      <label className="flex items-center gap-2 text-small text-ink-soft">
        <input type="checkbox" checked={sms} onChange={(e) => setSms(e.target.checked)} className="accent-[#13402C]" />
        Also send by SMS to guardians
      </label>
      <Button type="submit" className="w-full sm:w-auto">
        Send announcement
      </Button>
      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </form>);

}