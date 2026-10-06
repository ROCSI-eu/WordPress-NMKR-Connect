import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

function css(relativePath: string): string {
  return fs.readFileSync(path.resolve(relativePath), 'utf8');
}

test('public shortcode system stays contained, responsive, and keyboard operable', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });

  const styles = [
    'css/nmkr-shortcode-foundation.css',
    'css/nmkr-shortcode-grid.css',
    'css/nmkr-shortcode-list.css',
    'css/nmkr-shortcode-carousel.css',
    'css/nmkr-shortcode-token.css',
    'css/nmkr-shortcode-project.css',
    'css/nmkr-buy-button.css',
    'css/nmkr-lightbox.css',
  ].map(css).join('\n');

  const card = (scope: string, index: number) => `
    <article class="${scope === 'carousel' ? 'nmkr-token' : 'nmkr-token-card'}"
      data-nmkr-evt="view"
      data-nmkr-shortcode="${scope}"
      data-nmkr-project-uid="project-1"
      data-nmkr-token-uid="token-${index}"
      data-nmkr-id="${scope}:token-${index}">
      <div class="nmkr-token-media">
        <img
          src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='180'%3E%3Crect width='240' height='180' fill='%23e5e7eb'/%3E%3C/svg%3E"
          alt="Token ${index}"
          class="${scope === 'carousel' ? 'token-image' : 'nmkr-token-image'}"
          role="button"
          tabindex="0"
          data-nmkr-lightbox-trigger="1">
      </div>
      <div class="${scope === 'carousel' ? 'nmkr-carousel-card-body' : 'nmkr-token-card-body'}">
        <h3 class="nmkr-token-title">Token ${index}</h3>
        <span class="nmkr-token-status available"><span class="screen-reader-text">Status: </span>Available</span>
        <div class="nmkr-token-price"><span class="nmkr-price-badge nmkr-price-ada">12 ADA</span></div>
        <a href="https://example.test/buy/${index}" class="nmkr-buy-button"
          data-nmkr-evt="click" data-nmkr-cta="buy" data-nmkr-shortcode="${scope}"
          data-nmkr-project-uid="project-1" data-nmkr-token-uid="token-${index}"
          data-nmkr-id="${scope}:token-${index}">Buy with NMKR Pay</a>
      </div>
    </article>`;

  await page.setContent(`
    <style>.host-sentinel{color:rgb(1, 2, 3);padding:3px;display:block}
${styles}</style>
    <main>
      <button class="host-sentinel" id="host-sentinel">Host theme control</button>

      <section class="nmkr-shortcode nmkr-shortcode-grid" id="grid">
        <form class="nmkr-project-selector"><label class="nmkr-project-selector-label">Project</label><select><option>Example</option></select></form>
        <div class="nmkr-search-filter"><form><label class="nmkr-search-field"><span>Search tokens</span><input class="nmkr-search-input"></label><label class="nmkr-filter-field"><span>Status</span><select class="nmkr-filter-select"><option>All</option></select></label><button class="nmkr-filter-submit">Apply filters</button></form></div>
        <section class="nmkr-project-details"><div class="nmkr-project-header"><div class="nmkr-project-heading"><h2>Example project</h2><p class="nmkr-project-description">Project description</p></div></div><div class="nmkr-project-counters">${['Total','Minted','Sold','Reserved','Available'].map((label,i)=>`<div class="nmkr-counter-item"><strong>${label}</strong>${i}</div>`).join('')}</div></section>
        <div class="nmkr-token-grid">${[1,2,3].map(i=>card('grid',i)).join('')}</div>
      </section>

      <section class="nmkr-shortcode nmkr-shortcode-list" id="list">
        <div class="nmkr-token-list-region" role="region" tabindex="0" aria-label="Token list. Scroll horizontally on narrow screens.">
          <table class="nmkr-token-list"><thead><tr><th>Image</th><th>Name</th><th>Status</th><th>Price</th><th>Series</th><th>Asset</th><th>Actions</th></tr></thead>
          <tbody><tr data-nmkr-evt="view" data-nmkr-shortcode="list" data-nmkr-project-uid="project-1" data-nmkr-token-uid="token-list" data-nmkr-id="list:token-list"><td>Image</td><td class="nmkr-list-token-name">List token</td><td><span class="nmkr-token-status available">Available</span></td><td>12 ADA</td><td>Series</td><td>Asset</td><td><a class="nmkr-buy-button">Buy</a></td></tr></tbody></table>
        </div>
      </section>

      <section class="nmkr-shortcode nmkr-shortcode-carousel" id="carousel">
        <div class="nmkr-carousel-heading"><h3>Project tokens</h3><p>Browse tokens</p></div>
        <div class="nmkr-carousel-wrapper">
          <button type="button" class="nmkr-carousel-control carousel-prev" data-nmkr-carousel-direction="prev" aria-controls="carousel-strip" aria-label="Previous tokens">‹</button>
          <div id="carousel-strip" class="nmkr-carousel-container" tabindex="0" role="region" aria-label="Scrollable token carousel">
            ${[1,2,3,4,5,6].map(i=>card('carousel',i)).join('')}
          </div>
          <button type="button" class="nmkr-carousel-control carousel-next" data-nmkr-carousel-direction="next" aria-controls="carousel-strip" aria-label="Next tokens">›</button>
        </div>
      </section>

      <section class="nmkr-shortcode nmkr-shortcode-token" id="token">
        <article class="nmkr-single-token" data-nmkr-evt="view" data-nmkr-shortcode="token" data-nmkr-token-uid="single-token" data-nmkr-id="token:single-token">
          <div class="nmkr-single-token-media"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='180'%3E%3C/svg%3E" alt="Single token" class="nmkr-token-image" role="button" tabindex="0" data-nmkr-lightbox-trigger="1"></div>
          <div class="nmkr-single-token-content"><h2>Single token</h2><span class="nmkr-token-status sold">Sold</span><p class="nmkr-token-availability">This token is not currently available for purchase.</p></div>
        </article>
      </section>

      <section class="nmkr-shortcode nmkr-shortcode-project" id="project">
        <article class="nmkr-single-project" data-nmkr-evt="view" data-nmkr-shortcode="project" data-nmkr-project-uid="project-1" data-nmkr-id="project:project-1">
          <div class="nmkr-project-profile"><div class="nmkr-project-heading"><h2>Project profile</h2><p class="nmkr-project-description">Description</p></div></div>
          <div class="nmkr-project-meta">${['Total','Minted','Sold','Reserved','Available'].map((label,i)=>`<div class="nmkr-project-meta-item"><strong>${label}</strong>${i}</div>`).join('')}</div>
          <section class="nmkr-featured-token"><div class="nmkr-featured-token-media"></div><div class="nmkr-featured-token-content"><h3>Featured token</h3><h4>Token name</h4><span class="nmkr-token-status reserved">Reserved</span><p class="nmkr-token-availability">The featured token is not currently available for purchase.</p></div></section>
        </article>
      </section>

      <div class="nmkr-shortcode-state nmkr-shortcode-state-empty" id="empty-state" role="status"><p>No tokens match the current filters.</p></div>

      <div id="nmkr-lightbox" class="nmkr-lightbox" role="dialog" aria-modal="true" aria-label="Image preview" aria-hidden="true">
        <button type="button" class="nmkr-close" aria-label="Close image preview">×</button>
        <img src="" alt="">
      </div>
    </main>
  `);

  await page.addScriptTag({ path: path.resolve('js/nmkr-carousel.js') });
  await page.addScriptTag({ path: path.resolve('js/nmkr-lightbox.js') });

  for (const scope of ['grid', 'list', 'carousel', 'token', 'project']) {
    await expect(page.locator(`[data-nmkr-shortcode="${scope}"][data-nmkr-evt="view"]`).first()).toBeAttached();
  }
  await expect(page.locator('#empty-state')).toContainText('No tokens match');

  const sentinel = page.locator('#host-sentinel');
  expect(await sentinel.evaluate(el => getComputedStyle(el).color)).toBe('rgb(1, 2, 3)');
  expect(await sentinel.evaluate(el => getComputedStyle(el).paddingTop)).toBe('3px');

  await page.setViewportSize({ width: 900, height: 900 });
  const strip = page.locator('#carousel-strip');
  await strip.evaluate(el => { el.scrollLeft = 0; });
  await page.locator('.carousel-next').focus();
  await page.keyboard.press('Enter');
  await expect.poll(() => strip.evaluate(el => el.scrollLeft)).toBeGreaterThan(0);

  const imageTrigger = page.locator('#token [data-nmkr-lightbox-trigger="1"]');
  await imageTrigger.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('#nmkr-lightbox')).toHaveClass(/is-open/);
  await expect(page.locator('#nmkr-lightbox')).toHaveAttribute('aria-hidden', 'false');
  const lightboxClose = page.locator('#nmkr-lightbox .nmkr-close');
  await expect(lightboxClose).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(lightboxClose).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(lightboxClose).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('#nmkr-lightbox')).not.toHaveClass(/is-open/);
  await expect(imageTrigger).toBeFocused();

  await strip.evaluate(el => { el.scrollLeft = 0; });

  for (const width of [1180, 782, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    const pageFits = await page.evaluate(
      () => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1
    );
    expect(pageFits, 'plugin output should not cause document-level overflow at ' + width + 'px').toBe(true);

    for (const id of ['grid', 'list', 'carousel', 'token', 'project']) {
      const rectFits = await page.locator('#' + id).evaluate(el => {
        const rect = el.getBoundingClientRect();
        return rect.left >= -1 && rect.right <= document.documentElement.clientWidth + 1;
      });
      expect(rectFits, id + ' root should remain inside the viewport at ' + width + 'px').toBe(true);
    }
  }

  await page.setViewportSize({ width: 320, height: 900 });
  const listOverflow = await page.locator('.nmkr-token-list-region').evaluate(
    el => el.scrollWidth > el.clientWidth
  );
  expect(listOverflow).toBe(true);
  const carouselOverflow = await page.locator('#carousel-strip').evaluate(
    el => el.scrollWidth > el.clientWidth
  );
  expect(carouselOverflow).toBe(true);

  await expect(page.locator('.nmkr-carousel-control')).toHaveCount(2);
  await expect(page.locator('#carousel-strip')).toHaveAttribute('tabindex', '0');
});
