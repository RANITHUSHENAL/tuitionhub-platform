import React, { useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { MenuIcon, XIcon } from 'lucide-react';
import { LinkButton } from '../ui/Button';
import { AccountMenu } from '../app/AccountMenu';
import { useAuth } from '../../contexts/AuthContext';
import { roleHome, roleLabel } from '../../data/accounts';

const nav = [
{ to: '/search', label: 'Classes' },
{ to: '/institutes', label: 'Institutes' },
{ to: '/teachers', label: 'Teachers' },
{ to: '/study', label: 'Study' }];


const languages = ['සිංහල', 'தமிழ்', 'EN'];

export function SiteHeader() {
  const [open, setOpen] = useState(false);
  const [lang, setLang] = useState('EN');
  const { user, signOut } = useAuth();
  const navigate = useNavigate();

  const logout = () => {
    signOut();
    setOpen(false);
    navigate('/');
  };

  return (
    <header className="sticky top-0 z-30 border-b border-line/70 bg-canvas/90 backdrop-blur">
      <div className="mx-auto flex h-[68px] max-w-[1200px] items-center gap-6 px-5 lg:px-8">
        <Link to="/" className="text-h3 font-bold tracking-tight text-ink">
          Tuition<span className="text-forest">Hub</span>
        </Link>

        <nav aria-label="Main" className="hidden flex-1 items-center gap-1 lg:flex">
          {nav.map((item) =>
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
            `rounded-lg px-3 py-2 text-small font-medium transition-colors duration-150 ease-out ${
            isActive ? 'text-forest' : 'text-ink-soft hover:text-ink'}`

            }>
            
              {item.label}
            </NavLink>
          )}
        </nav>

        <div className="ml-auto hidden items-center gap-3 lg:flex">
          <div
            role="radiogroup"
            aria-label="Language"
            className="flex rounded-full border border-line bg-white p-0.5">
            
            {languages.map((l) =>
            <button
              key={l}
              role="radio"
              aria-checked={lang === l}
              onClick={() => setLang(l)}
              className={`rounded-full px-2.5 py-1 text-caption font-medium transition-colors duration-150 ease-out ${
              lang === l ? 'bg-forest text-white' : 'text-ink-soft hover:text-ink'}`
              }>
              
                {l}
              </button>
            )}
          </div>

          {user ?
          <>
              <LinkButton to={roleHome[user.role]} size="sm" variant="secondary">
                My {roleLabel[user.role].toLowerCase()} home
              </LinkButton>
              <div className="w-56">
                <AccountMenu />
              </div>
            </> :

          <>
              <Link
              to="/auth"
              className="rounded-lg px-2 py-2 text-small font-medium text-ink-soft transition-colors duration-150 ease-out hover:text-ink">
              
                Sign in
              </Link>
              <LinkButton to="/auth?mode=create" size="sm">
                Create account
              </LinkButton>
            </>
          }
        </div>

        <button
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          aria-label={open ? 'Close menu' : 'Open menu'}
          className="ml-auto rounded-lg p-2 text-ink lg:hidden">
          
          {open ? <XIcon className="h-5 w-5" /> : <MenuIcon className="h-5 w-5" />}
        </button>
      </div>

      {open ?
      <div className="border-t border-line bg-canvas px-5 py-4 lg:hidden">
          <nav aria-label="Mobile" className="grid gap-1">
            {nav.map((item) =>
          <NavLink
            key={item.to}
            to={item.to}
            onClick={() => setOpen(false)}
            className="rounded-lg px-2 py-2.5 text-body font-medium text-ink">
            
                {item.label}
              </NavLink>
          )}
          </nav>
          <div className="mt-4 flex items-center gap-3">
            {user ?
          <>
                <LinkButton to={roleHome[user.role]} size="sm" className="flex-1">
                  {roleLabel[user.role]} home
                </LinkButton>
                <button
              onClick={logout}
              className="h-9 flex-1 rounded-xl border border-line bg-white text-small font-medium text-ink">
              
                  Sign out
                </button>
              </> :

          <>
                <LinkButton to="/auth?mode=create" size="sm" className="flex-1">
                  Create account
                </LinkButton>
                <LinkButton to="/auth" size="sm" variant="secondary" className="flex-1">
                  Sign in
                </LinkButton>
              </>
          }
          </div>
        </div> :
      null}
    </header>);

}