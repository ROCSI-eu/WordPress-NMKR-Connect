import { test } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import os from 'os';
import { env, expectNotWordPressMaintenancePage, expectWpAdmin, handleAdminEmailVerification, requireEnv, urlFor } from './helpers/wp-admin';

const statePath = process.env.NMKR_AUTH_STATE_PATH || path.join(os.tmpdir(), `nmkr-connect-auth-${process.pid}.json`);

test('authenticate WordPress admin state', async ({ page, context }) => {
  const baseUrl = requireEnv('WP_BASE_URL');
  const username = requireEnv('WP_ADMIN_USER');
  const password = requireEnv('WP_ADMIN_PASSWORD');
  try {
    await page.goto(urlFor(baseUrl, '/wp-login.php'));
    await expectNotWordPressMaintenancePage(page);
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
    await page.waitForLoadState('domcontentloaded');
    await handleAdminEmailVerification(page);
    await page.goto(urlFor(baseUrl, env('WP_ADMIN_PATH', '/wp-admin')));
    await expectWpAdmin(page);
  } catch (error) {
    const message = error instanceof Error && /^auth_failure=/.test(error.message) ? error.message : 'auth_failure=navigation_or_transport_failure';
    throw new Error(message);
  }
  fs.mkdirSync(path.dirname(statePath), { recursive: true, mode: 0o700 });
  await context.storageState({ path: statePath });
  fs.chmodSync(statePath, 0o600);
});
