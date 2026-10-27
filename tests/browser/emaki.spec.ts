import { test, expect, type Locator, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Browser behaviour of the viewer, against the fixture page
 * tests/fixture/index.html. Every case that touches the dialog runs at a phone
 * width (390 px) and at a desktop width (1280 px).
 *
 * Fixture: container 0 has three images (landscape, portrait, square) and a
 * non-image link; container 1 has two images and must be its own sequence.
 */

const PAGE = '/tests/fixture/index.html';

const dialog = (page: Page) => page.locator('dialog.emaki');
const image = (page: Page) => dialog(page).locator('img.emaki-image');
const counter = (page: Page) => dialog(page).locator('.emaki-counter');
const caption = (page: Page) => dialog(page).locator('.emaki-caption');

// The item-th image link (one that wraps an <img>) of the container-th container.
const trigger = (page: Page, container: number, item: number): Locator =>
  page.locator('[data-emaki]').nth(container).locator('a:has(img)').nth(item);

async function open(page: Page, container = 0, item = 0) {
  await page.goto(PAGE);
  await trigger(page, container, item).click();
  await expect(dialog(page)).toBeVisible();
  await expect(dialog(page)).not.toHaveClass(/is-loading/);
}

// Opens with a synthetic click. Unlike a real click it does not move focus to
// the link, so the browser has no focus of its own to restore on close: only
// the viewer's own focus return can put focus back on the link. (After a real
// click Chromium restores focus by itself, which would hide a missing return.)
async function openUnfocused(page: Page, container = 0, item = 0) {
  await page.goto(PAGE);
  await trigger(page, container, item).dispatchEvent('click');
  await expect(dialog(page)).toBeVisible();
  await expect(dialog(page)).not.toHaveClass(/is-loading/);
}

// Accessible name, counter and source of the image on show.
async function expectShown(page: Page, number: number, src: string, total = 3) {
  await expect(dialog(page)).toHaveAccessibleName(`Bild ${number} von ${total}`);
  await expect(counter(page)).toHaveText(`${number} / ${total}`);
  await expect(image(page)).toHaveAttribute('src', src);
}

for (const viewport of [
  { width: 390, height: 844 },
  { width: 1280, height: 800 },
]) {
  test.describe(`${viewport.width} px`, () => {
    test.use({ viewport });

    test('click opens the dialog with the right src, srcset and sizes', async ({ page }) => {
      await page.goto(PAGE);
      await trigger(page, 0, 0).click();
      await expect(dialog(page)).toBeVisible();
      await expect(page).toHaveURL(/\/tests\/fixture\/index\.html$/);

      await expect(image(page)).toHaveAttribute('src', 'img/innenhof.jpg');
      await expect(image(page)).toHaveAttribute('srcset', 'img/innenhof.jpg 800w');
      await expect(image(page)).toHaveAttribute('sizes', '(max-width: 40rem) 100vw, 800px');
      await expect(dialog(page)).not.toHaveClass(/is-loading/);
      // The browser picked the one candidate and decoded it. naturalWidth is not
      // the file width here: with w-descriptors it is divided by the density
      // that sizes implies (390 px on a 390 px screen), so only "loaded" is checked.
      expect(await image(page).evaluate((img) => (img as HTMLImageElement).currentSrc)).toMatch(/\/img\/innenhof\.jpg$/);
      expect(await image(page).evaluate((img) => (img as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);

      // A link without data-srcset gives no srcset; without data-sizes the sizes are 100vw.
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 2, 'img/eingang.jpg');
      await expect(image(page)).not.toHaveAttribute('srcset');
      await expect(image(page)).toHaveAttribute('sizes', '100vw');
    });

    // The stage keeps its padding inside the dialog. In content-box its height 100% plus
    // the padding ran past the dialog (overflow hidden): the caption below the fold, a
    // portrait picture cut off. toBeVisible() is true for both, so the boxes are compared.
    test('picture and caption lie inside the dialog, without a box-sizing reset on the page', async ({ page }) => {
      const inside = async (target: Locator, what: string) => {
        const box = await target.boundingBox();
        const frame = await dialog(page).boundingBox();
        expect(box, what).not.toBeNull();
        expect(frame).not.toBeNull();
        expect(box!.y, `${what}: top`).toBeGreaterThanOrEqual(frame!.y);
        expect(box!.y + box!.height, `${what}: bottom`).toBeLessThanOrEqual(frame!.y + frame!.height);
      };
      await open(page, 0, 0);
      expect(await page.evaluate(() => getComputedStyle(document.body).boxSizing)).toBe('content-box');
      await expect(caption(page)).toHaveText('Unser Innenhof');
      await inside(image(page), 'landscape picture');
      await inside(caption(page), 'caption');
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 2, 'img/eingang.jpg');
      await expect(dialog(page)).not.toHaveClass(/is-loading/);
      await inside(image(page), 'portrait picture');
    });

    test('arrow keys step through the sequence and wrap around', async ({ page }) => {
      await open(page, 0, 0);
      await expectShown(page, 1, 'img/innenhof.jpg');
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 2, 'img/eingang.jpg');
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 3, 'img/garten.jpg');
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 1, 'img/innenhof.jpg');
      await page.keyboard.press('ArrowLeft');
      await expectShown(page, 3, 'img/garten.jpg');
    });

    test('Home and End jump to the first and the last image', async ({ page }) => {
      await open(page, 0, 1);
      await page.keyboard.press('End');
      await expectShown(page, 3, 'img/garten.jpg');
      await page.keyboard.press('Home');
      await expectShown(page, 1, 'img/innenhof.jpg');
    });

    test('Escape closes and returns focus to the link that opened the dialog', async ({ page }) => {
      await openUnfocused(page, 0, 1);
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 3, 'img/garten.jpg');
      await page.keyboard.press('Escape');
      await expect(dialog(page)).not.toBeVisible();
      // The origin is the link that was clicked (item 1), not the image shown last.
      await expect(trigger(page, 0, 1)).toBeFocused();
    });

    test('the close button closes and returns focus', async ({ page }) => {
      await openUnfocused(page, 0, 1);
      await dialog(page).getByRole('button', { name: 'Schließen' }).click();
      await expect(dialog(page)).not.toBeVisible();
      await expect(trigger(page, 0, 1)).toBeFocused();
    });

    test('dialog name, counter and button names are the German labels', async ({ page }) => {
      await open(page, 0, 1);
      await expectShown(page, 2, 'img/eingang.jpg');
      await expect(dialog(page).getByRole('button', { name: 'Schließen' })).toBeVisible();
      await expect(dialog(page).getByRole('button', { name: 'Vorheriges Bild' })).toBeVisible();
      await expect(dialog(page).getByRole('button', { name: 'Nächstes Bild' })).toBeVisible();
    });

    test('a click on the image keeps the dialog open, a click beside it closes', async ({ page }) => {
      await open(page, 0, 0);
      await image(page).click();
      await expect(dialog(page)).toBeVisible();

      // Top-left corner of the stage: beside the picture, on no control.
      const hit = await page.evaluate(() => document.elementFromPoint(8, 8)?.className ?? '');
      expect(hit).toContain('emaki-stage');
      await page.mouse.click(8, 8);
      await expect(dialog(page)).not.toBeVisible();
    });

    test('captions: the attribute wins, an empty attribute hides, alt is the fallback', async ({ page }) => {
      const cases = [
        // item, caption text (empty = no caption), img alt in the dialog, aria-describedby
        { item: 0, text: 'Unser Innenhof', alt: '', describedby: 'emaki-caption' },
        { item: 1, text: 'Hauseingang bei Abendlicht', alt: '', describedby: 'emaki-caption' },
        { item: 2, text: '', alt: 'Kleiner Garten', describedby: null },
      ];
      for (const c of cases) {
        await open(page, 0, c.item);
        if (c.text) {
          await expect(caption(page)).toBeVisible();
          await expect(caption(page)).toHaveText(c.text);
        } else {
          await expect(caption(page)).toBeHidden();
        }
        await expect(image(page)).toHaveAttribute('alt', c.alt);
        if (c.describedby) {
          await expect(dialog(page)).toHaveAttribute('aria-describedby', c.describedby);
        } else {
          await expect(dialog(page)).not.toHaveAttribute('aria-describedby');
        }
        await page.keyboard.press('Escape');
        await expect(dialog(page)).not.toBeVisible();
      }
    });

    test('emaki:open fires for every image shown, with href, image, index and count', async ({ page }) => {
      await page.goto(PAGE);
      await page.evaluate(() => {
        (window as any).emakiOpen = [];
        document.addEventListener('emaki:open', (e) => {
          (window as any).emakiOpen.push({ tag: (e.target as Element).tagName, ...(e as CustomEvent).detail });
        });
      });
      await trigger(page, 0, 0).click();
      await page.keyboard.press('ArrowRight');
      await page.keyboard.press('ArrowRight');
      const events = await page.evaluate(() => (window as any).emakiOpen);
      expect(events).toEqual([
        { tag: 'A', href: 'img/innenhof.jpg', image: 'innenhof', index: 0, count: 3 },
        { tag: 'A', href: 'img/eingang.jpg', image: 'eingang', index: 1, count: 3 },
        { tag: 'A', href: 'img/garten.jpg', image: null, index: 2, count: 3 },
      ]);
    });

    test('a second container is its own sequence', async ({ page }) => {
      await open(page, 1, 0);
      await expectShown(page, 1, 'img/garten.jpg', 2);
      await page.keyboard.press('ArrowLeft');
      await expectShown(page, 2, 'img/innenhof.jpg', 2);
      await expect(image(page)).not.toHaveAttribute('srcset');
    });

    test('a container nested in another one is its own sequence; the outer one keeps its own links', async ({ page }) => {
      // Container 3 is the inner one; container 2 is the outer one, with two links of its own
      // before the inner container (trigger() counts the inner links too, so item 1 is the outer's second link).
      await open(page, 3, 0);
      await expectShown(page, 1, 'img/innenhof.jpg', 2);
      await page.keyboard.press('ArrowRight');
      await expectShown(page, 2, 'img/garten.jpg', 2);
      await page.keyboard.press('Escape');
      await expect(dialog(page)).not.toBeVisible();
      await open(page, 2, 1);
      await expectShown(page, 2, 'img/eingang.jpg', 2);
    });

    test('a link to a non-image inside the container is left alone', async ({ page }) => {
      await page.goto(PAGE);
      // Navigating away proves the link was not intercepted: a viewer that
      // prevented the default would have opened the dialog instead.
      await Promise.all([
        page.waitForURL(/\/tests\/fixture\/info\.txt$/),
        page.getByRole('link', { name: 'Leistungen als Textdatei' }).click(),
      ]);
    });

    test('axe finds no WCAG A/AA violations while the dialog is open', async ({ page }) => {
      await open(page, 0, 0);
      // Let the opening animation finish, so axe measures the settled colours.
      await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => {}))));
      const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();
      const findings = result.violations.map((v) => {
        const places = v.nodes.map((n) => `    ${n.target.join(' ')}`).join('\n');
        return `[${v.impact}] ${v.id}: ${v.help}\n${places}`;
      });
      expect(findings.join('\n\n')).toBe('');
    });

    test('no element has a style attribute, dialog open or not', async ({ page }) => {
      await page.goto(PAGE);
      expect(await page.locator('[style]').count()).toBe(0);
      await open(page, 0, 0);
      expect(await page.locator('[style]').count()).toBe(0);
    });
  });
}

test.describe('independent of viewport', () => {
  test('stores nothing in the browser and talks to no other address', async ({ page, context }) => {
    const origin = new URL(test.info().project.use.baseURL!).origin;
    const requests: string[] = [];
    page.on('request', (r) => requests.push(r.url()));

    await open(page, 0, 0);
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Escape');

    expect(requests.filter((u) => !u.startsWith(origin)), 'requests to other addresses').toEqual([]);
    expect(await page.evaluate(() => [localStorage.length, sessionStorage.length])).toEqual([0, 0]);
    expect(await context.cookies()).toEqual([]);
  });

  test('without reduced motion the dialog carries emaki--motion', async ({ page }) => {
    await page.goto(PAGE);
    await expect(dialog(page)).toHaveClass(/emaki--motion/);
  });

  test('with reduced motion the dialog has no opening animation', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(PAGE);
    await expect(dialog(page)).toHaveCount(1);
    await expect(dialog(page)).not.toHaveClass(/emaki--motion/);
  });
});
