import React, { useState } from 'react';
import { SendIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { ChoiceGroup, Label, Select, TextArea, TextInput } from '../../components/ui/Field';
import { Toast } from '../../components/ui/States';
import { announcements } from '../../data/people';
import { institute, instituteClasses } from '../../data/institute';

export function InstituteAnnouncements() {
  const [channel, setChannel] = useState<'app' | 'both'>('both');
  const [toast, setToast] = useState(false);

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Announcements"
      subtitle="Reaches enrolled students and their guardians, in-app and by SMS">
      
      <div className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr] xl:grid-cols-[0.8fr_1.2fr]">
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <h2 className="text-h3 text-ink">New announcement</h2>
          <form
            className="mt-4 space-y-4"
            onSubmit={(e) => {
              e.preventDefault();
              setToast(true);
            }}>
            
            <div>
              <Label htmlFor="audience">Send to</Label>
              <Select id="audience">
                <option>All classes (1,284 students)</option>
                {instituteClasses.map((c) =>
                <option key={c.id}>
                    {c.name} ({c.enrolled} students)
                  </option>
                )}
              </Select>
            </div>
            <div>
              <Label htmlFor="atitle">Title</Label>
              <TextInput id="atitle" required placeholder="Saturday class postponed to Sunday" />
            </div>
            <div>
              <Label htmlFor="abody" hint="Keep SMS under 160 characters">
                Message
              </Label>
              <TextArea id="abody" rows={4} required placeholder="Combined Mathematics theory moves to Sunday 8:00 AM this week only." />
            </div>
            <div>
              <Label>Channel</Label>
              <ChoiceGroup
                name="Channel"
                value={channel}
                onChange={setChannel}
                options={[
                { value: 'both', label: 'App + SMS', description: 'Reaches parents without the app' },
                { value: 'app', label: 'App only', description: 'No SMS credits used' }]
                } />
              
            </div>
            <Button type="submit" className="w-full">
              <SendIcon className="h-4 w-4" />
              Send announcement
            </Button>
            <p className="text-caption text-ink-faint">
              SMS bundle: 1,240 of 2,000 messages remaining this month.
            </p>
          </form>
        </section>

        <section className="space-y-4">
          <h2 className="text-h3 text-ink">Sent</h2>
          {announcements.map((a) =>
          <article key={a.id} className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-small font-medium text-ink">{a.title}</h3>
                <Badge tone={a.channel === 'App + SMS' ? 'success' : 'neutral'}>{a.channel}</Badge>
              </div>
              <p className="mt-2 text-small text-ink-soft">{a.body}</p>
              <p className="mt-2 text-caption text-ink-faint">
                {a.from} · {a.time} · delivered to 1,284 students and 1,102 guardians
              </p>
            </article>
          )}
        </section>
      </div>

      <Toast
        open={toast}
        message="Announcement sent"
        detail="Delivered in-app now; SMS delivery reports appear within a few minutes."
        onClose={() => setToast(false)} />
      
    </AppShell>);

}