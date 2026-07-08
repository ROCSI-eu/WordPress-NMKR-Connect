import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';

dotenv.config({ path: process.env.DOTENV_CONFIG_PATH || '.env.tests' });

const saveArtifacts = process.env.PW_SAVE_ARTIFACTS === 'true';

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: [['html', { outputFolder: 'playwright-report', open: 'never' }], ['list']],
  use: {
    baseURL: process.env.WP_BASE_URL,
    headless: true,
    screenshot: saveArtifacts ? 'only-on-failure' : 'off',
    video: saveArtifacts ? 'retain-on-failure' : 'off',
    trace: saveArtifacts ? 'retain-on-failure' : 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  outputDir: 'test-results',
});
