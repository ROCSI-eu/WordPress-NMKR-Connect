import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import fs from 'fs';
import path from 'path';
import { approvedAuthStatePath } from './tests/e2e/helpers/auth-state';

const envFile = path.resolve(__dirname, '.env.tests');

if (fs.existsSync(envFile)) {
  dotenv.config({ path: envFile });
}

const saveArtifacts = process.env.PW_SAVE_ARTIFACTS === 'true';
const htmlReportDir = process.env.PLAYWRIGHT_HTML_REPORT || 'playwright-report';
const testOutputDir = process.env.PLAYWRIGHT_TEST_OUTPUT_DIR || 'test-results';
const authStatePath = approvedAuthStatePath();
const retainAuthState = process.env.NMKR_RETAIN_AUTH_STATE || 'false';
if (!['true', 'false'].includes(retainAuthState)) throw new Error('NMKR_RETAIN_AUTH_STATE must be true or false.');

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: htmlReportDir, open: 'never' }],
  ],
  outputDir: testOutputDir,
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost',
    headless: true,
    screenshot: saveArtifacts ? 'only-on-failure' : 'off',
    trace: saveArtifacts ? 'retain-on-failure' : 'off',
    video: saveArtifacts ? 'retain-on-failure' : 'off',
  },
  projects: [
    {
      name: 'auth-setup',
      testMatch: /auth\.setup\.ts/,
      retries: 1,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'chromium',
      testIgnore: /auth\.(setup|cleanup)\.ts/,
      dependencies: ['auth-setup'],
      teardown: 'auth-cleanup',
      use: { ...devices['Desktop Chrome'], storageState: authStatePath },
    },
    {
      name: 'auth-cleanup',
      testMatch: /auth\.cleanup\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
