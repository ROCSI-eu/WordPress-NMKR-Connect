import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';

type Runtime = { ajaxUrl: string; syncNonce: string; dashboardNonce: string };
type Classified = { ok: boolean; json: boolean; success: boolean; status: number; data: unknown; text: string };
const failure = (code: string): never => { throw new Error(code); };
const required = (name: string): string => process.env[name] || failure('ajax_security_environment_missing');
const urlFor = (base: string, path: string) => new URL(path, base.endsWith('/') ? base : `${base}/`).toString();

async function login(browser: Browser, user: string, password: string): Promise<BrowserContext> {
  const context = await browser.newContext({ storageState: undefined });
  const page = await context.newPage();
  await page.goto(urlFor(required('WP_BASE_URL'), '/wp-login.php'));
  await page.locator('#user_login').fill(user); await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#wp-submit').click()]);
  if (!page.url().includes('/wp-admin')) { await context.close(); failure('ajax_security_login_failed'); }
  return context;
}

async function runtime(page: Page, path: string): Promise<Runtime> {
  await page.goto(urlFor(required('WP_BASE_URL'), path));
  const value = await page.evaluate(() => {
    const v = (window as unknown as { nmkrSyncProgress?: Record<string,string> }).nmkrSyncProgress || {};
    return { ajaxUrl: v.ajax_url || v.ajaxUrl || '', syncNonce: v.nonce || '', dashboardNonce: v.dashboardNonce || '' };
  });
  if (!value.ajaxUrl || !value.syncNonce || !value.dashboardNonce) failure('ajax_security_runtime_missing');
  return value;
}

async function post(context: BrowserContext, ajaxUrl: string, fields: Record<string,string>): Promise<Classified> {
  const response = await context.request.post(ajaxUrl, { form: fields, failOnStatusCode: false });
  const text = await response.text(); let parsed: unknown; let json = false;
  try { parsed=JSON.parse(text); json=true; } catch { parsed=undefined; }
  const success = Boolean(json && parsed && typeof parsed==='object' && (parsed as {success?:unknown}).success===true);
  return { ok: response.ok(), json, success, status: response.status(), data: parsed, text };
}
function safe(r: Classified) {
  const markers = /(api[_ -]?key|password|authorization:|cookie:|stack trace|exception|fatal error|\bselect\b.+\bfrom\b|\/(?:home|var\/www|workspace)\/)/i;
  if (markers.test(r.text)) failure('ajax_security_sensitive_marker_detected');
}
function denied(r: Classified) {
  safe(r); if (r.success) failure('ajax_security_expected_denial_missing');
  if (!(r.json || r.text.trim()==='0' || r.status===400 || r.status===403)) failure('ajax_security_response_shape_invalid');
}

test('@negative privileged AJAX rejects anonymous, nonce, capability, and malformed Stop requests', async ({ browser, page, context }) => {
  const admin = await runtime(page, process.env.NMKR_DASHBOARD_PATH || '/wp-admin/admin.php?page=nmkr-connect-dashboard');
  const anonymous = await browser.newContext({ storageState: undefined });
  try {
    for (const action of ['nmkr_start_sync','nmkr_check_api_status','nmkr_analytics_kpis']) denied(await post(anonymous, admin.ajaxUrl, { action }));
  } finally { await anonymous.close(); }
  for (const [action, nonce] of [['nmkr_start_sync',''],['nmkr_check_api_status',''],['nmkr_clear_all_logs',''],['nmkr_analytics_kpis',''],['nmkr_start_sync','synthetic-invalid'],['nmkr_check_api_status','synthetic-invalid'],['nmkr_clear_all_logs','synthetic-invalid'],['nmkr_analytics_kpis','synthetic-invalid']]) denied(await post(context, admin.ajaxUrl, { action, nonce }));
  const restricted = await login(browser, required('NMKR_MARKETING_USER'), required('NMKR_MARKETING_PASSWORD'));
  try {
    const rp=await restricted.newPage(); const rr=await runtime(rp, process.env.NMKR_MARKETING_PATH || '/wp-admin/admin.php?page=nmkr-connect-projects');
    for (const [action,nonce] of [['nmkr_start_sync',rr.syncNonce],['nmkr_sync_progress',rr.syncNonce],['nmkr_check_api_status',rr.dashboardNonce]]) denied(await post(restricted,rr.ajaxUrl,{action,nonce}));
  } finally { await restricted.close(); }
  denied(await post(context,admin.ajaxUrl,{action:'nmkr_stop_sync',nonce:admin.syncNonce,run_id:'00000000-0000-4000-8000-000000000099'}));
});

test('@authorized bounded authorized analytics and synchronization-health shapes', async ({ browser, page, context }) => {
  const restricted=await login(browser,required('NMKR_MARKETING_USER'),required('NMKR_MARKETING_PASSWORD'));
  try {
    const rp=await restricted.newPage(); const rr=await runtime(rp,process.env.NMKR_MARKETING_PATH || '/wp-admin/admin.php?page=nmkr-connect-projects');
    const k=await post(restricted,rr.ajaxUrl,{action:'nmkr_analytics_kpis',nonce:rr.dashboardNonce,range:'24h',bucket:'hour',shortcode_type:'grid',project_uid:'synthetic',token_uid:'synthetic'}); safe(k);
    if (!k.success || !k.json || !k.data || typeof k.data!=='object') failure('ajax_security_response_shape_invalid');
    const d=(k.data as {data?:Record<string,unknown>}).data; if (!d || !['number','string'].includes(typeof d.views) || !['number','string'].includes(typeof d.clicks) || !['number','string'].includes(typeof d.ctr)) failure('ajax_security_response_shape_invalid');
  } finally { await restricted.close(); }
  const ar=await runtime(page,process.env.NMKR_DASHBOARD_PATH || '/wp-admin/admin.php?page=nmkr-connect-dashboard');
  const h=await post(context,ar.ajaxUrl,{action:'nmkr_check_sync_health',nonce:ar.syncNonce}); safe(h);
  if (!h.success || !h.json || !h.data || typeof h.data!=='object') failure('ajax_security_response_shape_invalid');
  const hd=(h.data as {data?:unknown}).data; if (!hd || typeof hd!=='object') failure('ajax_security_response_shape_invalid');
  expect(Object.keys(hd as object).length,'ajax_security_response_shape_invalid').toBeGreaterThan(0);
});
