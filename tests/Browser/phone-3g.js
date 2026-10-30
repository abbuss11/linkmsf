/*
 * Phase 3 deliverable, in a real browser: a candidate applies from a 360 px
 * phone on a 3G connection, and the network is cut during the CV upload.
 *
 * Prerequisites: `php artisan migrate:fresh --seed`, `npm run build`,
 * `php artisan serve`. Usage: node tests/Browser/phone-3g.js <output dir>
 * (screenshots are written to <output dir>/shots).
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const SP = process.argv[2] || fs.mkdtempSync(path.join(require('os').tmpdir(), 'link-'));
const BASE = process.env.APP_URL || 'http://127.0.0.1:8000';
fs.mkdirSync(SP + '/shots', { recursive: true });
// 1.2 MB PDF, detected as PDF from its content.
fs.writeFileSync(SP + '/cv-test.pdf', '%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n' + '%'.repeat(1200000) + '\ntrailer << /Root 1 0 R >>\n%%EOF\n');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 360, height: 740 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR',
    userAgent: 'Mozilla/5.0 (Linux; Android 10; itel A56) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0 Mobile Safari/537.36' });
  const page = await context.newPage();
  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  // "Regular 3G": 750 kb/s down, 250 kb/s up, 100 ms latency.
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 100, downloadThroughput: 750 * 1024 / 8, uploadThroughput: 250 * 1024 / 8 });
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push(e.message));
  let bytes = 0; page.on('response', async r => { try { const b = await r.body(); bytes += b.length; } catch (e) {} });
  const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

  let t = Date.now(); bytes = 0;
  await page.goto(BASE + '/offres'); await page.waitForLoadState('networkidle');
  log('job board', Date.now() - t, 'ms', Math.round(bytes / 1024), 'KB');
  await page.screenshot({ path: SP + '/shots/1-offres.png', fullPage: true });

  t = Date.now(); bytes = 0;
  await page.click('text=Chargé(e) de suivi-évaluation');
  await page.waitForLoadState('networkidle');
  log('offer page', Date.now() - t, 'ms', Math.round(bytes / 1024), 'KB');
  await page.screenshot({ path: SP + '/shots/2-offre.png', fullPage: true });

  await page.click('a:has-text("Postuler")');
  await page.waitForURL(/connexion/);
  await page.fill('input[name=email]', 'candidat@link.test');
  await page.fill('input[name=password]', 'password');
  await page.click('button[type=submit]:has-text("Se connecter")');
  await page.waitForURL(/modifier/);
  await page.waitForLoadState('networkidle');
  log('wizard step 1 at', page.url());
  await page.screenshot({ path: SP + '/shots/3-etape-profil.png', fullPage: true });

  await page.click('button:has-text("Enregistrer et continuer")');
  await page.waitForSelector('text=Années d’expérience en suivi-évaluation');
  await page.fill('input[type=number]', '5');
  await page.check('input[type=checkbox][value=fr]');
  await page.check('input[type=radio][value=yes]');
  await page.fill('textarea', 'Je souhaite mettre mon expérience du suivi-évaluation au service des écoles de Tillabéri.');
  await page.screenshot({ path: SP + '/shots/4-etape-questions.png', fullPage: true });
  await page.click('button:has-text("Enregistrer et continuer")');
  await page.waitForSelector('text=Choisir un fichier');
  log('documents step');

  // Upload 1.2 MB on 3G and cut the network in the middle.
  t = Date.now();
  const input = page.locator('section:has(h2:has-text("CV")) input[type=file]').first();
  await input.setInputFiles(SP + '/cv-test.pdf');
  await page.waitForTimeout(4000);
  await page.screenshot({ path: SP + '/shots/5-envoi-en-cours.png', fullPage: true });
  log('going offline');
  await context.setOffline(true);
  await cdp.send('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
  await page.waitForTimeout(6000);
  await page.screenshot({ path: SP + '/shots/6-coupure.png', fullPage: true });
  log('back online');
  await context.setOffline(false);
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 100, downloadThroughput: 750 * 1024 / 8, uploadThroughput: 250 * 1024 / 8 });
  await page.waitForSelector('text=✓ cv-test.pdf', { timeout: 180000 });
  log('upload complete after', Math.round((Date.now() - t) / 1000), 's');
  await page.screenshot({ path: SP + '/shots/7-envoye.png', fullPage: true });

  await page.click('button:has-text("Enregistrer et continuer")');
  await page.waitForSelector('text=Vérifiez votre candidature');
  await page.check('input[type=checkbox][wire\\:model=consent]');
  await page.screenshot({ path: SP + '/shots/8-verification.png', fullPage: true });
  await page.click('button:has-text("Envoyer ma candidature")');
  await page.waitForURL(/confirmation/, { timeout: 60000 });
  await page.screenshot({ path: SP + '/shots/9-confirmation.png', fullPage: true });
  log('confirmation:', (await page.textContent('main')).replace(/\s+/g, ' ').trim().slice(0, 250));

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
  log('horizontal overflow:', overflow, '| console errors:', errors.length ? errors : 'none');
  await browser.close();
})().catch(e => { console.error('FAILED', e.message); process.exit(1); });
