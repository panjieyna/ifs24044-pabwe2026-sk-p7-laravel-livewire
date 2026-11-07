import { chromium } from 'playwright-core';
import AxeBuilder from '@axe-core/playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:8000';
const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    headless: true,
});
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
const errors = [];
const audits = [];
page.on('pageerror', error => errors.push(error.message));
await mkdir('.qa', { recursive: true });

async function audit(name) {
    const result = await new AxeBuilder({ page }).analyze();
    audits.push({ name, violations: result.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.map(n => n.target) })) });
}

async function login(username, password) {
    await page.goto(base + '/login');
    await page.locator('#login-username').fill(username);
    await page.locator('#login-password').fill(password);
    await page.locator('#login-submit').click();
    await page.waitForURL(username === 'admin' ? '**/admin/users' : '**/stocks');
}

try {
    await page.goto(base + '/login');
    const loginPassword = page.locator('#login-password');
    const passwordToggle = page.locator('#login-password-toggle');
    const previewPassword = 'Password preview 123!';
    assert.equal(await loginPassword.getAttribute('type'), 'password');
    assert.equal(await passwordToggle.getAttribute('type'), 'button');
    assert.equal(await passwordToggle.getAttribute('aria-controls'), 'login-password');
    assert.equal(await passwordToggle.getAttribute('aria-pressed'), 'false');
    assert.equal(await page.getByRole('button', { name: 'Tampilkan password', exact: true }).count(), 1);
    await loginPassword.fill(previewPassword);
    await passwordToggle.click();
    assert.equal(await loginPassword.getAttribute('type'), 'text');
    assert.equal(await loginPassword.inputValue(), previewPassword);
    assert.equal(await passwordToggle.getAttribute('aria-pressed'), 'true');
    assert.equal(await page.getByRole('button', { name: 'Sembunyikan password', exact: true }).count(), 1);
    assert.equal(new URL(page.url()).pathname, '/login');
    await passwordToggle.focus();
    await page.keyboard.press('Space');
    assert.equal(await loginPassword.getAttribute('type'), 'password');
    assert.equal(await loginPassword.inputValue(), previewPassword);
    assert.equal(await passwordToggle.getAttribute('aria-pressed'), 'false');
    assert.equal(new URL(page.url()).pathname, '/login');
    await loginPassword.fill('');
    await audit('login');
    await page.goto(base + '/register');
    const registerPassword = page.locator('#password');
    const registerConfirmation = page.locator('#password_confirmation');
    await registerPassword.fill(previewPassword);
    await registerConfirmation.fill(previewPassword);
    for (const field of [
        { input: registerPassword, other: registerConfirmation, toggle: '#register-password-toggle', control: 'password', label: 'password' },
        { input: registerConfirmation, other: registerPassword, toggle: '#register-confirmation-toggle', control: 'password_confirmation', label: 'konfirmasi password' },
    ]) {
        const toggle = page.locator(field.toggle);
        assert.equal(await field.input.getAttribute('type'), 'password');
        assert.equal(await field.other.getAttribute('type'), 'password');
        assert.equal(await toggle.getAttribute('type'), 'button');
        assert.equal(await toggle.getAttribute('aria-controls'), field.control);
        assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
        assert.equal(await page.getByRole('button', { name: `Tampilkan ${field.label}`, exact: true }).count(), 1);
        await toggle.click();
        assert.equal(await field.input.getAttribute('type'), 'text');
        assert.equal(await field.other.getAttribute('type'), 'password');
        assert.equal(await field.input.inputValue(), previewPassword);
        assert.equal(await field.other.inputValue(), previewPassword);
        assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
        assert.equal(await page.getByRole('button', { name: `Sembunyikan ${field.label}`, exact: true }).count(), 1);
        assert.equal(new URL(page.url()).pathname, '/register');
        await toggle.focus();
        await page.keyboard.press('Space');
        assert.equal(await field.input.getAttribute('type'), 'password');
        assert.equal(await field.other.getAttribute('type'), 'password');
        assert.equal(await field.input.inputValue(), previewPassword);
        assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
        assert.equal(new URL(page.url()).pathname, '/register');
    }
    await registerPassword.fill('');
    await registerConfirmation.fill('');
    await audit('register');
    await login('member', 'member');
    await page.locator('#stock-search').fill('QA barang ');
    await page.waitForTimeout(500);
    while (await page.getByRole('button', { name: /^Hapus QA barang / }).count()) {
        await page.getByRole('button', { name: /^Hapus QA barang / }).first().click();
        await page.getByRole('dialog').getByRole('button', { name: 'Hapus barang', exact: true }).click();
        await page.getByRole('dialog').waitFor({ state: 'hidden' });
    }
    await page.locator('#stock-search').fill('');
    await page.waitForTimeout(500);
    await audit('inventory-desktop');
    await page.screenshot({ path: '.qa/inventory-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await audit('inventory-mobile');
    const aside = page.locator('#sidebar');
    assert.equal(await aside.evaluate(el => el.inert), true);
    await page.getByRole('button', { name: 'Buka navigasi' }).click();
    await page.waitForTimeout(350);
    assert.equal(await aside.evaluate(el => el.inert), false);
    await page.keyboard.press('Escape');
    await page.screenshot({ path: '.qa/inventory-mobile.png', fullPage: true, animations: 'disabled' });
    await page.setViewportSize({ width: 1440, height: 1000 });

    const name = 'QA barang ' + Date.now();
    await page.getByRole('button', { name: 'Tambah barang', exact: true }).click();
    await page.locator('#stock-name').fill(name);
    await page.locator('#stock-sku').fill('QA-BROWSER');
    await page.locator('#stock-quantity').fill('6');
    await page.locator('#stock-minimum').fill('5');
    await page.locator('#stock-price').fill('12500.50');
    await page.locator('#stock-description').fill('**Barang uji** dengan catatan Markdown.');
    await audit('create-dialog');
    await page.getByRole('button', { name: 'Simpan barang', exact: true }).click();
    await page.getByRole('dialog').waitFor({ state: 'hidden' });
    const row = page.locator('tbody tr').filter({ hasText: name });
    await row.getByRole('button', { name: 'Kurangi stok ' + name, exact: true }).click();
    await row.locator('.status-badge').filter({ hasText: 'Stok menipis' }).waitFor();
    await row.getByRole('link', { name, exact: true }).click();
    await page.waitForURL('**/stocks/*');
    await audit('stock-detail');
    await page.getByRole('button', { name: 'Edit barang', exact: true }).click();
    await page.getByRole('button', { name: 'Editor visual' }).click();
    await page.locator('.toastui-editor-defaultUI').waitFor();
    await audit('markdown-editor');
    await page.getByRole('button', { name: 'Batal', exact: true }).click();
    await page.getByRole('button', { name: 'Hapus', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Hapus barang', exact: true }).click();
    await page.waitForURL('**/stocks');

    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Ekspor Excel' }).click();
    assert.match((await download).suggestedFilename(), /\.xlsx$/);
    await page.getByRole('button', { name: 'Impor', exact: true }).click();
    await audit('import-dialog');
    await page.getByRole('button', { name: 'Batal', exact: true }).click();
    await page.getByRole('button', { name: 'Bantu dengan AI' }).click();
    await audit('ai-dialog');
    await page.getByRole('button', { name: 'Tutup dialog', exact: true }).click();
    await page.goto(base + '/profile');
    await audit('profile');
    await page.getByRole('button', { name: 'Keluar dari akun' }).click();
    await page.waitForURL('**/login');
    await login('admin', 'admin');
    await audit('admin-users');
    await page.getByRole('link', { name: 'Lihat inventaris Member User' }).click();
    await audit('admin-user-stocks');
    assert.equal(await page.getByRole('button', { name: /^Edit |^Hapus |^Tambah stok/ }).count(), 0);
    await writeFile('.qa/browser-audit.json', JSON.stringify({ audits, errors }, null, 2));
    console.log(JSON.stringify({ pages: audits.length, violations: audits.flatMap(a => a.violations).length, errors, audits: audits.filter(a => a.violations.length) }, null, 2));
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    assert.equal(audits.flatMap(a => a.violations).length, 0, 'Axe accessibility violations');
} catch (error) {
    await writeFile('.qa/browser-audit.json', JSON.stringify({ audits, errors, failure: error.message }, null, 2));
    await page.screenshot({ path: '.qa/browser-failure.png', fullPage: true });
    throw error;
} finally {
    await browser.close();
}
