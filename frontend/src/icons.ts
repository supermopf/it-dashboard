/**
 * Selektiv eingebundene SVG-Icons (24×24, Strichstärke über CSS). Ersetzt Font Awesome (CDN).
 * Pfade eigenständig gezeichnet, nur einfache Grundformen.
 */

const P: Record<string, string> = {
  cloud: '<path d="M7 18h10a4 4 0 0 0 .6-7.95A6 6 0 0 0 6.1 9.2 4.5 4.5 0 0 0 7 18z"/>',
  lifebuoy: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M5.6 5.6l3.6 3.6M14.8 14.8l3.6 3.6M18.4 5.6l-3.6 3.6M9.2 14.8l-3.6 3.6"/>',
  chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  bell: '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
  save: '<path d="M5 3h11l4 4v14H4V3z"/><path d="M8 3v5h7V3M8 21v-7h8v7"/>',
  branch: '<circle cx="6" cy="5" r="2"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="8" r="2"/><path d="M6 7v10M18 10c0 4-6 3-11 7"/>',
  wrench: '<path d="M14.5 6.5a4 4 0 0 0 5 5L12 19l-3-3 7.5-7.5a4 4 0 0 0-2-2zM9 16l-4 4"/>',
  pager: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10h10M7 14h5"/>',
  gauge: '<path d="M4 18a8 8 0 1 1 16 0"/><path d="M12 18l4-6"/>',
  alert: '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
  'alert-circle': '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/>',
  x: '<path d="M6 6l12 12M18 6L6 18"/>',
  check: '<path d="M4 12l5 5L20 6"/>',
  refresh: '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5V8"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  'wifi-off': '<path d="M2 8.5a15 15 0 0 1 4.5-2.8M10 5.1a15 15 0 0 1 12 3.4M5 12a10 10 0 0 1 3-1.9M15 10.4a10 10 0 0 1 4 1.6M8.5 15.5a5 5 0 0 1 7 0M12 19h.01M3 3l18 18"/>',
  plug: '<path d="M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4"/>',
  music: '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
};

export function icon(name: string, cls = 'icon'): string {
  const body = P[name] ?? P.info!;
  return `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${body}</svg>`;
}

export function iconEl(name: string, cls = 'icon'): Element {
  const tpl = document.createElement('template');
  tpl.innerHTML = icon(name, cls);
  return tpl.content.firstElementChild!;
}

/** Font-Awesome-Klassen aus den Legacy-Fragmenten → eigene Icons. */
const FA_MAP: Record<string, string> = {
  'fa-exclamation-triangle': 'alert',
  'fa-exclamation-circle': 'alert-circle',
  'fa-times': 'x',
  'fa-refresh': 'refresh',
  'fa-sync': 'refresh',
  'fa-info-circle': 'info',
  'fa-clock-o': 'clock',
  'fa-clock': 'clock',
  'fa-check': 'check',
  'fa-check-circle': 'check',
  'fa-bell': 'bell',
  'fa-cloud': 'cloud',
};

/** Ersetzt `<i class="fa fa-…">` durch Inline-SVG (keine Icon-Font nötig). */
export function replaceFontAwesome(root: ParentNode): void {
  for (const i of [...root.querySelectorAll('i.fa, i.fas, i.far')]) {
    const cls = [...i.classList].find((c) => FA_MAP[c]);
    if (!cls) {
      i.remove();
      continue;
    }
    const svg = iconEl(FA_MAP[cls]!, 'icon icon-inline');
    const style = i.getAttribute('style');
    if (style) svg.setAttribute('style', style);
    i.replaceWith(svg);
  }
}
