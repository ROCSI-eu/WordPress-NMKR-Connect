import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/security',
  timeout: 30_000,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  reporter: [['list']],
  outputDir: '/tmp/nmkr-security-rendering-results',
  use: { headless: true, screenshot: 'off', trace: 'off', video: 'off' },
});
