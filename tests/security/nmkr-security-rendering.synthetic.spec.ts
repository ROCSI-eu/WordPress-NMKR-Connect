import { test, expect } from '@playwright/test';
import path from 'path';

const hostile = `<img src=x onerror="window.__nmkrMarker()"><svg onload="window.__nmkrMarker()"><a href=x>marker</a><iframe srcdoc=x></iframe>`;

test('production synchronization and analytics sinks render hostile-shaped values as text', async ({ page }) => {
  await page.setContent(`<main>
    <button id="nmkr-sync-button"></button><button id="nmkr-stop-sync-button"></button>
    <input id="nmkr-sync-nonce" value="nonce"><div id="nmkr-sync-progress-bar"></div>
    <div id="nmkr-sync-progress-container"></div><div id="status-message"></div><div id="nmkr-sync-error"></div>
    <div id="last-synced"></div><div id="total-sync-time"></div><div id="total-api-time"></div><div id="request-count"></div><div id="avg-response-time"></div><div id="memory-usage"></div><div id="total-projects"></div><div id="total-tokens"></div><div id="active-sync-metrics"></div><div class="sync-data panel"></div>
    <section class="nmkr-analytics-wrap"><div id="nmkr-analytics-filters"></div><div id="nmkr-analytics-kpis"></div><div id="nmkr-analytics-charts"></div><div id="nmkr-analytics-tables"></div><div id="nmkr-analytics-exports"></div></section>
  </main>`);
  await page.evaluate((marker) => {
    window.__nmkrMarkerCount = 0;
    window.__nmkrMarker = () => { window.__nmkrMarkerCount++; };
    window.nmkrSyncProgress = { ajax_url: '/local', nonce: 'nonce', options: { sync_initial_interval: 60000, sync_max_interval: 60000 } };
    window.nmkrAnalyticsDashboard = { ajax_url: '/local', nonce: 'nonce', defaults: { perPage: 10 } };
    window.fetch = async (_url, init) => {
      const body = String(init?.body || '');
      const action = new URLSearchParams(body).get('action');
      const data = action === 'nmkr_analytics_top_projects' ? { rows:[{ project_uid:marker, views:1, clicks:0, ctr:0 }], total:1 }
        : action === 'nmkr_analytics_top_tokens' ? { rows:[{ token_uid:marker, views:1, clicks:0, ctr:0 }], total:1 }
        : action === 'nmkr_analytics_timeseries' ? { series:[] } : { views:0, clicks:0, ctr:0 };
      return { ok:true, json:async()=>({ success:true, data }) };
    };
    class JQ {
      nodes: Element[]; constructor(nodes: Element[]) { this.nodes=nodes; }
      ready(fn:()=>void){ fn(); return this; } val(){ return (this.nodes[0] as HTMLInputElement)?.value || ''; }
      off(){ return this; } on(event:string, fn:EventListener){ this.nodes.forEach(n=>n.addEventListener(event,fn)); return this; }
      prop(name:string,v:unknown){ this.nodes.forEach(n=>(n as any)[name]=v); return this; } hide(){ return this.css('display','none'); } show(){ return this.css('display',''); }
      css(name:string,v:string){ this.nodes.forEach(n=>(n as HTMLElement).style.setProperty(name,v)); return this; }
      text(v?:unknown){ if(arguments.length){this.nodes.forEach(n=>n.textContent=String(v));return this;} return this.nodes[0]?.textContent||''; }
      empty(){this.nodes.forEach(n=>n.replaceChildren());return this;} append(child:any){this.nodes.forEach(n=>n.append(...child.nodes));return this;}
      find(sel:string){return new JQ(this.nodes.flatMap(n=>Array.from(n.querySelectorAll(sel))));} each(fn:Function){this.nodes.forEach((n,i)=>fn.call(n,i,n));return this;}
      addClass(c:string){this.nodes.forEach(n=>n.classList.add(c));return this;} removeClass(c:string){this.nodes.forEach(n=>n.classList.remove(c));return this;}
    }
    const jq:any = (sel:any, attrs?:any) => {
      if(sel===document) return new JQ([document.documentElement]);
      if(typeof sel==='string' && sel.startsWith('<')) { const n=document.createElement(sel.match(/<([a-z]+)/i)![1]); Object.entries(attrs||{}).forEach(([k,v])=>k==='class'?n.className=String(v):n.setAttribute(k,String(v))); return new JQ([n]); }
      if(sel instanceof Element) return new JQ([sel]); return new JQ(Array.from(document.querySelectorAll(sel)));
    };
    const progress={success:true,data:{progress:1,current_item:marker,in_progress:true,activeRunId:'12345678-1234-4123-8123-123456789abc'}};
    jq.ajax=()=>{ const chain:any={readyState:4,done(fn:Function){queueMicrotask(()=>fn(progress));return chain;},fail(){return chain;},always(){return chain;},abort(){}}; return chain;};
    jq.post=jq.ajax; window.jQuery=jq; (window as any).$=jq;
  }, hostile);
  await page.addScriptTag({ path: path.resolve('js/nmkr-sync-progress.js') });
  await page.click('#nmkr-sync-button');
  await page.addScriptTag({ path: path.resolve('js/admin/nmkr-analytics-dashboard.js') });
  await page.waitForFunction(() => document.querySelectorAll('#nmkr-top-projects-root tbody tr').length > 0);
  for (const selector of ['#status-message .status-header','#nmkr-top-projects-root tbody td:first-child','#nmkr-top-tokens-root tbody td:first-child']) {
    await expect(page.locator(selector)).toHaveText(hostile);
  }
  await expect(page.locator('main script, main img, main svg, main a, main iframe')).toHaveCount(0);
  expect(await page.evaluate(() => window.__nmkrMarkerCount)).toBe(0);
  await expect(page.locator('#status-message .status-header')).toHaveCount(1);
  await expect(page.locator('#nmkr-top-projects-root table, #nmkr-top-tokens-root table')).toHaveCount(2);
});
