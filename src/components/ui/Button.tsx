import React from 'react';
import { Link } from 'react-router-dom';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'accent';
type Size = 'sm' | 'md' | 'lg';

const base =
'inline-flex items-center justify-center gap-2 font-medium rounded-xl border transition-[background-color,border-color,color,box-shadow,transform] duration-150 ease-out disabled:opacity-45 disabled:pointer-events-none active:translate-y-px whitespace-nowrap';

const variants: Record<Variant, string> = {
  primary:
  'bg-forest text-white border-forest hover:bg-forest-600 hover:border-forest-600',
  accent: 'bg-clay text-white border-clay hover:bg-clay-600 hover:border-clay-600',
  secondary:
  'bg-white text-ink border-line hover:border-line-strong hover:bg-surface-muted',
  ghost: 'bg-transparent text-ink border-transparent hover:bg-forest-50',
  danger:
  'bg-white text-state-danger border-state-danger/30 hover:bg-state-dangerBg'
};

const sizes: Record<Size, string> = {
  sm: 'text-small h-9 px-3.5',
  md: 'text-small h-11 px-5',
  lg: 'text-body h-12 px-6'
};

interface CommonProps {
  variant?: Variant;
  size?: Size;
  className?: string;
  children: React.ReactNode;
}

interface ButtonProps extends
  CommonProps,
  Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'children' | 'className'> {}

export function Button({
  variant = 'primary',
  size = 'md',
  className = '',
  children,
  ...rest
}: ButtonProps) {
  return (
    <button
      className={`${base} ${variants[variant]} ${sizes[size]} ${className}`}
      {...rest}>
      
      {children}
    </button>);

}

interface LinkButtonProps extends CommonProps {
  to: string;
  ariaLabel?: string;
}

export function LinkButton({
  to,
  variant = 'primary',
  size = 'md',
  className = '',
  ariaLabel,
  children
}: LinkButtonProps) {
  return (
    <Link
      to={to}
      aria-label={ariaLabel}
      className={`${base} ${variants[variant]} ${sizes[size]} ${className}`}>
      
      {children}
    </Link>);

}