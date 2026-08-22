import { spawn } from 'node:child_process';

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const fail = name => { throw new Error(name); };
const post = async (context, url, form) => {
  const response = await context.request.post(url, { form, timeout: 30000 });
  let json; try { json = await response.json(); } catch { fail('malformed-response'); }
  if (!response.ok() || !json?.success) fail('request-refused');
  return json.data;
};
export function assertProgress(previous, data) {
  const value = Number(data?.progress ?? data?.percentage);
  if (!Number.isFinite(value) || value < previous || value < 0 || value > 100) fail('invalid-progress');
  return value;
}
export async function runSyntheticLifecycle({ playwright, env = process.env }) {
  const browser = await playwright.chromium.launch({ headless: true });
  const context = await browser.newContext({ acceptDownloads: false });
  const page = await context.newPage();
  let started = false;
  try {
    await page.goto(`${env.NMKR_SYNTHETIC_BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(env.NMKR_SYNTHETIC_ADMIN_USER);
    await page.locator('#user_pass').fill(env.NMKR_SYNTHETIC_ADMIN_PASSWORD);
    await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#wp-submit').click()]);
    await page.goto(`${env.NMKR_SYNTHETIC_BASE_URL}/wp-admin/admin.php?page=nmkr-connect-dashboard`, { waitUntil: 'domcontentloaded' });
    const nonce = await page.evaluate(() => globalThis.nmkrSyncData?.nonce || globalThis.nmkr_sync_ajax?.nonce || '');
    if (!nonce) fail('nonce-missing');
    const ajax = `${env.NMKR_SYNTHETIC_BASE_URL}/wp-admin/admin-ajax.php`;
    started = true;
    const start = await post(context, ajax, { action: 'nmkr_start_sync', nonce });
    if (!/^[0-9a-f-]{36}$/i.test(start?.run_id || '')) fail('run-id-invalid');
    const wp = spawn(env.NMKR_SYNTHETIC_WP_CLI, ['--path', env.NMKR_SYNTHETIC_WP_ROOT, 'cron', 'event', 'run', 'nmkr_execute_sync_background', '--due-now'], { stdio: ['ignore','ignore','ignore'], env });
    let previous = 0; let terminal = false;
    while (!terminal) {
      await sleep(3000);
      const data = await post(context, ajax, { action: 'nmkr_sync_progress', nonce });
      previous = assertProgress(previous, data);
      terminal = data?.finished === true && data?.in_progress === false && data?.status === 'completed';
      if (data?.status === 'failed' || data?.status === 'stopped') fail('terminal-failure');
    }
    const exit = await new Promise(resolve => wp.once('exit', code => resolve(code)));
    if (exit !== 0) fail('worker-failed');
    return { runId: start.run_id, starts: 1, completed: true };
  } finally { await browser.close(); }
}
if (import.meta.url === `file://${process.argv[1]}`) {
  import('@playwright/test').then(({ chromium }) => runSyntheticLifecycle({ playwright: { chromium } })).then(() => process.stdout.write('Synthetic driver: PASS\n')).catch(() => { process.stderr.write(`Synthetic driver: FAIL (${process.env.CI ? 'public-safe' : 'private diagnostic retained'})\n`); process.exitCode = 1; });
}
