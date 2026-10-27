import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  { ignores: ['node_modules/**', 'test-results/**', 'playwright-report/**', '../monitor/dist/**'] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['**/*.ts'],
    rules: {
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-restricted-syntax': [
        'error',
        {
          selector: "CallExpression[callee.name='setInterval']",
          message: 'Timer bitte über den zentralen Scheduler (core/scheduler.ts) anlegen.',
        },
      ],
    },
  },
  {
    files: ['src/core/scheduler.ts', 'src/debug/**', 'tests/**', 'src/panels/fragment-panel.ts'],
    rules: { 'no-restricted-syntax': 'off' },
  },
  {
    files: ['src/legacy/*.js'],
    languageOptions: { globals: { window: 'readonly' } },
  },
  {
    // Node-Skripte mit Playwright: Code in page.evaluate() läuft im Browser.
    files: ['scripts/**/*.mjs', 'tests/**/*.mjs'],
    languageOptions: {
      globals: { window: 'readonly', document: 'readonly', performance: 'readonly', fetch: 'readonly', requestAnimationFrame: 'readonly', MutationObserver: 'readonly' },
    },
  },
  {
    files: ['**/*.mjs', '**/*.js'],
    languageOptions: {
      globals: { process: 'readonly', console: 'readonly', URL: 'readonly', Buffer: 'readonly', setTimeout: 'readonly', clearTimeout: 'readonly', setInterval: 'readonly', clearInterval: 'readonly' },
    },
  },
);
