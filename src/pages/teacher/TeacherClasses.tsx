import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { PlusIcon, UsersIcon } from 'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { Toast } from '../../components/ui/States';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import { subjects } from '../../data/classes';

export function TeacherClasses() {
  const { myClasses, isIndependent, workspace } = useTeacherScope();
  const [open, setOpen] = useState(false);
  const [toast, setToast] = useState(false);

  return (
    <TeacherShell
      title="My classes"
      subtitle={
      isIndependent ?
      'Classes you run yourself' :
      `Classes ${workspace?.name ?? 'your institute'} has assigned to you`
      }
      actions={
      isIndependent ?
      <Button size="sm" onClick={() => setOpen(true)}>
            <PlusIcon className="h-4 w-4" />
            New class
          </Button> :
      null
      }>
      
      <ul className="grid gap-4 md:grid-cols-2">
        {myClasses.map((c) =>
        <li key={c.id}>
            <Link
            to={`/teacher/classes/${c.id}`}
            className="flex h-full flex-col rounded-2xl border border-line bg-white p-5 shadow-card transition-[border-color,box-shadow] duration-150 ease-out hover:border-line-strong hover:shadow-lift">
            
              <h2 className="text-h3 text-ink">{c.name}</h2>
              <p className="mt-1 text-small text-ink-soft">
                {c.schedule} · {c.place}
              </p>
              <dl className="mt-auto grid grid-cols-3 gap-3 border-t border-line pt-4 text-small">
                <div>
                  <dt className="text-caption text-ink-faint">Students</dt>
                  <dd className="mt-0.5 inline-flex items-center gap-1 font-medium text-ink">
                    <UsersIcon className="h-3.5 w-3.5 text-ink-faint" />
                    {c.students}
                  </dd>
                </div>
                <div>
                  <dt className="text-caption text-ink-faint">Attendance</dt>
                  <dd className="mt-0.5 font-medium text-ink">{c.attendanceRate}%</dd>
                </div>
                <div>
                  <dt className="text-caption text-ink-faint">Next</dt>
                  <dd className="mt-0.5 truncate font-medium text-ink">{c.nextSession}</dd>
                </div>
              </dl>
            </Link>
          </li>
        )}
      </ul>
      {!isIndependent ?
      <p className="mt-6 text-caption text-ink-faint">
          Need another class? Ask your institute to assign it — they create classes and set fees.
        </p> :
      null}

      <Modal open={open} onClose={() => setOpen(false)} title="New class">
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            setOpen(false);
            setToast(true);
          }}>
          
          <div>
            <Label htmlFor="ncname">Class name</Label>
            <TextInput id="ncname" required placeholder="A/L Physics — Saturday Revision" />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <Label htmlFor="ncsub">Subject</Label>
              <Select id="ncsub">
                {subjects.map((s) =>
                <option key={s}>{s}</option>
                )}
              </Select>
            </div>
            <div>
              <Label htmlFor="ncfee">Monthly fee (LKR)</Label>
              <TextInput id="ncfee" type="number" required placeholder="2500" />
            </div>
            <div>
              <Label htmlFor="ncday">Day & time</Label>
              <TextInput id="ncday" required placeholder="Saturday · 2:00 PM" />
            </div>
            <div>
              <Label htmlFor="ncwhere">Where</Label>
              <TextInput id="ncwhere" required placeholder="Maharagama or Online" />
            </div>
          </div>
          <label className="flex items-center gap-2 text-small text-ink-soft">
            <input type="checkbox" defaultChecked className="accent-[#13402C]" />
            List this class publicly so students can find it
          </label>
          <Button type="submit" className="w-full">
            Create class
          </Button>
        </form>
      </Modal>
      <Toast open={toast} message="Class created" detail="It appears in the marketplace after a quick review." onClose={() => setToast(false)} />
    </TeacherShell>);

}