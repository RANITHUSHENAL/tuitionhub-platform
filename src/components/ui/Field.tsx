import React from 'react';

const controlBase =
'w-full rounded-xl border border-line bg-white text-ink placeholder:text-ink-faint transition-[border-color,box-shadow] duration-150 ease-out hover:border-line-strong focus:border-forest focus:outline-none focus:ring-2 focus:ring-forest/15';

export function Label({
  htmlFor,
  children,
  hint




}: {htmlFor?: string;children: React.ReactNode;hint?: string;}) {
  return (
    <label
      htmlFor={htmlFor}
      className="mb-1.5 flex items-baseline justify-between gap-3 text-small font-medium text-ink">
      
      <span>{children}</span>
      {hint ? <span className="text-caption font-normal text-ink-faint">{hint}</span> : null}
    </label>);

}

export function TextInput({
  className = '',
  ...rest
}: React.InputHTMLAttributes<HTMLInputElement>) {
  return <input className={`${controlBase} h-11 px-3.5 text-small ${className}`} {...rest} />;
}

export function TextArea({
  className = '',
  ...rest
}: React.TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return (
    <textarea className={`${controlBase} px-3.5 py-3 text-small ${className}`} {...rest} />);

}

export function Select({
  className = '',
  children,
  ...rest
}: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={`${controlBase} h-11 px-3 text-small ${className}`} {...rest}>
      {children}
    </select>);

}

export function ChoiceGroup<T extends string>({
  options,
  value,
  onChange,
  name,
  columns = 2






}: {options: {value: T;label: string;description?: string;}[];value: T;onChange: (value: T) => void;name: string;columns?: 1 | 2 | 3;}) {
  const cols = columns === 1 ? 'grid-cols-1' : columns === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2';
  return (
    <div role="radiogroup" aria-label={name} className={`grid gap-2 ${cols}`}>
      {options.map((o) => {
        const active = o.value === value;
        return (
          <button
            key={o.value}
            type="button"
            role="radio"
            aria-checked={active}
            onClick={() => onChange(o.value)}
            className={`rounded-xl border px-3.5 py-3 text-left transition-[border-color,background-color] duration-150 ease-out ${
            active ?
            'border-forest bg-forest-50' :
            'border-line bg-white hover:border-line-strong'}`
            }>
            
            <span className="block text-small font-medium text-ink">{o.label}</span>
            {o.description ?
            <span className="mt-0.5 block text-caption text-ink-soft">{o.description}</span> :
            null}
          </button>);

      })}
    </div>);

}