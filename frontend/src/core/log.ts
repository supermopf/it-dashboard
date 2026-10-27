/**
 * Logger mit begrenzter Historie (Ringpuffer). Keine sensiblen Daten loggen:
 * Toast-Inhalte und URLs werden nur gekürzt übernommen.
 */

export type LogLevel = 'debug' | 'info' | 'warn' | 'error';

export interface LogEntry {
  t: number;
  level: LogLevel;
  msg: string;
}

const ORDER: Record<LogLevel, number> = { debug: 0, info: 1, warn: 2, error: 3 };

export class Logger {
  private buf: LogEntry[] = [];
  private head = 0;
  private size = 0;
  consoleLevel: LogLevel = 'warn';

  constructor(private readonly capacity = 200) {}

  log(level: LogLevel, msg: string): void {
    const entry: LogEntry = { t: Date.now(), level, msg: truncate(msg, 300) };
    if (this.size < this.capacity) {
      this.buf.push(entry);
      this.size++;
    } else {
      this.buf[this.head] = entry;
      this.head = (this.head + 1) % this.capacity;
    }
    if (ORDER[level] >= ORDER[this.consoleLevel]) {
      const fn = level === 'error' ? console.error : level === 'warn' ? console.warn : console.log;
      fn(`[dashboard] ${entry.msg}`);
    }
  }

  debug(msg: string): void {
    this.log('debug', msg);
  }
  info(msg: string): void {
    this.log('info', msg);
  }
  warn(msg: string): void {
    this.log('warn', msg);
  }
  error(msg: string): void {
    this.log('error', msg);
  }

  /** Einträge in chronologischer Reihenfolge. */
  entries(): LogEntry[] {
    if (this.size < this.capacity) return [...this.buf];
    return [...this.buf.slice(this.head), ...this.buf.slice(0, this.head)];
  }

  get length(): number {
    return this.size;
  }
}

export function truncate(s: string, max: number): string {
  return s.length > max ? `${s.slice(0, max - 1)}…` : s;
}

export const log = new Logger();
