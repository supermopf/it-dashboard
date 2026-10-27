import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/unit/**/*.test.ts'],
    restoreMocks: true,
    // Wenige Worker: jsdom-Start ist auf /mnt/c (WSL) langsam.
    maxWorkers: 2,
    pool: 'threads',
    testTimeout: 15000,
  },
});
