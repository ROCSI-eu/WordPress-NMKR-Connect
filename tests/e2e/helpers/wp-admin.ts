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
    throw new Error('auth_failure=maintenance_mode');
  }
}

export async function expectWpAdmin(page: Page): Promise<void> {
  await expectNotWordPressMaintenancePage(page);
  if (/wp-login\.php/.test(page.url())) throw new Error('auth_failure=login_form_again');
  if (!/wp-admin/.test(page.url())) throw new Error('auth_failure=redirect_outside_admin');
  try {
    await expect(page.locator('#wpadminbar, #adminmenu, #wpbody-content').first()).toBeVisible();
  } catch {
    throw new Error('auth_failure=authenticated_shell_missing');
  }
}

export async function loginToWpAdmin(page: Page): Promise<string> {
  const baseUrl = requireEnv('WP_BASE_URL');
  const adminPath = env('WP_ADMIN_PATH', '/wp-admin');
  await page.goto(urlFor(baseUrl, adminPath));
  await expectWpAdmin(page);

  return baseUrl;
}
