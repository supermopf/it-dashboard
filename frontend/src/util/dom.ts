/** Kleine DOM-Helfer – Text immer per textContent, nie per innerHTML. */

type Attrs = Record<string, string | number | boolean | null | undefined>;

export function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  attrs: Attrs = {},
  ...children: Array<Node | string | null | undefined>
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') node.className = String(v);
    else if (k === 'text') node.textContent = String(v);
    else node.setAttribute(k, v === true ? '' : String(v));
  }
  for (const c of children) {
    if (c === null || c === undefined) continue;
    node.append(typeof c === 'string' ? document.createTextNode(c) : c);
  }
  return node;
}

/** Text nur schreiben, wenn er sich geändert hat (vermeidet unnötige Style-/Layout-Arbeit). */
export function setText(node: Element, text: string): void {
  if (node.textContent !== text) node.textContent = text;
}

export function setClass(node: Element, cls: string, on: boolean): void {
  if (node.classList.contains(cls) !== on) node.classList.toggle(cls, on);
}

export function prefersReducedMotion(): boolean {
  return typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/** Wartet auf das Ende einer Transition, höchstens `maxMs`. */
export function afterTransition(node: Element, maxMs: number): Promise<void> {
  return new Promise((resolve) => {
    let done = false;
    const finish = () => {
      if (done) return;
      done = true;
      node.removeEventListener('transitionend', onEnd);
      clearTimeout(t);
      resolve();
    };
    const onEnd = (ev: Event) => {
      if (ev.target === node) finish();
    };
    node.addEventListener('transitionend', onEnd);
    const t = setTimeout(finish, maxMs);
  });
}

export function nextFrame(): Promise<void> {
  return new Promise((r) => requestAnimationFrame(() => r()));
}
