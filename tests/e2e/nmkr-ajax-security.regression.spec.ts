import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';

type Runtime = { ajaxUrl: string; syncNonce: string; dashboardNonce: string };
type Classified = { json: boolean; success: boolean; status: number; data: unknown; text: string };
const failure = (code: string): never => { throw new Error(code); };
const restrictedContract = Boolean(process.env.NMKR_MARKETING_USER && process.env.NMKR_MARKETING_PASSWORD);
const targeted = process.env.NMKR_AJAX_SECURITY_REQUIRE_CONTRACT === 'true';

test.skip(!restrictedContract && !targeted, 'Private restricted-account contract is not configured.');
if (targeted && !restrictedContract) failure('ajax_security_environment_missing');

const required = (name: string): string => process.env[name] || failure('ajax_security_environment_missing');
const urlFor = (base: string, path: string) => new URL(path, base.endsWith('/') ? base : `${base}/`).toString();

async function blockAutomaticAjax(context: BrowserContext): Promise<string[]> {
  const ledger: string[] = [];
  await context.route('**/admin-ajax.php**', async route => {
    ledger.push('blocked-automatic-ajax');
    await route.abort('blockedbyclient');
  });
  return ledger;
}

async function login(browser: Browser, user: string, password: string): Promise<{ context: BrowserContext; ledger: string[] }> {
  const context = await browser.newContext({ storageState: undefined });
  const ledger = await blockAutomaticAjax(context);
  const page = await context.newPage();
  await page.goto(urlFor(required('WP_BASE_URL'), '/wp-login.php'));
  await page.locator('#user_login').fill(user); await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#wp-submit').click()]);
  if (!page.url().includes('/wp-admin')) { await context.close(); failure('ajax_security_login_failed'); }
  return { context, ledger };
}

async function runtime(page: Page, path = '/wp-admin/admin.php?page=nmkr-connect-projects'): Promise<Runtime> {
  await page.goto(urlFor(required('WP_BASE_URL'), path));
  const value = await page.evaluate(() => {
    const v = (window as unknown as { nmkrSyncProgress?: Record<string,string> }).nmkrSyncProgress || {};
    return { ajaxUrl: v.ajax_url || v.ajaxUrl || '', syncNonce: v.nonce || '', dashboardNonce: v.dashboardNonce || '' };
  });
  if (!value.ajaxUrl || !value.syncNonce || !value.dashboardNonce) failure('ajax_security_runtime_missing');
  return value;
}

async function post(context: BrowserContext, ajaxUrl: string, fields: Record<string,string>, secrets: string[] = []): Promise<Classified> {
  const response = await context.request.post(ajaxUrl, { form: fields, failOnStatusCode: false });
  const text = await response.text(); let parsed: unknown; let json = false;
  try { parsed = JSON.parse(text); json = true; } catch { parsed = undefined; }
  if (secrets.some(secret => secret && text.includes(secret))) failure('ajax_security_secret_echoed');
  const success = Boolean(json && parsed && typeof parsed === 'object' && (parsed as {success?:unknown}).success === true);
  return { json, success, status: response.status(), data: parsed, text };
}
function safe(r: Classified) {
  const markers = /(api[_ -]?key|password|authorization:|cookie:|stack trace|exception|fatal error|\bselect\b.+\bfrom\b|\/(?:home|var\/www|workspace)\/)/i;
  if (markers.test(r.text)) failure('ajax_security_sensitive_marker_detected');
}
function deniedNonceOrAnonymous(r: Classified) {
  safe(r); if (r.success) failure('ajax_security_expected_denial_missing');
  if (![400, 401, 403].includes(r.status) && !(r.status === 200 && (r.json || r.text.trim() === '0'))) failure('ajax_security_response_shape_invalid');
}
function deniedCapability(r: Classified) {
  safe(r); if (r.status !== 403 || r.success) failure('ajax_security_capability_denial_invalid');
}
function deniedInput(r: Classified) {
  safe(r); if (r.success || ![400, 403].includes(r.status)) failure('ajax_security_input_denial_invalid');
}

