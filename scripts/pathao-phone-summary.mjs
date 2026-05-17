import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer-core';

const mode = process.argv[2] || 'search';
const phone = process.argv[3] || '';
const baseDir = process.cwd();
const userDataDir = path.join(baseDir, 'storage', 'app', 'pathao-automation-profile');
const targetUrl = 'https://merchant.pathao.com/courier/orders/create';

function resolveChromePath() {
  const candidates = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  throw new Error('Chrome/Edge executable not found');
}

async function launchBrowser(headless) {
  fs.mkdirSync(userDataDir, { recursive: true });

  return puppeteer.launch({
    headless,
    executablePath: resolveChromePath(),
    userDataDir,
    defaultViewport: { width: 1440, height: 960 },
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
}

async function ensurePathaoPage(page) {
  await page.goto(targetUrl, { waitUntil: 'networkidle2', timeout: 120000 });
  await new Promise((resolve) => setTimeout(resolve, 2000));
}

async function isLoggedIn(page) {
  const url = page.url();
  if (url.includes('/login')) {
    return false;
  }

  const body = await page.locator('body').innerText().catch(() => '');
  return !/login|sign in|otp/i.test(body);
}

async function findPhoneInput(page) {
  const selectors = [
    'input[placeholder*="phone" i]',
    'input[name*="phone" i]',
    'input[type="tel"]',
  ];

  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.count()) {
      return locator;
    }
  }

  throw new Error('Recipient phone input not found on Pathao page');
}

function pickMetric(text, label) {
  const regex = new RegExp(`${label}\\s+(\\d+)`, 'i');
  const match = text.match(regex);
  return match ? Number(match[1]) : 0;
}

async function scrapeSummary(page, mobile) {
  const input = await findPhoneInput(page);
  await input.click({ clickCount: 3 });
  await input.fill(mobile);
  await new Promise((resolve) => setTimeout(resolve, 3000));

  await page.waitForFunction(() => {
    const body = document.body.innerText || '';
    return /Processed\s+\d+/i.test(body) && /Delivered\s+\d+/i.test(body) && /Returned\s+\d+/i.test(body);
  }, { timeout: 15000 }).catch(() => {});

  const bodyText = await page.locator('body').innerText();
  const processed = pickMetric(bodyText, 'Processed');
  const delivered = pickMetric(bodyText, 'Delivered');
  const returned = pickMetric(bodyText, 'Returned');
  const ratingMatch = bodyText.match(/Customer Rating:\s*([A-Za-z]+)/i);
  const customerRating = ratingMatch ? ratingMatch[1] : null;

  const messageMatch = bodyText.match(/This customer[^\n]+/i);
  const note = messageMatch ? messageMatch[0].trim() : null;

  return {
    source: 'pathao_dashboard',
    mobile,
    total_parcel: processed,
    success_parcel: delivered,
    cancelled_parcel: returned,
    success_ratio: processed > 0 ? Math.round((delivered / processed) * 100) : 0,
    customer_rating: customerRating,
    note,
  };
}

async function main() {
  if (mode === 'login') {
    const browser = await launchBrowser(false);
    const [page] = await browser.pages();
    await ensurePathaoPage(page);

    if (await isLoggedIn(page)) {
      console.log(JSON.stringify({ status: 'success', message: 'Pathao session already active' }));
      await browser.close();
      return;
    }

    console.log('Log in to Pathao in the opened browser window, then press Enter here to continue.');
    process.stdin.resume();
    process.stdin.setEncoding('utf8');
    await new Promise((resolve) => process.stdin.once('data', resolve));

    await ensurePathaoPage(page);
    if (!(await isLoggedIn(page))) {
      throw new Error('Pathao login not detected after confirmation');
    }

    console.log(JSON.stringify({ status: 'success', message: 'Pathao login saved for automation' }));
    await browser.close();
    return;
  }

  if (!phone) {
    throw new Error('Phone number is required');
  }

  const browser = await launchBrowser(true);
  try {
    const [page] = await browser.pages();
    await ensurePathaoPage(page);

    if (!(await isLoggedIn(page))) {
      console.log(JSON.stringify({
        status: 'login_required',
        message: 'Run `php artisan pathao:login` once to save a Pathao merchant session for automation.',
      }));
      return;
    }

    const summary = await scrapeSummary(page, phone);
    console.log(JSON.stringify({ status: 'success', data: summary }));
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.log(JSON.stringify({
    status: 'error',
    message: error.message,
  }));
  process.exit(1);
});
