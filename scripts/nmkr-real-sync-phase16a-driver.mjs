#!/usr/bin/env node
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);
const MUTATING_ACTIONS = new Set(['nmkr_start_sync','nmkr_stop_sync','nmkr_force_stop_sync','nmkr_restart_sync_batch','nmkr_cleanup_sync_jobs','nmkr_store_active_metrics','nmkr_store_active_sync_metrics','nmkr_clear_all_logs','nmkr_clear_section_logs','nmkr_clear_sync_log']);
const READ_ONLY_AFTER_START = new Set(['nmkr_sync_progress']);
const FROZEN_BLOCKED = new Set(['heartbeat','nmkr_get_sync_statistics','nmkr_sync_progress','nmkr_check_api_status']);

export function actionFromRequestLike(input) {
  const url = typeof input?.url === 'function' ? input.url() : (input?.url || 'about:blank');
  const method = (typeof input?.method === 'function' ? input.method() : (input?.method || 'GET')).toUpperCase();
  const postData = typeof input?.postData === 'function' ? input.postData() : (input?.postData || '');
  const parsed = new URL(url, 'https://phase16a.invalid');
  const queryAction = parsed.searchParams.get('action');
  if (queryAction) return queryAction;
  if (method !== 'GET' && postData) return new URLSearchParams(postData).get('action') || '';
  return '';
}

export function routeDecisionForAction(action, mode = 'prepare', startAlreadySent = false) {
  if (mode === 'prepare') {
    if (action === 'nmkr_check_api_status') return 'synthetic-ok';
    if (MUTATING_ACTIONS.has(action)) return 'block';
    return 'allow';
  }
  if (mode === 'frozen') return 'block';
  if (mode === 'after-start') {
    if (action === 'nmkr_start_sync') return startAlreadySent ? 'block' : 'allow-once';
    if (READ_ONLY_AFTER_START.has(action)) return 'allow';
    return 'block';
  }
  return FROZEN_BLOCKED.has(action) || MUTATING_ACTIONS.has(action) ? 'block' : 'allow';
}
export const routeDecision = routeDecisionForAction;

export function sanitizeResult(state) {
  return {
    startCount: state.startCount || 0,
    pollCount: state.pollCount || 0,
    maxProgress: state.maxProgress || 0,
    nonterminalObserved: !!state.nonterminalObserved,
    validLiveMetricsObserved: !!state.validLiveMetricsObserved,
    terminalClassification: state.terminalClassification || 'none',
    transportRetryCount: state.transportRetryCount || 0,
    activeHistoryIdObserved: state.activeHistoryIdObserved || 0,
    elapsedSeconds: Math.max(0, Math.round(((state.now ? state.now() : Date.now()) - (state.startedAt || Date.now())) / 1000)),
  };
}

function validMetrics(metrics) {
  return !!metrics && typeof metrics === 'object' && ['total_projects','total_tokens','api_requests','memory_usage'].some((key) => Number.isFinite(Number(metrics[key])) && Number(metrics[key]) >= 0);
}
export function buildStartForm(nonce) { return { action: 'nmkr_start_sync', nonce }; }
export function buildProgressForm(nonce) { return { action: 'nmkr_sync_progress', nonce }; }
function classifyStart(response) {
  if (!response || typeof response !== 'object') return 'fatal';
  if (response.transportError || response.timeout) return 'ambiguous';
  if (!Number.isInteger(response.status) || response.status < 200 || response.status >= 300) return 'fatal';
  if (response.malformed || !response.json || typeof response.json !== 'object') return 'fatal';
  if (response.json.success === false) return 'fatal';
  return 'ok';
}
function classifyPoll(response) {
  if (!response || typeof response !== 'object') return { kind: 'retry' };
  if (response.transportError || response.timeout || response.offline || (response.status >= 500 && response.status < 600)) return { kind: 'retry' };
  if (response.status >= 400 && response.status < 500) return { kind: 'fatal' };
  if (response.malformed || !response.json || typeof response.json !== 'object') return { kind: 'fatal' };
  const data = response.json.data && typeof response.json.data === 'object' ? response.json.data : response.json;
  const progress = Number(data.progress ?? data.percentage ?? 0);
  if (!Number.isFinite(progress) || progress < 0 || progress > 100) return { kind: 'fatal' };
  const terminal = data.status === 'completed' || data.completed === true || data.finished === true;
  const explicitNotDone = data.in_progress === false && !terminal;
  const activeHistoryId = Number(data.history_id ?? data.sync_id ?? 0);
  return { kind: 'ok', progress, terminal, explicitNotDone, activeHistoryId, metrics: data.live_metrics ?? data.metrics };
}

