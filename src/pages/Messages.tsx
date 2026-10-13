import React, { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { SendIcon, ShieldAlertIcon } from 'lucide-react';
import { AppShell } from '../components/app/AppShell';
import { studentNav } from '../components/app/navConfig';
import { Avatar } from '../components/ui/Avatar';
import { Badge } from '../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { TextInput } from '../components/ui/Field';
import { activities, conversations } from '../data/study';
import { studentProfile } from '../data/people';
import type { ChatMessage } from '../types';

const fallbackThread: ChatMessage[] = [
{ author: 'Nimal', initials: 'NP', text: "I'm bringing the 2025 paper.", time: '4:12 PM' },
{ author: 'Ayesha', initials: 'AR', text: 'Should we start with stationary waves?', time: '4:20 PM' },
{ author: 'Kasun', initials: 'KP', text: 'Yes — I still get the node spacing wrong.', time: '4:24 PM', self: true },
{ author: 'Nimal', initials: 'NP', text: 'We will do that first then. Library reading room, 6.', time: '4:26 PM' }];


export function Messages() {
  const [params] = useSearchParams();
  const threadParam = params.get('thread');
  const initial =
  conversations.find((c) => c.id === threadParam)?.id ?? (
  threadParam && activities.some((a) => a.id === threadParam) ? threadParam : conversations[0].id);

  const [activeId, setActiveId] = useState(initial);
  const [draft, setDraft] = useState('');
  const [messages, setMessages] = useState<ChatMessage[]>(fallbackThread);

  const activity = activities.find((a) => a.id === activeId);
  const conversation = conversations.find((c) => c.id === activeId);
  const title = activity?.title ?? conversation?.name ?? 'Conversation';
  const kind = activity ? 'Activity group' : conversation?.kind ?? 'Conversation';

  const send = (e: React.FormEvent) => {
    e.preventDefault();
    if (!draft.trim()) return;
    setMessages((m) => [
    ...m,
    { author: 'Kasun', initials: 'KP', text: draft.trim(), time: 'Now', self: true }]
    );
    setDraft('');
  };

  const threads = [
  ...conversations,
  ...activities.
  filter((a) => !conversations.some((c) => c.id === a.id)).
  filter((a) => a.id === threadParam).
  map((a) => ({
    id: a.id,
    name: a.title,
    kind: 'Activity group' as const,
    last: a.chatPreview[0]?.text ?? 'Group created',
    time: a.dayLabel,
    unread: 0
  }))];


  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title="Messages"
      subtitle="Activity groups, study circles and support — separate from your classes">
      
      <div className="grid gap-5 lg:grid-cols-[0.42fr_0.58fr] xl:grid-cols-[0.34fr_0.66fr]">
        <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
          <h2 className="border-b border-line px-5 py-4 text-h3 text-ink">Conversations</h2>
          <ul className="divide-y divide-line">
            {threads.map((t) =>
            <li key={t.id}>
                <button
                onClick={() => setActiveId(t.id)}
                aria-current={t.id === activeId}
                className={`flex w-full items-start gap-3 px-5 py-4 text-left transition-colors duration-150 ease-out ${
                t.id === activeId ? 'bg-forest-50' : 'hover:bg-surface-muted'}`
                }>
                
                  <Avatar initials={t.name.slice(0, 2).toUpperCase()} size="sm" />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-small font-medium text-ink">{t.name}</p>
                    <p className="truncate text-caption text-ink-soft">{t.last}</p>
                    <p className="mt-1 text-caption text-ink-faint">{t.kind}</p>
                  </div>
                  <div className="text-right">
                    <span className="text-caption text-ink-faint">{t.time}</span>
                    {t.unread ?
                  <span className="mt-1 block rounded-full bg-clay px-1.5 text-caption font-medium text-white">
                        {t.unread}
                      </span> :
                  null}
                  </div>
                </button>
              </li>
            )}
          </ul>
        </section>

        <section className="flex min-h-[32rem] flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-card">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
              <h2 className="text-h3 text-ink">{title}</h2>
              <p className="text-caption text-ink-soft">
                {kind}
                {activity ? ` · ${activity.going} participants` : ''}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Badge tone="neutral">Report</Badge>
              <Badge tone="neutral">Leave</Badge>
            </div>
          </div>

          <div className="flex-1 space-y-4 overflow-y-auto th-scroll px-5 py-5">
            <p className="text-center text-caption text-ink-faint">
              This group was created for one study session. It closes 24 hours after the
              session ends.
            </p>
            {messages.map((m, i) =>
            <div key={i} className={`flex gap-3 ${m.self ? 'flex-row-reverse' : ''}`}>
                <Avatar initials={m.initials} size="xs" />
                <div className={`max-w-[70%] ${m.self ? 'text-right' : ''}`}>
                  <p className="text-caption text-ink-faint">
                    {m.author} · {m.time}
                  </p>
                  <p
                  className={`mt-1 inline-block rounded-2xl px-3.5 py-2 text-small ${
                  m.self ? 'bg-forest text-white' : 'bg-surface-muted text-ink'}`
                  }>
                  
                    {m.text}
                  </p>
                </div>
              </div>
            )}
          </div>

          <div className="border-t border-line px-5 py-3">
            <div className="mb-3 flex items-start gap-2 rounded-xl bg-surface-muted px-3.5 py-2.5">
              <ShieldAlertIcon className="mt-0.5 h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
              <p className="text-caption text-ink-soft">
                Keep it about studying. Any participant can report a message, and hosts
                can remove members.
              </p>
            </div>
            <form onSubmit={send} className="flex items-center gap-2">
              <TextInput
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                placeholder="Write a message"
                aria-label="Message" />
              
              <Button type="submit" aria-label="Send message">
                <SendIcon className="h-4 w-4" />
              </Button>
            </form>
          </div>
        </section>
      </div>
    </AppShell>);

}