import React, { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ListIcon, MapIcon, SearchIcon, SlidersHorizontalIcon, XIcon } from 'lucide-react';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import { ClassCard } from '../components/cards/ClassCard';
import { ClassMap } from '../components/cards/ClassMap';
import { Button } from '../components/ui/Button';
import { Label, Select, TextInput } from '../components/ui/Field';
import { SegmentedControl } from '../components/ui/Tabs';
import { EmptyState } from '../components/ui/States';
import { Badge } from '../components/ui/Badge';
import {
  cities,
  classes,
  districts,
  examTargets,
  grades,
  mediums,
  subjects } from
'../data/classes';
import { lkr } from '../utils/format';

const anyValue = 'Any';

interface Filters {
  subject: string;
  grade: string;
  examTarget: string;
  medium: string;
  district: string;
  city: string;
  mode: string;
  day: string;
  time: string;
  maxFee: number;
  institute: string;
  teacher: string;
}

const emptyFilters: Filters = {
  subject: anyValue,
  grade: anyValue,
  examTarget: anyValue,
  medium: anyValue,
  district: anyValue,
  city: anyValue,
  mode: anyValue,
  day: anyValue,
  time: anyValue,
  maxFee: 5000,
  institute: anyValue,
  teacher: anyValue
};

const institutes = Array.from(new Set(classes.map((c) => c.institute)));
const teachers = Array.from(new Set(classes.map((c) => c.teacher)));