export async function runExactlyOnceSync({ transport, nonce, adminAjaxUrl = 'about:blank', receipt, now = () => Date.now(), sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms)) }) {
  if (!transport || typeof transport.start !== 'function' || typeof transport.poll !== 'function') throw new Error('transport-required');
  if (!nonce) throw new Error('nonce-required');
  const maxMs = Math.max(1, Number(receipt?.maxDurationSeconds || receipt?.max_duration_seconds || 30)) * 1000;
  const pollTimeoutMs = Math.max(1, Number(receipt?.pollTimeoutSeconds || receipt?.poll_timeout_seconds || 5)) * 1000;
  const state = { startCount: 0, pollCount: 0, maxProgress: 0, nonterminalObserved: false, validLiveMetricsObserved: false, terminalClassification: 'none', transportRetryCount: 0, activeHistoryIdObserved: 0, startedAt: now(), now };
  let interval = 3000;
  state.startCount += 1;
  const startResponse = await transport.start({ url: adminAjaxUrl, nonce, timeoutMs: pollTimeoutMs });
  const startClass = classifyStart(startResponse);
  if (startClass === 'ambiguous') { state.terminalClassification = 'ambiguous-start'; return { ok: false, ambiguous: true, sanitized: sanitizeResult(state) }; }
  if (startClass !== 'ok') { state.terminalClassification = 'start-fatal'; return { ok: false, fatal: true, sanitized: sanitizeResult(state) }; }
  while (now() - state.startedAt < maxMs) {
    state.pollCount += 1;
    const pollResponse = await transport.poll({ timeoutMs: Math.min(pollTimeoutMs, Math.max(1, maxMs - (now() - state.startedAt))), nonce });
    const poll = classifyPoll(pollResponse);
    if (poll.kind === 'fatal') { state.terminalClassification = 'poll-fatal'; return { ok: false, fatal: true, sanitized: sanitizeResult(state) }; }
    if (poll.kind === 'retry') { state.transportRetryCount += 1; await sleep(interval); interval = Math.min(30000, interval * 2); continue; }
    if (poll.progress < state.maxProgress) { state.terminalClassification = 'progress-decrease'; return { ok: false, fatal: true, sanitized: sanitizeResult(state) }; }
    state.maxProgress = poll.progress;
    if (poll.activeHistoryId > 0) state.activeHistoryIdObserved = poll.activeHistoryId;
    if (!poll.terminal) {
      state.nonterminalObserved = true;
      if (validMetrics(poll.metrics)) state.validLiveMetricsObserved = true;
      if (poll.explicitNotDone) { await sleep(3000); continue; }
    }
    if (poll.terminal) { state.terminalClassification = 'terminal-observed'; return { ok: true, sanitized: sanitizeResult(state) }; }
    await sleep(3000);
  }
  state.terminalClassification = 'timeout';
  return { ok: false, timeout: true, sanitized: sanitizeResult(state) };
}

async function authorizeWithController() {
  const script = process.env.NMKR_PHASE16A_FINAL_AUTH_SCRIPT;
  const runDir = process.env.NMKR_PHASE16A_FINAL_AUTH_RUN_DIR;
  if (!script || !runDir) throw new Error('final-authorization-callback-required');
  const { stdout } = await execFileAsync('bash', [script, '--final-authorize', runDir], { timeout: 120000, maxBuffer: 1024 * 64 });
  const parsed = JSON.parse(stdout.trim().split(/\n/).pop() || '{}');
  if (parsed.ok !== true) throw new Error('final-authorization-denied');
}

async function extractNonce(page) {
  return page.evaluate(() => {
    const candidates = [
      window.nmkrSync?.nonce,
      window.nmkrSync?.sync_nonce,
      window.nmkr_ajax?.nonce,
      window.nmkrDashboard?.syncNonce,
      document.querySelector('#nmkr-sync-nonce')?.value,
      document.querySelector('input[name="nmkr_sync_nonce"]')?.value,
    ].filter(Boolean);
    return String(candidates[0] || '');
  });
}

