import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CheckIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { Button } from '../../components/ui/Button';
import { ChoiceGroup, Label, Select, TextArea, TextInput } from '../../components/ui/Field';
import { Badge } from '../../components/ui/Badge';
import { Toast } from '../../components/ui/States';
import { activityTypes } from '../../data/study';
import { examTargets, subjects } from '../../data/classes';
import { studentProfile } from '../../data/people';
import type { ActivityType, ActivityVisibility } from '../../types';

export function CreateActivity() {
  const navigate = useNavigate();
  const [title, setTitle] = useState('');
  const [type, setType] = useState<ActivityType>('Study Together');
  const [subject, setSubject] = useState('Physics');
  const [examTarget, setExamTarget] = useState('A/L 2027');
  const [date, setDate] = useState('');
  const [start, setStart] = useState('18:00');
  const [duration, setDuration] = useState('2 hours');
  const [venue, setVenue] = useState<'Online' | 'Library' | 'Café' | 'Institute' | 'Other'>('Library');
  const [area, setArea] = useState('Near Nugegoda');
  const [limit, setLimit] = useState(6);
  const [visibility, setVisibility] = useState<ActivityVisibility>('public');
  const [description, setDescription] = useState('');
  const [toast, setToast] = useState(false);

  const publish = (e: React.FormEvent) => {
    e.preventDefault();
    setToast(true);
    window.setTimeout(() => navigate('/study'), 1200);
  };

  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title="Create a study activity"
      subtitle="Two minutes. Other students studying the same thing will see it.">
      
      <form onSubmit={publish} className="grid gap-6 lg:grid-cols-[1.3fr_0.7fr] lg:items-start">
        <div className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">What are you doing?</h2>
            <div className="mt-4 space-y-4">
              <div>
                <Label htmlFor="title">Activity title</Label>
                <TextInput
                  id="title"
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="A/L Physics — Waves study session" />
                
              </div>
              <div>
                <Label htmlFor="type">Activity type</Label>
                <Select
                  id="type"
                  value={type}
                  onChange={(e) => setType(e.target.value as ActivityType)}>
                  
                  {activityTypes.map((t) =>
                  <option key={t}>{t}</option>
                  )}
                </Select>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label htmlFor="subject">Subject</Label>
                  <Select id="subject" value={subject} onChange={(e) => setSubject(e.target.value)}>
                    {subjects.map((s) =>
                    <option key={s}>{s}</option>
                    )}
                  </Select>
                </div>
                <div>
                  <Label htmlFor="exam">Grade / exam</Label>
                  <Select id="exam" value={examTarget} onChange={(e) => setExamTarget(e.target.value)}>
                    {examTargets.map((s) =>
                    <option key={s}>{s}</option>
                    )}
                  </Select>
                </div>
              </div>
            </div>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">When and where</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-3">
              <div>
                <Label htmlFor="date">Date</Label>
                <TextInput id="date" type="date" required value={date} onChange={(e) => setDate(e.target.value)} />
              </div>
              <div>
                <Label htmlFor="start">Start time</Label>
                <TextInput id="start" type="time" value={start} onChange={(e) => setStart(e.target.value)} />
              </div>
              <div>
                <Label htmlFor="duration">Duration</Label>
                <Select id="duration" value={duration} onChange={(e) => setDuration(e.target.value)}>
                  <option>1 hour</option>
                  <option>1 hour 30 min</option>
                  <option>2 hours</option>
                  <option>3 hours</option>
                </Select>
              </div>
            </div>

            <div className="mt-4">
              <Label>Location</Label>
              <ChoiceGroup
                name="Location"
                columns={3}
                value={venue}
                onChange={setVenue}
                options={[
                { value: 'Online', label: 'Online' },
                { value: 'Library', label: 'Library' },
                { value: 'Café', label: 'Café' },
                { value: 'Institute', label: 'Institute' },
                { value: 'Other', label: 'Other' }]
                } />
              
            </div>

            {venue !== 'Online' ?
            <div className="mt-4">
                <Label htmlFor="area" hint="Shown publicly as an approximate area">
                  Area
                </Label>
                <TextInput
                id="area"
                value={area}
                onChange={(e) => setArea(e.target.value)}
                placeholder="Near Nugegoda" />
              
                <p className="mt-1.5 text-caption text-ink-faint">
                  The exact meeting point is only shared with people you accept.
                </p>
              </div> :
            null}
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Who can join</h2>
            <div className="mt-4 space-y-4">
              <div>
                <Label htmlFor="limit">Participant limit</Label>
                <TextInput
                  id="limit"
                  type="number"
                  min={2}
                  max={50}
                  value={limit}
                  onChange={(e) => setLimit(Number(e.target.value))}
                  className="sm:max-w-[10rem]" />
                
              </div>
              <ChoiceGroup
                name="Visibility"
                columns={3}
                value={visibility}
                onChange={setVisibility}
                options={[
                { value: 'public', label: 'Public', description: 'Anyone who finds it can join' },
                { value: 'request', label: 'Request to join', description: 'You approve each person' },
                { value: 'private', label: 'Private', description: 'Invite link only' }]
                } />
              
              {visibility === 'private' ?
              <div className="rounded-xl border border-line bg-surface-muted px-4 py-3">
                  <p className="text-caption text-ink-soft">
                    A share link is generated when you publish. Only people with the link
                    can open this activity.
                  </p>
                </div> :
              null}
              <div>
                <Label htmlFor="desc" hint="Optional">
                  Description
                </Label>
                <TextArea
                  id="desc"
                  rows={3}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="What you will cover, what to bring." />
                
              </div>
            </div>
          </section>
        </div>

        <aside className="space-y-4 lg:sticky lg:top-24">
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <p className="text-caption uppercase tracking-wide text-ink-faint">Preview</p>
            <h2 className="mt-2 text-h3 text-ink">{title || 'Your activity title'}</h2>
            <p className="mt-1 text-caption text-ink-soft">
              {date || 'Date'} · {start} · {duration}
            </p>
            <p className="text-caption text-ink-soft">
              {venue === 'Online' ? 'Online' : area} · {venue}
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Badge tone="neutral">{type}</Badge>
              <Badge>{subject}</Badge>
              <Badge>{examTarget}</Badge>
              <Badge tone="success">0 / {limit} going</Badge>
            </div>
            <p className="mt-3 text-small text-ink-soft">
              {description || 'Your description appears here.'}
            </p>
          </section>

          <Button type="submit" className="w-full" size="lg">
            <CheckIcon className="h-4 w-4" />
            Create activity
          </Button>
          <p className="text-caption text-ink-faint">
            Hosting means you are responsible for the group. Keep it about studying —
            activities can be reported by any participant.
          </p>
        </aside>
      </form>

      <Toast
        open={toast}
        message="Activity published"
        detail="It is now visible on the Study map for students in your subject and exam year."
        onClose={() => setToast(false)} />
      
    </AppShell>);

}