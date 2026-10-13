import { useEffect, useState } from 'react';

/** Live countdown to an epoch-ms deadline. Returns zeros when no deadline. */
export function useCountdown(deadline?: number) {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!deadline) return;
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [deadline]);

  const left = deadline ? Math.max(0, deadline - now) : 0;
  return {
    done: !!deadline && left === 0,
    hours: Math.floor(left / 3_600_000),
    minutes: Math.floor(left % 3_600_000 / 60_000),
    seconds: Math.floor(left % 60_000 / 1000)
  };
}