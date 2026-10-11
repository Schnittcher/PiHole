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
    await page.setContent('<style>' + fs.readFileSync(path.join(root, 'libs/tile.css'), 'utf8') + '</style><script>window.requests=[];window.requestAction=(action,json)=>requests.push({action,...JSON.parse(json)});</script>' + fs.readFileSync(path.join(root, 'PiHoleConfig/module.html'), 'utf8'));
    const request = () => page.evaluate(() => requests.at(-1));
    const reply = (data) => page.evaluate(data => handleMessage(JSON.stringify({ requestID: requests.at(-1).requestID, action: requests.at(-1).action, ...data })), data);
    const input = name => page.locator('.config-field').filter({ has: page.locator('label', { hasText: name }) }).locator('input,textarea,select');
    await page.evaluate(() => handleMessage('{"init":true}'));
    assert.equal((await request()).action, 'LoadConfiguration');
    await reply({ ok: true, fields });
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
    console.log('All configuration browser checks passed.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
