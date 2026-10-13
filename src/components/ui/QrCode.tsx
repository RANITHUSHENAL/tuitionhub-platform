import React, { useMemo } from 'react';

const GRID = 25;

function hash(str: string) {
  let h = 2166136261;
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

function finder(x: number, y: number, cx: number, cy: number) {
  const dx = x - cx;
  const dy = y - cy;
  if (dx < 0 || dy < 0 || dx > 6 || dy > 6) return null;
  const edge = dx === 0 || dy === 0 || dx === 6 || dy === 6;
  const core = dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4;
  return edge || core;
}

/**
 * Visual stand-in for a QR code, deterministic per seed. Changing the seed
 * visibly regenerates the pattern — used to show rotating session codes.
 */
export function QrCode({ seed, size = 180, label }: {seed: string;size?: number;label: string;}) {
  const cells = useMemo(() => {
    let s = hash(seed);
    const out: boolean[] = [];
    for (let y = 0; y < GRID; y++) {
      for (let x = 0; x < GRID; x++) {
        const f =
        finder(x, y, 0, 0) ?? finder(x, y, GRID - 7, 0) ?? finder(x, y, 0, GRID - 7);
        if (f !== null) {
          out.push(f);
          continue;
        }
        if (x < 8 && y < 8 || x > GRID - 9 && y < 8 || x < 8 && y > GRID - 9) {
          out.push(false);
          continue;
        }
        s = Math.imul(s ^ s >>> 15, 2246822507) >>> 0;
        s = Math.imul(s ^ s >>> 13, 3266489909) >>> 0;
        out.push((s & 3) === 0 || (s & 7) === 5);
      }
    }
    return out;
  }, [seed]);

  return (
    <svg
      width={size}
      height={size}
      viewBox={`-2 -2 ${GRID + 4} ${GRID + 4}`}
      role="img"
      aria-label={label}
      className="rounded-xl bg-white"
      shapeRendering="crispEdges">
      
      <rect x="-2" y="-2" width={GRID + 4} height={GRID + 4} fill="#FFFFFF" />
      {cells.map((on, i) =>
      on ? <rect key={i} x={i % GRID} y={Math.floor(i / GRID)} width="1" height="1" fill="#1B211D" /> : null
      )}
    </svg>);

}