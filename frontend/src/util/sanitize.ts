import { safeMediaUrl } from './url';

/**
 * HTML-Allowlist-Sanitizer für Toast-Inhalte (ToastSubject/ToastBody).
 * Legacy-Verhalten war `.html()` ohne Filter (= beliebiges JavaScript, AUDIT X2).
 */

const ALLOWED = new Set([
  'B', 'STRONG', 'I', 'EM', 'U', 'S', 'BR', 'P', 'DIV', 'SPAN', 'SMALL', 'MARK', 'CODE', 'PRE',
  'UL', 'OL', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'HR', 'IMG', 'SUB', 'SUP',
]);
/** Elemente, die samt Inhalt entfernt werden. */
const DROP = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'LINK', 'META', 'TEMPLATE', 'NOSCRIPT', 'SVG', 'MATH', 'FORM', 'INPUT', 'BUTTON', 'TEXTAREA', 'SELECT', 'VIDEO', 'AUDIO']);

export function sanitizeHtml(html: string, doc: Document = document): DocumentFragment {
  const tpl = doc.createElement('template');
  tpl.innerHTML = html;
  clean(tpl.content);
  return tpl.content;
}

function clean(node: ParentNode): void {
  for (const child of [...node.childNodes]) {
    if (child.nodeType === 8 /* Kommentar */) {
      child.remove();
      continue;
    }
    if (child.nodeType !== 1) continue;
    const el = child as Element;
    const tag = el.tagName.toUpperCase();
    if (DROP.has(tag)) {
      el.remove();
      continue;
    }
    if (!ALLOWED.has(tag)) {
      // Unbekannte Elemente (z. B. <a>, <font>, <center>) durch ihren Inhalt ersetzen.
      clean(el);
      el.replaceWith(...el.childNodes);
      continue;
    }
    for (const attr of [...el.attributes]) {
      const keep = tag === 'IMG' && (attr.name === 'src' || attr.name === 'alt');
      if (!keep) el.removeAttribute(attr.name);
    }
    if (tag === 'IMG') {
      const src = safeMediaUrl(el.getAttribute('src') ?? '');
      if (!src) {
        el.remove();
        continue;
      }
      el.setAttribute('src', src);
      el.setAttribute('decoding', 'async');
    }
    clean(el);
  }
}

/**
 * Erkennt den Legacy-„Reload-Toast“ (`<script>location.reload()</script>`), wie er von
 * websocket/index.php, newtoast.php, youtube.php, history.php und best.php gesendet wird.
 */
export function isReloadPayload(body: string): boolean {
  const scripts = [...body.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script\s*>/gi)];
  if (scripts.length === 0) return false;
  return scripts.some((m) =>
    /^(?:window\.|document\.)?location\.reload\(\s*(?:true|false)?\s*\);?$/.test((m[1] ?? '').replace(/\s+/g, '')),
  );
}

/** Text aus HTML für Kurzanzeigen/Logs. */
export function textOnly(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}
