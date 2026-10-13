import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, ListIcon, MapIcon, SearchIcon, SlidersHorizontalIcon } from 'lucide-react';
import { classes, cities, examTargets, mediums, subjects } from '../../data/classes';
import { ClassCard } from '../cards/ClassCard';
import { ClassMap } from '../cards/ClassMap';
import { Select } from '../ui/Field';
import { SegmentedControl } from '../ui/Tabs';
import { EmptyState } from '../ui/States';

const ANY = '';
const modes = ['Physical', 'Online', 'Hybrid'];
const dayOptions = ['Weekdays', 'Saturday', 'Sunday'];

/** Working discovery on the homepage — real search, real filters, real cards. */
export function FindClassSection() {
  const [q, setQ] = useState('');
  const [subject, setSubject] = useState(ANY);
  const [exam, setExam] = useState(ANY);
  const [medium, setMedium] = useState(ANY);
  const [city, setCity] = useState(ANY);
  const [mode, setMode] = useState(ANY);
  const [day, setDay] = useState(ANY);
  const [view, setView] = useState<'list' | 'map'>('list');

  const results = useMemo(
    () =>
    classes.filter((c) => {
      if (q && !`${c.title} ${c.subject} ${c.institute} ${c.teacher}`.toLowerCase().includes(q.toLowerCase())) return false;
      if (subject && c.subject !== subject) return false;
      if (exam && c.examTarget !== exam) return false;
      if (medium && c.medium !== medium) return false;
      if (city && c.city !== city) return false;
      if (mode && c.mode !== mode) return false;
      if (day === 'Weekdays' && ['Saturday', 'Sunday'].includes(c.day)) return false;
      if (day && day !== 'Weekdays' && c.day !== day) return false;
      return true;
    }),
    [q, subject, exam, medium, city, mode, day]
  );

  const params = new URLSearchParams();
  if (q || subject) params.set('q', q || subject);
  if (city) params.set('where', city);

  const clear = () => {
    setQ('');
    setSubject(ANY);
    setExam(ANY);
    setMedium(ANY);
    setCity(ANY);
    setMode(ANY);
    setDay(ANY);
  };

  return (
    <section id="find" className="scroll-mt-20 border-b border-line/60 bg-surface-muted">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <h2 className="text-h1 text-ink">Find the right class</h2>
        <p className="mt-2 max-w-2xl text-body text-ink-soft">
          Search tuition classes by what, when and where you want to learn.
        </p>

        <div className="mt-8 rounded-2xl border border-line bg-white p-4 shadow-card">
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex min-w-[14rem] flex-1 items-center gap-2.5 rounded-xl border border-line px-3.5 py-2.5 focus-within:border-forest">
              <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
              <input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Subject, teacher or institute"
                aria-label="Search classes"
                className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
              
            </div>
            <SegmentedControl
              ariaLabel="Results view"
              value={view}
              onChange={setView}
              options={[
              { value: 'list', label: 'List', icon: <ListIcon className="h-4 w-4" /> },
              { value: 'map', label: 'Map', icon: <MapIcon className="h-4 w-4" /> }]
              } />
            
          </div>
          <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            <Filter label="Subject" value={subject} onChange={setSubject} options={subjects} />
            <Filter label="Exam year" value={exam} onChange={setExam} options={examTargets} />
            <Filter label="Medium" value={medium} onChange={setMedium} options={mediums} />
            <Filter label="City" value={city} onChange={setCity} options={cities} />
            <Filter label="Mode" value={mode} onChange={setMode} options={modes} />
            <Filter label="Day" value={day} onChange={setDay} options={dayOptions} />
          </div>
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-caption text-ink-soft">
            <span>
              {results.length} {results.length === 1 ? 'class' : 'classes'} match
            </span>
            <Link to={`/search?${params.toString()}`} className="inline-flex items-center gap-1.5 font-medium text-forest">
              <SlidersHorizontalIcon className="h-3.5 w-3.5" />
              More filters: grade, district, time, fee, institute, teacher
            </Link>
          </div>
        </div>

        <div className="mt-6">
          {results.length === 0 ?
          <EmptyState
            icon={<SearchIcon className="h-5 w-5" />}
            title="No classes match these filters"
            body="Try removing a filter, or search a nearby city."
            action={
            <button onClick={clear} className="text-small font-medium text-forest underline underline-offset-4">
                  Clear filters
                </button>
            } /> :

          view === 'list' ?
          <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {results.slice(0, 6).map((c) =>
            <ClassCard key={c.id} item={c} compact />
            )}
            </div> :

          <ClassMap results={results} className="h-[440px]" />
          }
          {results.length > 6 && view === 'list' ?
          <Link
            to={`/search?${params.toString()}`}
            className="mt-6 inline-flex items-center gap-1.5 text-small font-medium text-forest">
            
              See all {results.length} classes <ArrowRightIcon className="h-4 w-4" />
            </Link> :
          null}
        </div>
      </div>
    </section>);

}

function Filter({
  label,
  value,
  onChange,
  options





}: {label: string;value: string;onChange: (v: string) => void;options: string[];}) {
  return (
    <Select aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} className={value ? 'border-forest text-forest' : ''}>
      <option value="">{label}: any</option>
      {options.map((o) =>
      <option key={o} value={o}>
          {o}
        </option>
      )}
    </Select>);

}