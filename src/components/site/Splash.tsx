import React, { useEffect, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

const SESSION_KEY = 'th-splash-seen';

/** One brand beat per browser session — under a second, then out of the way. */
export function Splash() {
  const [show, setShow] = useState(() => {
    try {
      return window.sessionStorage.getItem(SESSION_KEY) !== '1';
    } catch {
      return false;
    }
  });

  useEffect(() => {
    if (!show) return;
    window.sessionStorage.setItem(SESSION_KEY, '1');
    const id = window.setTimeout(() => setShow(false), 900);
    return () => window.clearTimeout(id);
  }, [show]);

  return (
    <AnimatePresence>
      {show ?
      <motion.div
        key="splash"
        initial={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.24, ease: [0.23, 1, 0.32, 1] }}
        className="fixed inset-0 z-[100] flex items-center justify-center bg-canvas"
        aria-hidden="true">
        
          <div className="flex flex-col items-center">
            <motion.p
            initial={{ opacity: 0, y: 6, scale: 0.97 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ duration: 0.28, ease: [0.23, 1, 0.32, 1] }}
            className="text-h1 font-bold tracking-tight text-ink">
            
              Tuition<span className="text-forest">Hub</span>
            </motion.p>
            <div className="mt-4 h-0.5 w-24 overflow-hidden rounded-full bg-line">
              <motion.div
              initial={{ x: '-100%' }}
              animate={{ x: '0%' }}
              transition={{ duration: 0.5, delay: 0.15, ease: [0.23, 1, 0.32, 1] }}
              className="h-full w-full rounded-full bg-clay" />
            
            </div>
          </div>
        </motion.div> :
      null}
    </AnimatePresence>);

}