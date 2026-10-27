import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';

const PHP_PORT = Number(process.env.PHP_PORT ?? 8090);
const WS_PORT = Number(process.env.WS_PORT ?? 9100);

/**
 * Browser-Tests gegen die echte PHP-Shell (php -S) mit Test-Konfiguration und Mock-WebSocket-Server.
 * Voraussetzung: `npm run build` (monitor/dist muss existieren).
 */
export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 60_000,
  workers: 1,
  fullyParallel: false,
  reporter: [['list']],
  use: {
    baseURL: `http://127.0.0.1:${PHP_PORT}`,
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    trace: 'retain-on-failure',
  },
  webServer: [
    {
      command: `node tests/mock-server/server.mjs`,
      env: { WS_PORT: String(WS_PORT) },
      port: WS_PORT + 1,
      reuseExistingServer: false,
    },
    {
      command: `php -S 127.0.0.1:${PHP_PORT} -t .`,
      env: {
        DASHBOARD_CONFIG: resolve(import.meta.dirname, 'tests/fixtures/config.test.php'),
        TEST_WS_URL: `ws://127.0.0.1:${WS_PORT}/`,
      },
      cwd: '..',
      port: PHP_PORT,
      stdout: 'ignore',
      stderr: 'ignore',
      reuseExistingServer: false,
    },
  ],
});
