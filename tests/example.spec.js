// @ts-check
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const LOGIN_EMAIL = 'maniiax3d@gmail.com';
const LOGIN_PASSWORD = 'Stanislav';

test('K1 diagnostika kroku a URL', async ({ page }) => {
  const lines = [];
  const outDir = path.join(process.cwd(), 'test-results');
  const logFile = path.join(outDir, 'k1-steps.txt');

  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(logFile, '', 'utf8');

  const flushLog = () => {
    fs.writeFileSync(logFile, lines.join('\n'), 'utf8');
  };

  const writeStep = async (label) => {
    const title = await page.title().catch(() => '');
    lines.push(`[${new Date().toISOString()}] ${label}`);
    lines.push(`URL: ${page.url()}`);
    lines.push(`TITLE: ${title}`);
    lines.push('');
    flushLog();
  };

  page.on('framenavigated', (frame) => {
    if (frame === page.mainFrame()) {
      lines.push(`[${new Date().toISOString()}] NAVIGATE ${frame.url()}`);
      flushLog();
    }
  });

  try {
    await page.goto('');
    await writeStep('Po otevreni rootu');

    await page.getByLabel('E-mail').fill(LOGIN_EMAIL);
    await page.getByLabel('Heslo').fill(LOGIN_PASSWORD);
    await writeStep('Po vyplneni login formulare');

    await page.getByRole('button', { name: 'Přihlásit' }).click();
    await writeStep('Po kliknuti na Prihlasit');

    await page.waitForLoadState('networkidle').catch(() => {});
    await writeStep('Po dokonceni login requestu');

    await page.goto('?sekce=1');
    await writeStep('Po otevreni admin sekce');

    const k1 = page.locator('article.cb-admin-karty');
    await expect(k1).toBeVisible();
    await writeStep('K1 je viditelna');

    const toggle = k1.locator('[data-card-toggle="1"]');
    if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
      await toggle.click();
      await writeStep('Po rozbaleni K1');
    }

    const expanded = page.locator('.dash_maxi_card [data-card-expanded]');
    await expect(expanded).toBeVisible();
    await writeStep('Expanded cast K1 je viditelna');

    await page.screenshot({ path: 'test-results/k1-debug.png', fullPage: true });
    lines.push(`[${new Date().toISOString()}] SCREENSHOT test-results/k1-debug.png`);
    lines.push('');
    flushLog();
  } catch (error) {
    lines.push(`[${new Date().toISOString()}] ERROR ${String(error)}`);
    lines.push('');
    flushLog();
    throw error;
  } finally {
    flushLog();
    console.log(`K1 step log saved to ${logFile}`);
  }
});