export function Search() {
  const [params] = useSearchParams();
  const [query, setQuery] = useState(params.get('q') ?? '');
  const [where, setWhere] = useState(params.get('where') ?? '');
  const [filters, setFilters] = useState<Filters>(emptyFilters);
  const [view, setView] = useState<'list' | 'map'>('list');
  const [sort, setSort] = useState('relevance');
  const [filtersOpen, setFiltersOpen] = useState(false);

  const set = <K extends keyof Filters,>(key: K, value: Filters[K]) =>
  setFilters((f) => ({ ...f, [key]: value }));

  const results = useMemo(() => {
    const q = query.trim().toLowerCase();
    const w = where.trim().toLowerCase();
    const list = classes.filter((c) => {
      const haystack =
      `${c.title} ${c.subject} ${c.examTarget} ${c.grade} ${c.institute} ${c.teacher} ${c.mode}`.toLowerCase();
      if (q && !haystack.includes(q)) return false;
      if (w && !`${c.city} ${c.district} ${c.mode}`.toLowerCase().includes(w)) return false;
      if (filters.subject !== anyValue && c.subject !== filters.subject) return false;
      if (filters.grade !== anyValue && c.grade !== filters.grade) return false;
      if (filters.examTarget !== anyValue && c.examTarget !== filters.examTarget) return false;
      if (filters.medium !== anyValue && c.medium !== filters.medium) return false;
      if (filters.district !== anyValue && c.district !== filters.district) return false;
      if (filters.city !== anyValue && c.city !== filters.city) return false;
      if (filters.mode !== anyValue && c.mode !== filters.mode) return false;
      if (filters.day !== anyValue && !c.day.startsWith(filters.day)) return false;
      if (filters.time !== anyValue) {
        const isEvening = c.startTime.includes('PM') && parseInt(c.startTime, 10) >= 4;
        if (filters.time === 'Morning' && isEvening) return false;
        if (filters.time === 'Evening' && !isEvening) return false;
      }
      if (c.feeMonthly > filters.maxFee) return false;
      if (filters.institute !== anyValue && c.institute !== filters.institute) return false;
      if (filters.teacher !== anyValue && c.teacher !== filters.teacher) return false;
      return true;
    });

    if (sort === 'fee-low') return [...list].sort((a, b) => a.feeMonthly - b.feeMonthly);
    if (sort === 'fee-high') return [...list].sort((a, b) => b.feeMonthly - a.feeMonthly);
    if (sort === 'seats') return [...list].sort((a, b) => b.seatsTotal - b.seatsTaken - (a.seatsTotal - a.seatsTaken));
    return list;
  }, [query, where, filters, sort]);

  const activeCount =
  Object.entries(filters).filter(([k, v]) => k === 'maxFee' ? v !== 5000 : v !== anyValue).
  length;

  const filterPanel =
  <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="text-h3 text-ink">Filters</h2>
        {activeCount > 0 ?
      <button
        onClick={() => setFilters(emptyFilters)}
        className="text-caption font-medium text-forest underline underline-offset-4">
        
            Clear all
          </button> :
      null}
      </div>

      <FilterSelect label="Subject" value={filters.subject} onChange={(v) => set('subject', v)} options={subjects} />
      <FilterSelect label="Grade" value={filters.grade} onChange={(v) => set('grade', v)} options={grades} />
      <FilterSelect label="Exam target" value={filters.examTarget} onChange={(v) => set('examTarget', v)} options={examTargets} />
      <FilterSelect label="Medium" value={filters.medium} onChange={(v) => set('medium', v)} options={mediums} />
      <FilterSelect label="District" value={filters.district} onChange={(v) => set('district', v)} options={districts} />
      <FilterSelect label="City" value={filters.city} onChange={(v) => set('city', v)} options={cities} />
      <FilterSelect label="Mode" value={filters.mode} onChange={(v) => set('mode', v)} options={['Physical', 'Online', 'Hybrid']} />
      <FilterSelect label="Day" value={filters.day} onChange={(v) => set('day', v)} options={['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']} />
      <FilterSelect label="Time of day" value={filters.time} onChange={(v) => set('time', v)} options={['Morning', 'Evening']} />

      <div>
        <Label htmlFor="fee" hint={`up to ${lkr(filters.maxFee)}`}>
          Monthly fee
        </Label>
        <input
        id="fee"
        type="range"
        min={1000}
        max={5000}
        step={200}
        value={filters.maxFee}
        onChange={(e) => set('maxFee', Number(e.target.value))}
        className="w-full accent-[#13402C]" />
      
      </div>

      <FilterSelect label="Institute" value={filters.institute} onChange={(v) => set('institute', v)} options={institutes} />
      <FilterSelect label="Teacher" value={filters.teacher} onChange={(v) => set('teacher', v)} options={teachers} />
    </div>;


  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />

      <div className="border-b border-line bg-white">
        <div className="mx-auto flex max-w-[1400px] flex-col gap-3 px-5 py-5 lg:flex-row lg:items-center lg:px-8">
          <div className="flex flex-1 items-center gap-2.5 rounded-xl border border-line px-3.5 py-2.5">
            <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="What do you want to learn?"
              aria-label="Search classes"
              className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
            
            {query ?
            <button onClick={() => setQuery('')} aria-label="Clear search">
                <XIcon className="h-4 w-4 text-ink-faint" />
              </button> :
            null}
          </div>
          <TextInput
            value={where}
            onChange={(e) => setWhere(e.target.value)}
            placeholder="Nugegoda / Online"
            aria-label="Location"
            className="lg:max-w-[14rem]" />
          
          <div className="flex items-center gap-2">
            <Button
              variant="secondary"
              className="lg:hidden"
              onClick={() => setFiltersOpen((v) => !v)}>
              
              <SlidersHorizontalIcon className="h-4 w-4" />
              Filters{activeCount ? ` (${activeCount})` : ''}
            </Button>
            <SegmentedControl
              ariaLabel="Result view"
              value={view}
              onChange={setView}
              options={[
              { value: 'list', label: 'List', icon: <ListIcon className="h-4 w-4" /> },
              { value: 'map', label: 'Map', icon: <MapIcon className="h-4 w-4" /> }]
              } />
            
          </div>
        </div>
      </div>

      <main className="mx-auto flex max-w-[1400px] gap-8 px-5 py-8 lg:px-8">
        <aside className="hidden w-64 shrink-0 lg:block">
          <div className="sticky top-24 rounded-2xl border border-line bg-white p-5">
            {filterPanel}
          </div>
        </aside>

        <div className="min-w-0 flex-1">
          {filtersOpen ?
          <div className="mb-6 rounded-2xl border border-line bg-white p-5 lg:hidden">
              {filterPanel}
            </div> :
          null}

          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h1 className="text-h2 text-ink">
                {results.length} {results.length === 1 ? 'class' : 'classes'}
              </h1>
              <p className="mt-1 text-small text-ink-soft">
                {query ? `Matching “${query}”` : 'All listed classes'}
                {where ? ` near ${where}` : ''} · prototype listings
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Label htmlFor="sort">Sort</Label>
              <Select
                id="sort"
                value={sort}
                onChange={(e) => setSort(e.target.value)}
                className="w-44">
                
                <option value="relevance">Most relevant</option>
                <option value="fee-low">Fee: low to high</option>
                <option value="fee-high">Fee: high to low</option>
                <option value="seats">Most seats available</option>
              </Select>
            </div>
          </div>

          {view === 'map' ?
          <div className="mt-6 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
              <ClassMap results={results} />
              <div className="space-y-4">
                {results.slice(0, 4).map((c) =>
              <ClassCard key={c.id} item={c} compact />
              )}
              </div>
            </div> :
          results.length === 0 ?
          <div className="mt-8">
              <EmptyState
              icon={<SearchIcon className="h-5 w-5" />}
              title="No classes match these filters"
              body="Try widening the fee range, removing the day filter, or including online classes — more institutes are being onboarded in this area."
              action={
              <Button variant="secondary" onClick={() => setFilters(emptyFilters)}>
                    Clear filters
                  </Button>
              } />
            
            </div> :

          <div className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
              {results.map((c) =>
            <ClassCard key={c.id} item={c} />
            )}
            </div>
          }
        </div>
      </main>

      <SiteFooter />
    </div>);

}

function FilterSelect({
  label,
  value,
  onChange,
  options





}: {label: string;value: string;onChange: (v: string) => void;options: string[];}) {
  const id = `filter-${label.replace(/\s+/g, '-').toLowerCase()}`;
  return (
    <div>
      <Label htmlFor={id}>{label}</Label>
      <Select id={id} value={value} onChange={(e) => onChange(e.target.value)}>
        <option value={anyValue}>Any</option>
        {options.map((o) =>
        <option key={o} value={o}>
            {o}
          </option>
        )}
      </Select>
    </div>);

}