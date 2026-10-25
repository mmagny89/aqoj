/* Icônes au trait, décoratives : le libellé est toujours porté par le texte voisin. */
const base = {
  width: 24,
  height: 24,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2.2,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
}

export const HomeIcon = (props) => (
  <svg {...base} {...props}><path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></svg>
)
export const SparkIcon = (props) => (
  <svg {...base} {...props}><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" /><path d="M19 17v4M17 19h4" /></svg>
)
export const SearchIcon = (props) => (
  <svg {...base} {...props}><circle cx="11" cy="11" r="7" /><path d="M21 21l-5-5" /></svg>
)
export const ShelfIcon = (props) => (
  <svg {...base} {...props}><rect x="3" y="3" width="7" height="18" rx="1" /><rect x="12" y="3" width="4" height="18" rx="1" /><path d="M18 5l3 .8-3.6 15.2-3-.8z" /></svg>
)
export const DiceIcon = (props) => (
  <svg {...base} {...props}><rect x="3" y="3" width="18" height="18" rx="3" /><circle cx="8.5" cy="8.5" r="1" /><circle cx="15.5" cy="15.5" r="1" /><circle cx="12" cy="12" r="1" /></svg>
)
export const DownloadIcon = (props) => (
  <svg {...base} {...props}><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v3h16v-3" /></svg>
)
export const HelpIcon = (props) => (
  <svg {...base} {...props}><circle cx="12" cy="12" r="9" /><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01" /></svg>
)
export const ToolIcon = (props) => (
  <svg {...base} {...props}><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6z" /></svg>
)
export const UserIcon = (props) => (
  <svg {...base} {...props}><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
)
export const LogoutIcon = (props) => (
  <svg {...base} {...props}><path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 8l-4 4 4 4M6 12h10" /></svg>
)
export const EyeIcon = (props) => (
  <svg {...base} {...props}><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
)
export const EyeOffIcon = (props) => (
  <svg {...base} {...props}><path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.1 4M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.4-1M9.9 9.9a3 3 0 0 0 4.2 4.2" /></svg>
)
