import React from 'react';

export interface TabItem {
  id: string;
  label: string;
  count?: number;
}

export function Tabs({
  items,
  active,
  onChange,
  ariaLabel





}: {items: TabItem[];active: string;onChange: (id: string) => void;ariaLabel: string;}) {
  return (
    <div
      role="tablist"
      aria-label={ariaLabel}
      className="flex gap-1 overflow-x-auto border-b border-line">
      
      {items.map((item) => {
        const selected = item.id === active;
        return (
          <button
            key={item.id}
            role="tab"
            aria-selected={selected}
            onClick={() => onChange(item.id)}
            className={`-mb-px whitespace-nowrap border-b-2 px-3.5 py-2.5 text-small font-medium transition-[color,border-color] duration-150 ease-out ${
            selected ?
            'border-forest text-forest' :
            'border-transparent text-ink-soft hover:text-ink'}`
            }>
            
            {item.label}
            {typeof item.count === 'number' ?
            <span className="ml-1.5 text-caption text-ink-faint">{item.count}</span> :
            null}
          </button>);

      })}
    </div>);

}

export function SegmentedControl<T extends string>({
  options,
  value,
  onChange,
  ariaLabel





}: {options: {value: T;label: string;icon?: React.ReactNode;}[];value: T;onChange: (v: T) => void;ariaLabel: string;}) {
  return (
    <div
      role="radiogroup"
      aria-label={ariaLabel}
      className="inline-flex rounded-xl border border-line bg-white p-1">
      
      {options.map((o) => {
        const active = o.value === value;
        return (
          <button
            key={o.value}
            role="radio"
            aria-checked={active}
            onClick={() => onChange(o.value)}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-small font-medium transition-[background-color,color] duration-150 ease-out ${
            active ? 'bg-forest text-white' : 'text-ink-soft hover:text-ink'}`
            }>
            
            {o.icon}
            {o.label}
          </button>);

      })}
    </div>);

}