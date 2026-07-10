import { expect, Page } from '@playwright/test';

export const requiredEnv = ['WP_BASE_URL', 'WP_ADMIN_USER', 'WP_ADMIN_PASSWORD'] as const;

export function env(name: string, fallback = ''): string {
  return process.env[name] || fallback;
}

export function requireEnv(name: (typeof requiredEnv)[number]): string {
  const value = process.env[name];

  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }

  return value;
}

export function urlFor(baseUrl: string, path: string): string {
  return new URL(path, baseUrl).toString();
}

export function cssAttrValue(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

export async function handleAdminEmailVerification(page: Page): Promise<void> {
  const emailVerificationHeading = page.getByRole('heading', {
    name: /administration email verification/i,
  });

  if (await emailVerificationHeading.isVisible({ timeout: 3000 }).catch(() => false)) {
    const confirmButton = page.getByRole('button', { name: /email is correct/i });

    if (await confirmButton.isVisible({ timeout: 3000 }).catch(() => false)) {
      await confirmButton.click();
      await page.waitForLoadState('domcontentloaded');
    }
  }
}

export async function expectNotWordPressMaintenancePage(page: Page): Promise<void> {
  const maintenanceText = page.getByText(
    /Briefly unavailable for scheduled maintenance|Check back in a minute/i,
  );

  if (await maintenanceText.isVisible({ timeout: 500 }).catch(() => false)) {
    throw new Error('WordPress maintenance page was displayed during admin readiness.');
  }
}

export async function expectWpAdmin(page: Page): Promise<void> {
  await expectNotWordPressMaintenancePage(page);
  await expect(page).toHaveURL(/wp-admin/);
  await expect(page.locator('#wpadminbar, #adminmenu, #wpbody-content').first()).toBeVisible();
}

export async function loginToWpAdmin(page: Page): Promise<string> {
  const baseUrl = requireEnv('WP_BASE_URL');
  const username = requireEnv('WP_ADMIN_USER');
  const password = requireEnv('WP_ADMIN_PASSWORD');
  const adminPath = env('WP_ADMIN_PATH', '/wp-admin');

  await page.goto(urlFor(baseUrl, '/wp-login.php'));
  await expectNotWordPressMaintenancePage(page);
  await expect(page.locator('#user_login')).toBeVisible();
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();

  await page.waitForLoadState('domcontentloaded');
  await handleAdminEmailVerification(page);
  await page.goto(urlFor(baseUrl, adminPath));
  await expectWpAdmin(page);

  return baseUrl;
}