test('@negative privileged AJAX rejects anonymous, nonce, capability, and malformed Stop requests', async ({ browser, page, context }) => {
  const adminLedger = await blockAutomaticAjax(context);
  const admin = await runtime(page);
  const anonymous = await browser.newContext({ storageState: undefined });
  try {
    for (const action of ['nmkr_start_sync','nmkr_check_api_status','nmkr_analytics_kpis']) deniedNonceOrAnonymous(await post(anonymous, admin.ajaxUrl, { action }));
  } finally { await anonymous.close(); }
  for (const [action, nonce] of [['nmkr_start_sync',''],['nmkr_check_api_status',''],['nmkr_clear_all_logs',''],['nmkr_analytics_kpis',''],['nmkr_start_sync','synthetic-invalid'],['nmkr_check_api_status','synthetic-invalid'],['nmkr_clear_all_logs','synthetic-invalid'],['nmkr_analytics_kpis','synthetic-invalid']]) {
    deniedNonceOrAnonymous(await post(context, admin.ajaxUrl, { action, nonce }, [admin.syncNonce, admin.dashboardNonce]));
  }
  const restrictedLogin = await login(browser, required('NMKR_MARKETING_USER'), required('NMKR_MARKETING_PASSWORD'));
  try {
    const rp = await restrictedLogin.context.newPage(); const rr = await runtime(rp, process.env.NMKR_MARKETING_PATH);
    for (const [action, nonce] of [['nmkr_start_sync',rr.syncNonce],['nmkr_sync_progress',rr.syncNonce],['nmkr_check_api_status',rr.dashboardNonce]]) {
      deniedCapability(await post(restrictedLogin.context, rr.ajaxUrl, { action, nonce }, [rr.syncNonce, rr.dashboardNonce, required('NMKR_MARKETING_PASSWORD')]));
    }
  } finally { await restrictedLogin.context.close(); }
  deniedInput(await post(context, admin.ajaxUrl, { action:'nmkr_stop_sync', nonce:admin.syncNonce, run_id:'not-a-valid-run-id' }, [admin.syncNonce]));
  expect([...adminLedger, ...restrictedLogin.ledger], 'ajax_security_automatic_ajax_ledger').not.toContain('allowed-automatic-ajax');
});

test('@authorized bounded authorized analytics and synchronization-health shapes', async ({ browser, page, context }) => {
  const adminLedger = await blockAutomaticAjax(context);
  const restrictedLogin = await login(browser, required('NMKR_MARKETING_USER'), required('NMKR_MARKETING_PASSWORD'));
  try {
    const rp = await restrictedLogin.context.newPage(); const rr = await runtime(rp, process.env.NMKR_MARKETING_PATH);
    const k = await post(restrictedLogin.context, rr.ajaxUrl, { action:'nmkr_analytics_kpis', nonce:rr.dashboardNonce, range:'24h', bucket:'hour', shortcode_type:'grid', project_uid:'synthetic', token_uid:'synthetic' }, [rr.dashboardNonce, required('NMKR_MARKETING_PASSWORD')]); safe(k);
    if (!k.success || !k.json || !k.data || typeof k.data !== 'object') failure('ajax_security_response_shape_invalid');
    const d = (k.data as {data?:Record<string,unknown>}).data; if (!d || !['number','string'].includes(typeof d.views) || !['number','string'].includes(typeof d.clicks) || !['number','string'].includes(typeof d.ctr)) failure('ajax_security_response_shape_invalid');
  } finally { await restrictedLogin.context.close(); }
  const ar = await runtime(page);
  const h = await post(context, ar.ajaxUrl, { action:'nmkr_check_sync_health', nonce:ar.syncNonce }, [ar.syncNonce, required('WP_ADMIN_PASSWORD')]); safe(h);
  if (!h.success || !h.json || !h.data || typeof h.data !== 'object') failure('ajax_security_response_shape_invalid');
  const hd = (h.data as {data?:Record<string,unknown>}).data;
  if (!hd || typeof hd.in_progress !== 'boolean' || typeof hd.progress !== 'number' || typeof hd.time_elapsed !== 'number' || typeof hd.time_since_update !== 'number' || typeof hd.has_running_jobs !== 'boolean' || typeof hd.is_stalled !== 'boolean' || typeof hd.stall_reason !== 'string' || !hd.profile_settings || typeof hd.profile_settings !== 'object') failure('ajax_security_response_shape_invalid');
  expect([...adminLedger, ...restrictedLogin.ledger], 'ajax_security_automatic_ajax_ledger').not.toContain('allowed-automatic-ajax');
});
