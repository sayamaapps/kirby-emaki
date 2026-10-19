import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests of the viewer (SPEC.md §7c, §8): assets/emaki.js and
 * emaki.css against the fixture page tests/fixture/index.html, served from the
 * repository root by PHP's built-in server. Chromium only, no build step.
 *
 *   npm ci && npx playwright install chromium && npm run test:web
 *
 * CI runs the same under xvfb-run (job `browser` in .github/workflows/ci.yml).
 */
const PORT = 8731;
const BASIS = `http://127.0.0.1:${PORT}`;

export default defineConfig({
  testDir: './tests/browser',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: process.env.CI ? 'github' : [['list']],

  webServer: {
    command: `php -S 127.0.0.1:${PORT} -t .`,
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