async function login(page, baseUrl) {
  const user = process.env.WP_ADMIN_USER;
  const pass = process.env.WP_ADMIN_PASSWORD;
  if (!user || !pass) throw new Error('wp-credentials-required');
  await page.goto(new URL('/wp-login.php', baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => null), page.click('#wp-submit')]);
  const confirm = page.locator('text=/administration email/i');
  if (await confirm.count().catch(() => 0)) {
    const remind = page.locator('a:has-text("Remind me later"), input[value*="Remind me later"], button:has-text("Remind me later")').first();
    if (await remind.count().catch(() => 0)) await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }).catch(() => null), remind.click()]);
  }
}

async function realCli() {
  if (process.env.RUN_REAL_SYNC !== 'true') throw new Error('RUN_REAL_SYNC-required');
  const baseUrl = process.env.WP_BASE_URL;
  const dashboardPath = process.env.NMKR_DASHBOARD_PATH || '/wp-admin/admin.php?page=nmkr-connect-dashboard';
  if (!baseUrl || !baseUrl.startsWith('https://')) throw new Error('https-base-url-required');
  const { chromium } = await import('@playwright/test');
  const browser = await chromium.launch({ headless: true });
  let routingMode = 'prepare';
  let startAlreadySent = false;
  try {
    const context = await browser.newContext({ recordVideo: undefined, storageState: undefined, acceptDownloads: false });
    const page = await context.newPage();
    await page.route('**/*', async (route, request) => {
      const url = request.url();
      const isAjax = url.includes('/admin-ajax.php');
      if (routingMode === 'frozen' && (isAjax || url.includes('/wp-admin/') || url.endsWith('.php'))) return route.fulfill({ status: 403, body: '{}' });
      if (!isAjax) return route.continue();
      const action = actionFromRequestLike(request);
      const decision = routeDecisionForAction(action, routingMode, startAlreadySent);
      if (decision === 'synthetic-ok') return route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"connected":true}}' });
      if (decision === 'block') return route.fulfill({ status: 403, contentType: 'application/json', body: '{"success":false}' });
      return route.continue();
    });
    await login(page, baseUrl);
    await page.goto(new URL(dashboardPath, baseUrl).toString(), { waitUntil: 'domcontentloaded' });
    const nonce = await extractNonce(page);
    if (!nonce) throw new Error('sync-nonce-missing');
    routingMode = 'frozen';
    await authorizeWithController();
    routingMode = 'after-start';
    const adminAjaxUrl = new URL('/wp-admin/admin-ajax.php', baseUrl).toString();
    const result = await runExactlyOnceSync({
      adminAjaxUrl,
      nonce,
      receipt: { maxDurationSeconds: Number(process.env.NMKR_REAL_SYNC_MAX_DURATION_SECONDS || 1800), pollTimeoutSeconds: Number(process.env.NMKR_REAL_SYNC_POLL_TIMEOUT_SECONDS || 45) },
      transport: {
        start: async ({ url, nonce, timeoutMs }) => {
          if (startAlreadySent) return { status: 409, json: { success: false } };
          startAlreadySent = true;
          try {
            const res = await context.request.post(url, { form: buildStartForm(nonce), timeout: timeoutMs });
            let json; try { json = await res.json(); } catch { return { status: res.status(), malformed: true }; }
            return { status: res.status(), json };
          } catch (error) { return { timeout: /Timeout|Abort/i.test(String(error?.message || error)), transportError: true }; }
        },
        poll: async ({ timeoutMs, nonce }) => {
          try {
            const res = await context.request.post(adminAjaxUrl, { form: buildProgressForm(nonce), timeout: timeoutMs });
            let json; try { json = await res.json(); } catch { return { status: res.status(), malformed: true }; }
            return { status: res.status(), json };
          } catch (error) { return { timeout: /Timeout|Abort/i.test(String(error?.message || error)), transportError: true }; }
        },
      },
    });
    console.log(JSON.stringify(result.sanitized));
    if (!result.ok) process.exit(result.ambiguous ? 3 : 2);
  } finally {
    await browser.close();
  }
}

if (import.meta.url === `file://${process.argv[1]}`) {
  realCli().catch((error) => { console.error(`Phase16A driver FAIL: ${String(error?.message || error).replace(/[\r\n].*/s, '')}`); process.exit(1); });
}
