import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';

const envFile = path.resolve(__dirname, '.env.tests');

if (fs.existsSync(envFile)) {
  dotenv.config({ path: envFile });
}

const saveArtifacts = process.env.PW_SAVE_ARTIFACTS === 'true';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  outputDir: 'test-results',
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost',
    headless: true,
    screenshot: saveArtifacts ? 'only-on-failure' : 'off',
    trace: saveArtifacts ? 'retain-on-failure' : 'off',
    video: saveArtifacts ? 'retain-on-failure' : 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
