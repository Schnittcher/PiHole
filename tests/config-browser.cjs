// Standalone browser regression checks: node tests/config-browser.cjs (Playwright + Chromium)
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const field = (name, value, type, extra = {}) => ({ path: name, value, type, description: 'Description <img src=x onerror="window.injected=true">', default: value, allowed: null, readOnly: false, reason: '', restart: false, sessionReset: false, ...extra });
const fields = [
  field('dns.queryLogging', true, 'boolean'),
  field('dns.upstreams', ['1.1.1.1'], 'string array', { restart: true }),
  field('dns.port', 53, 'unsigned integer (16 bit)'),
  field('dns.listeningMode', 'LOCAL', 'enum (string)', { allowed: [{ item: 'LOCAL', description: 'Local' }, { item: 'ALL', description: 'All' }] }),
  field('dns.interface', 'eth0', 'string', { readOnly: true, reason: 'Environment variable' }),
  field('webserver.api.password', '********', 'password', { readOnly: true, reason: 'Edit in Pi-hole' }),
  field('misc.delay_startup', 0, 'unsigned integer')
];

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage({ viewport: { width: 850, height: 700 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const root = path.join(__dirname, '..');
    const markup = '<style>' + fs.readFileSync(path.join(root, 'libs/tile.css'), 'utf8') + '</style><script>window.requests=[];window.translationCalls={};window.translate=text=>{translationCalls[text]=(translationCalls[text]||0)+1;throw new Error("Synchronous SDK translation must not be called");};window.requestAction=(action,json)=>requests.push({action,...JSON.parse(json)});</script>' + fs.readFileSync(path.join(root, 'PiHoleConfig/module.html'), 'utf8');
    await page.setContent(markup);
    const choose = (target, name) => target.locator('.config-section[data-section="' + name + '"]').click();
    const request = () => page.evaluate(() => requests.at(-1));
    const reply = (data) => page.evaluate(data => handleMessage(JSON.stringify({ requestID: requests.at(-1).requestID, action: requests.at(-1).action, ...data })), data);
    const input = name => page.locator('.config-field').filter({ has: page.locator('label', { hasText: name }) }).locator('input,textarea,select');
    await page.evaluate(() => handleMessage('{"init":true}'));
    assert.equal((await request()).action, 'LoadConfiguration');
    await reply({ ok: true, fields });
    assert.equal(await page.locator('.config-section[aria-pressed=true]').getAttribute('data-section'), 'dns');
    assert.equal(await page.locator('.config-field').count(), 5);
    await choose(page, '');
    assert.equal(await page.locator('.config-field').count(), 7);
    assert.equal(await input('dns.interface').isDisabled(), true);
    assert.equal(await input('webserver.api.password').isDisabled(), true);
    assert.equal(await page.locator('.config-fields img').count(), 0);
    assert.equal(await page.evaluate(() => window.injected), undefined);
    console.log('PASS: configuration loaded, locked fields disabled, descriptions escaped');

    await page.locator('#configSearch').fill('upstreams');
    assert.equal(await page.locator('.config-field:visible').count(), 1);
    await page.locator('#configSearch').fill('');
    await input('dns.queryLogging').uncheck();
    await input('dns.upstreams').fill('9.9.9.9\n8.8.8.8');
    await input('dns.listeningMode').selectOption('1');
    await input('dns.port').fill('');
    assert.equal(await page.locator('#configSave').isDisabled(), true);
    await input('dns.port').fill('54');
    await page.locator('#configSave').click();
    assert.match(await page.locator('#configConfirmText').innerText(), /FTL may restart/);
    assert.equal(await page.evaluate(() => requests.length), 1, 'Review must not write');
    await page.locator('#configAccept').click();
    const saved = await request();
    assert.equal(saved.action, 'SaveConfiguration');
    assert.equal(saved.restart, true);
    assert.deepEqual(saved.changes.map(change => [change.path, change.value]), [
      ['dns.queryLogging', false], ['dns.upstreams', ['9.9.9.9', '8.8.8.8']], ['dns.listeningMode', 'ALL'], ['dns.port', 54]
    ]);
    assert.equal(await input('dns.port').isDisabled(), true);
    console.log('PASS: typed inputs, search, invalid number, explicit review and changed values only');

    await page.evaluate(() => handleMessage('{"requestID":"another-browser","ok":true,"fields":[]}'));
    assert.equal(await input('dns.port').isDisabled(), true);
    await reply({ ok: false, error: 'App session cannot modify configuration' });
    assert.equal(await input('dns.port').inputValue(), '54');
    assert.equal(await page.locator('#configSave').isDisabled(), false);
    assert.match(await page.locator('#configStatus').innerText(), /App session/);
    await page.locator('#configSave').click();
    await page.locator('#configAccept').click();
    await reply({ ok: true });
    assert.equal(await page.locator('#configSave').isDisabled(), true);
    assert.equal(await input('dns.port').inputValue(), '54');
    console.log('PASS: independent browser responses ignored, failed save retains edits, successful save clears changes');

    await input('dns.port').fill('55');
    await page.locator('#configLoad').click();
    const before = await page.evaluate(() => requests.length);
    await page.locator('#configCancel').click();
    assert.equal(await input('dns.port').inputValue(), '55');
    assert.equal(await page.evaluate(() => requests.length), before);
    await page.locator('#configLoad').click();
    await page.locator('#configAccept').click();
    await reply({ ok: true, fields });
    assert.equal(await input('dns.port').inputValue(), '53');
    console.log('PASS: reload requires confirmation before discarding edits');

    await page.clock.install();
    await page.locator('#configLoad').click();
    await page.clock.fastForward(120001);
    assert.equal(await page.locator('#configLoad').isDisabled(), false);
    await input('dns.port').fill('56');
    assert.equal(await page.locator('#configSave').isDisabled(), true);
    assert.match(await page.locator('#configStatus').innerText(), /Reload the configuration/);
    // Test the compact layout without publishing a preview or exposing a port.
    await page.setViewportSize({ width: 330, height: 450 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    assert.deepEqual(errors, []);
    console.log('PASS: timeout blocks uncertain writes; compact layout has no horizontal page overflow');

    // Realistic size: 167 settings, several sections and multiple pages.
    const large = await browser.newPage({ viewport: { width: 850, height: 700 } });
    large.on('pageerror', error => errors.push(error.message));
    await large.setContent(markup);
    await large.evaluate(() => handleMessage('{"init":true}'));
    const largeFields = [
      ...Array.from({ length: 63 }, (_, i) => field('dns.setting' + i, false, 'boolean')),
      ...Array.from({ length: 24 }, (_, i) => field('dhcp.setting' + i, i, 'integer')),
      ...Array.from({ length: 40 }, (_, i) => field('webserver.setting' + i, 'value', 'string')),
      ...Array.from({ length: 40 }, (_, i) => field('misc.setting' + i, i, 'integer'))
    ];
    await large.evaluate(fields => handleMessage(JSON.stringify({ requestID: requests.at(-1).requestID, ok: true, fields })), largeFields);
    assert.equal(await large.locator('.config-field').count(), 20);
    assert.match(await large.locator('#configCount').innerText(), /1–20 \/ 63 matching settings · 167 total/);
    await large.locator('.config-field').first().locator('input').check();
    await large.locator('#configNext').click();
    assert.match(await large.locator('#configCount').innerText(), /21–40 \/ 63/);
    await large.locator('#configPrevious').click();
    assert.equal(await large.locator('.config-field').first().locator('input').isChecked(), true);
    await choose(large, 'dhcp');
    assert.equal(await large.locator('.config-field label').first().innerText(), 'dhcp.setting0');
    assert.match(await large.locator('#configCount').innerText(), /1–20 \/ 24/);
    await large.locator('.config-field').first().locator('input').fill('');
    await choose(large, '');
    assert.match(await large.locator('#configCount').innerText(), /1–20 \/ 167/);
    assert.equal(await large.locator('.config-field').count(), 20);
    await choose(large, 'dhcp');
    assert.equal(await large.locator('.config-field').first().locator('input').inputValue(), '');
    assert.equal(await large.locator('.config-field').first().locator('input').getAttribute('aria-invalid'), 'true');
    await large.locator('.config-field').first().locator('input').fill('2');
    await choose(large, 'webserver');
    await large.locator('#configSave').click();
    await large.locator('#configAccept').click();
    const largeRequest = await large.evaluate(() => requests.at(-1));
    assert.deepEqual(largeRequest.changes.map(change => [change.path, change.value]), [['dns.setting0', true], ['dhcp.setting0', 2]]);
    await large.evaluate(() => handleMessage(JSON.stringify({ requestID: requests.at(-1).requestID, ok: false, error: 'Test write error' })));
    const timings = await large.evaluate(() => {
      const timings = [];
      for (let i = 0; i < 30; i++) {
        const name = i % 2 ? 'dns' : '';
        const button = document.querySelector('.config-section[data-section="' + name + '"]');
        const start = performance.now();
        button.click();
        timings.push(performance.now() - start);
        if (document.querySelectorAll('.config-field').length > 20) { throw new Error('Too many fields rendered'); }
      }
      return timings;
    });
    assert.ok(Math.max(...timings) < 500, 'A local filter switch should not block the browser for half a second');
    assert.equal(await large.evaluate(() => requests.length), 2, 'Filtering and paging must not request the API');
    assert.equal(await large.evaluate(() => Object.keys(translationCalls).length), 0, 'The UI must not invoke the synchronous SDK translation');
    await large.locator('#configSearch').fill('does-not-exist');
    assert.equal(await large.locator('.config-field').count(), 0);
    assert.match(await large.locator('#configCount').innerText(), /0 \/ 0 matching/);
    await large.locator('#configSearch').fill('');
    assert.equal(await large.locator('.config-field').count(), 20);
    assert.deepEqual(errors, []);
    console.log('PASS: 167 settings paginated and filtered locally; drafts survive switches; no synchronous SDK translation; max filter time ' + Math.max(...timings).toFixed(1) + ' ms');

    if (process.env.CONFIG_TILE_HTML) {
      // Embed the actual PHP RenderTile output, including the translated initial message.
      const embedded = await browser.newPage({ viewport: { width: 850, height: 700 } });
      embedded.on('pageerror', error => errors.push(error.message));
      await embedded.setContent('<iframe id="tile" style="width:100%;height:650px;border:0"></iframe>');
      const stub = '<script>window.requests=[];window.translate=()=>{throw new Error("SDK translation called");};window.requestAction=(action,json)=>requests.push({action,...JSON.parse(json)});</script>';
      await embedded.locator('#tile').evaluate((iframe, html) => { iframe.srcdoc = html; }, stub + fs.readFileSync(process.env.CONFIG_TILE_HTML, 'utf8'));
      const frame = embedded.frameLocator('#tile');
      await frame.locator('#configLoad').waitFor();
      assert.equal(await frame.locator('#configLoad').innerText(), 'Neu laden');
      const contentFrame = embedded.frames().find(frame => frame !== embedded.mainFrame());
      await contentFrame.evaluate(fields => handleMessage(JSON.stringify({ requestID: requests.at(-1).requestID, ok: true, fields })), largeFields);
      assert.match(await frame.locator('#configCount').innerText(), /63 passende Einstellungen · 167 insgesamt/);
      const checkCounter = async () => {
        const box = await frame.locator('#configCount').boundingBox();
        const iframeBox = await embedded.locator('#tile').boundingBox();
        assert.ok(box && box.y >= iframeBox.y && box.y + box.height < iframeBox.y + iframeBox.height / 2, 'Counter must stay in the visible upper part of the tile');
      };
      await checkCounter();
      await frame.locator('.config-section[data-section=""]').click();
      assert.match(await frame.locator('#configCount').innerText(), /167 passende Einstellungen/);
      await frame.locator('#configSearch').fill('dhcp');
      assert.match(await frame.locator('#configCount').innerText(), /24 passende Einstellungen/);
      await frame.locator('#configFields').evaluate(element => { element.scrollTop = element.scrollHeight; });
      await checkCounter();
      await embedded.setViewportSize({ width: 340, height: 470 });
      await embedded.locator('#tile').evaluate(element => { element.style.height = '430px'; });
      await checkCounter();
      assert.equal(await contentFrame.evaluate(() => requests.length), 1);
      assert.deepEqual(errors, []);
      console.log('PASS: PHP-generated tile in an iframe uses German server translations; counter stays visible while filtering and scrolling at compact and full sizes');
    }
    console.log('All configuration browser checks passed.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
