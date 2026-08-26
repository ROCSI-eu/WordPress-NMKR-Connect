import { spawn, spawnSync } from 'node:child_process';
import { chmodSync, closeSync, openSync, writeFileSync } from 'node:fs';

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const fail = name => { throw new Error(name); };
const post = async (context, url, form) => {
  const response = await context.request.post(url, { form, timeout: 30000 });
  let json; try { json = await response.json(); } catch { fail('malformed-response'); }
  if (!response.ok() || !json?.success) fail('request-refused');
  return json.data;
};
const refuse = async (context, url, form) => {
  const response = await context.request.post(url, { form, timeout: 30000 });
  let json; try { json = await response.json(); } catch { fail('duplicate-start-ambiguous'); }
  if (response.status() !== 409 || json?.success !== false) fail('duplicate-start-accepted');
};
const syntheticActivationVariables = ['NMKR_SYNTHETIC_SENTINEL', 'NMKR_SYNTHETIC_EXECUTION_ID', 'NMKR_SYNTHETIC_PROFILE', 'NMKR_SYNTHETIC_EXPIRY'];
export const providerInertEnvironment = env => {
  const inert = { ...env };
  for (const name of syntheticActivationVariables) delete inert[name];
  return inert;
};
export const verifyWorker = (env, runId) => {
  const diagnostic = openSync(env.NMKR_SYNTHETIC_WORKER_IDENTITY_LOG, 'wx', 0o600);
  let checked;
  try {
    checked = spawnSync(env.NMKR_SYNTHETIC_WP_CLI, [`--path=${env.NMKR_SYNTHETIC_WP_ROOT}`, 'eval-file', env.NMKR_SYNTHETIC_WORKER_IDENTITY_HELPER], {
      env: { ...providerInertEnvironment(env), NMKR_SYNTHETIC_EXPECTED_RUN_ID: runId },
      stdio: ['ignore', 'ignore', diagnostic],
    });
  } finally {
    closeSync(diagnostic);
  }
  const classified = spawnSync('python3', [env.NMKR_SYNTHETIC_DIAGNOSTIC_CLASSIFIER, env.NMKR_SYNTHETIC_WORKER_IDENTITY_LOG, '60'], {
    env: providerInertEnvironment(env), stdio: 'ignore',
  });
  if (checked.status !== 0 || (classified.status !== 0 && classified.status !== 3)) fail('worker-event-identity');
};
export function assertProgress(previous, data) {
  const value = Number(data?.progress ?? data?.percentage);
  if (!Number.isFinite(value) || value < previous || value < 0 || value > 100) fail('invalid-progress');
  return value;
}
export function canonicalNonce(runtimeNonce, domNonce) {
  if (typeof runtimeNonce !== 'string' || typeof domNonce !== 'string' || runtimeNonce.length === 0 || domNonce.length === 0) fail('nonce-missing');
  if (!/^[a-z0-9]{10}$/i.test(runtimeNonce) || !/^[a-z0-9]{10}$/i.test(domNonce)) fail('nonce-malformed');
  if (runtimeNonce !== domNonce) fail('nonce-mismatch');
  return runtimeNonce;
}
export async function runSyntheticLifecycle({ playwright, env = process.env }) {
  const timeoutSeconds = Number(env.NMKR_SYNTHETIC_TIMEOUT_SECONDS || 1200);
  if (!Number.isInteger(timeoutSeconds) || timeoutSeconds < 60 || timeoutSeconds > 2700) fail('timeout-invalid');
  const browser = await playwright.chromium.launch({ headless: true });
  const context = await browser.newContext({ acceptDownloads: false });
  const page = await context.newPage();
  let wp;
  try {
    await page.goto(`${env.NMKR_SYNTHETIC_BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(env.NMKR_SYNTHETIC_ADMIN_USER);
    await page.locator('#user_pass').fill(env.NMKR_SYNTHETIC_ADMIN_PASSWORD);
    await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#wp-submit').click()]);
    let statusProbes = 0;
    let resolveStatusProbes;
    const statusProbesComplete = new Promise(resolve => { resolveStatusProbes = resolve; });
    await page.route('**/wp-admin/admin-ajax.php', async route => {
      const request = route.request();
      const form = new URLSearchParams(request.postData() || '');
      if (request.method() !== 'POST' || form.get('action') !== 'nmkr_check_api_status') return route.continue();
      statusProbes++;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: { message: 'Synthetic status probe bypassed.', connected: true, sync_in_progress: false } }),
      });
      if (statusProbes === 2) resolveStatusProbes();
    });
    await page.goto(`${env.NMKR_SYNTHETIC_BASE_URL}/wp-admin/admin.php?page=nmkr-connect-dashboard`, { waitUntil: 'domcontentloaded' });
    await Promise.race([statusProbesComplete, sleep(30000).then(() => fail('status-probes-missing'))]);
    if (statusProbes !== 2) fail('status-probes-unexpected');
    const nonceSources = await page.evaluate(() => ({
      runtime: globalThis.nmkrSyncProgress?.nonce,
      dom: document.querySelector('#nmkr-sync-nonce')?.value,
    }));
    const nonce = canonicalNonce(nonceSources.runtime, nonceSources.dom);
    const ajax = `${env.NMKR_SYNTHETIC_BASE_URL}/wp-admin/admin-ajax.php`;
    const start = await post(context, ajax, { action: 'nmkr_start_sync', nonce });
    if (!/^[0-9a-f-]{36}$/i.test(start?.run_id || '')) fail('run-id-invalid');
    writeFileSync(env.NMKR_SYNTHETIC_RUN_RECEIPT, JSON.stringify({ schema_version: 1, run_id: start.run_id, mode: env.NMKR_SYNTHETIC_RUN_MODE }) + '\n', { mode: 0o600, flag: 'wx' });
    chmodSync(env.NMKR_SYNTHETIC_RUN_RECEIPT, 0o600);
    await refuse(context, ajax, { action: 'nmkr_start_sync', nonce });
    verifyWorker(env, start.run_id);
    const workerLog = openSync(env.NMKR_SYNTHETIC_WORKER_LOG, 'a', 0o600);
    try {
      wp = spawn(env.NMKR_SYNTHETIC_WP_CLI, [`--path=${env.NMKR_SYNTHETIC_WP_ROOT}`, 'cron', 'event', 'run', 'nmkr_execute_sync_background', '--due-now'], { stdio: ['ignore', workerLog, workerLog], env });
    } finally {
      closeSync(workerLog);
    }
    const workerExit = new Promise(resolve => {
      wp.once('exit', code => resolve({ workerExited: true, code }));
      wp.once('error', () => resolve({ workerExited: true, code: null }));
    });
    const deadline = Date.now() + timeoutSeconds * 1000;
    let previous = 0; let terminal = false;
    while (!terminal) {
      const remaining = deadline - Date.now();
      if (remaining <= 0) fail('lifecycle-timeout');
      const event = await Promise.race([sleep(Math.min(3000, remaining)).then(() => ({ workerExited: false })), workerExit]);
      const data = await post(context, ajax, { action: 'nmkr_sync_progress', nonce });
      previous = assertProgress(previous, data);
      const outcome = data?.terminal_outcome;
      terminal = data?.finished === true && data?.in_progress === false && outcome === 'completed' && data?.terminalRunId === start.run_id;
      if (outcome === 'failed' || outcome === 'stopped' || outcome === 'cancelled') fail('terminal-failure');
      if (event.workerExited && !terminal) fail('worker-exited-early');
    }
    const exit = await Promise.race([
      workerExit,
      sleep(Math.max(0, deadline - Date.now())).then(() => fail('lifecycle-timeout')),
    ]);
    if (exit.code !== 0) fail('worker-failed');
    return { runId: start.run_id, starts: 1, completed: true };
  } finally {
    if (wp && wp.exitCode === null) wp.kill('SIGTERM');
    await browser.close();
  }
}
if (import.meta.url === `file://${process.argv[1]}`) {
  import('@playwright/test').then(({ chromium }) => runSyntheticLifecycle({ playwright: { chromium } })).then(() => process.stdout.write('Synthetic driver: PASS\n')).catch(() => { process.stderr.write(`Synthetic driver: FAIL (${process.env.CI ? 'public-safe' : 'private diagnostic retained'})\n`); process.exitCode = 1; });
}
