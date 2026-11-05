import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';

const base = (process.env.TEST_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const outputDirectory = resolve('.qa/lighthouse');
const executablePath = process.env.CHROME_PATH || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const categories = ['performance', 'accessibility', 'best-practices', 'seo'];
const summary = [];
const profileDirectory = resolve('.qa', 'lighthouse-browser-' + Date.now());
await mkdir(outputDirectory, { recursive: true });
await mkdir(profileDirectory, { recursive: true });

const chrome = await launch({
    chromePath: executablePath,
    chromeFlags: ['--headless=new', '--disable-gpu', '--no-first-run'],
    userDataDir: profileDirectory,
    logLevel: 'error',
});
let browser;

async function audit(name, path) {
    const cachePage = await browser.contexts()[0].newPage();
    const cdp = await cachePage.context().newCDPSession(cachePage);
    await cdp.send('Network.clearBrowserCache');
    await cdp.detach();
    await cachePage.close();
    const result = await lighthouse(base + path, {
        port: chrome.port,
        hostname: '127.0.0.1',
        onlyCategories: categories,
        formFactor: 'mobile',
        throttlingMethod: 'simulate',
        disableStorageReset: true,
        output: ['json', 'html'],
        logLevel: 'error',
        maxWaitForLoad: 45000,
    });
    assert.ok(result, 'Lighthouse returned a report for ' + name);
    const { lhr } = result;
    if (lhr.runtimeError) {
        throw new Error(name + ': ' + lhr.runtimeError.message);
    }
    const finalUrl = lhr.finalDisplayedUrl || lhr.finalUrl;
    assert.equal(new URL(finalUrl).pathname, path, 'Lighthouse reached the expected page for ' + name);
    await writeFile(resolve(outputDirectory, name + '.json'), result.report[0]);
    await writeFile(resolve(outputDirectory, name + '.html'), result.report[1]);
    const findings = Object.values(lhr.audits)
        .filter(item => typeof item.score === 'number' && item.score < 1)
        .map(item => ({
            id: item.id,
            title: item.title,
            score: item.score,
            displayValue: item.displayValue,
            explanation: item.explanation,
            details: Array.isArray(item.details?.items) ? item.details.items.slice(0, 5) : undefined,
        }));
    const row = {
        page: name,
        url: finalUrl,
        scores: Object.fromEntries(categories.map(id => [id, Math.round(lhr.categories[id].score * 100)])),
        metrics: Object.fromEntries(['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index']
            .map(id => [id, lhr.audits[id]?.displayValue])),
        warnings: lhr.runWarnings,
        findings,
    };
    summary.push(row);
    console.log(JSON.stringify({
        page: row.page,
        scores: row.scores,
        metrics: row.metrics,
        warnings: row.warnings,
        findings: findings.map(({ id, title, displayValue }) => ({ id, title, displayValue })),
    }));
    await writeFile(resolve(outputDirectory, 'summary.json'), JSON.stringify(summary, null, 2));
}

try {
    browser = await chromium.connectOverCDP('http://127.0.0.1:' + chrome.port);
    const context = browser.contexts()[0];
    await audit('login', '/login');
    await audit('register', '/register');

    const page = await context.newPage();
    await page.goto(base + '/login');
    await page.locator('#login-username').fill(process.env.TEST_MEMBER_USERNAME || 'member');
    await page.locator('#login-password').fill(process.env.TEST_MEMBER_PASSWORD || 'member');
    await page.locator('#login-submit').click();
    await page.waitForURL(base + '/stocks');
    await page.close();

    await audit('stocks', '/stocks');
    await audit('profile', '/profile');
    console.log(JSON.stringify({ formFactor: 'mobile', reports: '.qa/lighthouse', pages: summary.map(({ page, scores }) => ({ page, ...scores })) }, null, 2));
} finally {
    await browser?.close();
    await chrome.kill();
}
