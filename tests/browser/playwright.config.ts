import { defineConfig, devices } from '@playwright/test';
import { join } from 'node:path';

/**
 * Browser tests of the viewer (SPEC.md §7c, §8): assets/emaki.js and
 * emaki.css against the fixture page tests/fixture/index.html, served from the
 * repository root by PHP's built-in server. Chromium only, no build step.
 * The fixture images are generated before the run (global-setup.ts).
 *
 *   npm ci --prefix tests/browser && npm --prefix tests/browser run test:web
 *
 * CI runs the same under xvfb-run (job `browser` in .github/workflows/ci.yml).
 */
const PORT = 8731;
const BASIS = `http://127.0.0.1:${PORT}`;
const REPOSITORY = join(__dirname, '..', '..');

export default defineConfig({
  testDir: '.',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: process.env.CI ? 'github' : [['list']],
  globalSetup: './global-setup.ts',

  webServer: {
    command: `php -S 127.0.0.1:${PORT} -t .`,
    cwd: REPOSITORY,
    url: `${BASIS}/tests/fixture/index.html`,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },

  use: {
    baseURL: BASIS,
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
