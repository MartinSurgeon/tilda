/** @type {import('tailwindcss').Config} */
// Colours resolve to the CSS variables in resources/css/app.css (the single
// source of truth). Variables hold RGB channels so opacity modifiers work.
const v = (name) => `rgb(var(--rgb-${name}) / <alpha-value>)`;

module.exports = {
  content: ['./views/**/*.php', './app/**/*.php', './resources/js/**/*.js', './public/assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        teal: {
          DEFAULT: v('teal'),       // primary
          dark: v('teal-dark'),     // hover
          darker: v('teal-darker'), // text on tints
          tint: v('teal-tint'),
        },
        green: { DEFAULT: v('green'), text: v('green-text'), tint: v('green-tint') },
        'light-green': { DEFAULT: v('light-green'), 1: v('light-green-1') },
        charcoal: v('charcoal'),    // body text
        ink: v('ink'),              // headings
        muted: v('muted'),          // secondary text
        midnight: { DEFAULT: v('midnight'), tint: v('midnight-tint') }, // secondary / links
        cornflower: v('cornflower'),// info accents only (fails AA as text)
        info: { text: v('info-text'), tint: v('info-tint') },
        warning: { DEFAULT: v('warning'), text: v('warning-text'), tint: v('warning-tint') },
        danger: { DEFAULT: v('danger'), dark: v('danger-dark'), tint: v('danger-tint') },
        neutral: { text: v('neutral-text'), tint: v('neutral-tint') },
        smoke: { DEFAULT: v('white-smoke'), 1: v('white-smoke-1'), 2: v('white-smoke-2') },
        line: { DEFAULT: v('line'), strong: v('line-strong') },
      },
      backgroundImage: {
        'brand-linear': 'var(--gradient-linear)',
        'brand-linear-1': 'var(--gradient-linear-1)',
      },
      fontFamily: {
        sans: ['system-ui', '-apple-system', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
      },
      minHeight: { touch: '44px' },
      minWidth: { touch: '44px' },
      spacing: { 'bottom-nav': '4.5rem', sidebar: '16rem' },
      boxShadow: {
        card: '0 1px 2px rgb(0 0 0 / 0.04), 0 1px 3px rgb(0 0 0 / 0.06)',
        raised: '0 4px 12px rgb(0 0 0 / 0.08)',
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
