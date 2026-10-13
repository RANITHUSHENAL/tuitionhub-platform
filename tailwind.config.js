/** @type {import('tailwindcss').Config} */
export default {
  content: [
  './index.html',
  './src/**/*.{js,ts,jsx,tsx}'
],
  theme: {
    extend: {
      colors: {
        canvas: '#E7EFE7',
        surface: {
          DEFAULT: '#FFFFFF',
          muted: '#F4F7F3',
          sunk: '#EDF2EC',
        },
        ink: {
          DEFAULT: '#1B211D',
          soft: '#55605A',
          faint: '#8B958E',
        },
        forest: {
          DEFAULT: '#13402C',
          600: '#1A5539',
          400: '#3D7A5C',
          100: '#DCE9E1',
          50: '#EFF5F0',
        },
        clay: {
          DEFAULT: '#B04A2F',
          600: '#983E27',
          100: '#F7E3DC',
        },
        line: {
          DEFAULT: '#D8E2D7',
          strong: '#BECBBD',
        },
        state: {
          success: '#1A5539',
          successBg: '#DCE9E1',
          warn: '#8A5B10',
          warnBg: '#F7EAD3',
          danger: '#9A2F22',
          dangerBg: '#F7DEDA',
          info: '#2C4F63',
          infoBg: '#DDE8EE',
        },
      },
      fontFamily: {
        sans: [
          'Inter',
          'Noto Sans Sinhala',
          'Noto Sans Tamil',
          'system-ui',
          'sans-serif',
        ],
      },
      fontSize: {
        display: ['clamp(2.75rem, 5.2vw, 4.25rem)', { lineHeight: '1.02', letterSpacing: '-0.035em', fontWeight: '700' }],
        h1: ['clamp(2rem, 3.2vw, 2.75rem)', { lineHeight: '1.08', letterSpacing: '-0.028em', fontWeight: '700' }],
        h2: ['clamp(1.5rem, 2.2vw, 2rem)', { lineHeight: '1.15', letterSpacing: '-0.022em', fontWeight: '650' }],
        h3: ['1.125rem', { lineHeight: '1.3', letterSpacing: '-0.012em', fontWeight: '600' }],
        body: ['1rem', { lineHeight: '1.6' }],
        small: ['0.875rem', { lineHeight: '1.5' }],
        caption: ['0.75rem', { lineHeight: '1.4', letterSpacing: '0.01em' }],
      },
      spacing: {
        4.5: '1.125rem',
      },
      borderRadius: {
        xl: '0.75rem',
        '2xl': '1rem',
        '3xl': '1.5rem',
      },
      boxShadow: {
        card: '0 1px 2px rgba(19, 64, 44, 0.04), 0 8px 24px -16px rgba(19, 64, 44, 0.24)',
        lift: '0 2px 4px rgba(19, 64, 44, 0.05), 0 18px 44px -24px rgba(19, 64, 44, 0.38)',
        panel: '0 24px 64px -28px rgba(19, 64, 44, 0.45)',
      },
      transitionTimingFunction: {
        out: 'cubic-bezier(0.23, 1, 0.32, 1)',
      },
    },
  },
  plugins: [],
}
