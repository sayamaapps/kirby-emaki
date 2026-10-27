// Guards and behaviour of Emaki's script and stylesheet. The guards hold
// whatever the viewer does: nothing stored in the browser, no address but the
// project page, no inline styles, and a page without [data-emaki] is left
// alone. The behaviour tests run assets/emaki.js against a small fake DOM;
// no dependencies.
//   node --test tests/*.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const js = readFileSync(new URL('../assets/emaki.js', import.meta.url), 'utf8');
const css = readFileSync(new URL('../assets/emaki.css', import.meta.url), 'utf8');

test('stores nothing in the browser', () => {
  assert.doesNotMatch(js, /localStorage|sessionStorage|document\.cookie|indexedDB/);
});

test('names no address but the project page', () => {
  for (const source of [js, css]) {
    for (const match of source.matchAll(/https?:\/\/[^\s"'()]+/g)) {
      assert.ok(match[0].startsWith('https://github.com/sayamaapps'), match[0]);
    }
  }
});

test('sets no inline styles (CSP style-src self)', () => {
  assert.doesNotMatch(js, /\.style\.|setAttribute\(\s*['"]style['"]|\sstyle=/);
});

test('does nothing on a page without a gallery', () => {
  const created = [];
  const document = {
    querySelector: () => null,
    querySelectorAll: () => [],
    createElement: (tag) => { created.push(tag); return { addEventListener() {}, setAttribute() {}, querySelector: () => null, querySelectorAll: () => [] }; },
    body: { appendChild: (el) => created.push('append:' + el) },
    currentScript: { dataset: {} },
    addEventListener() {},
  };
  const window = { HTMLDialogElement: function () {}, matchMedia: () => ({ matches: false }), document };
  vm.runInNewContext(js, { window, document, CustomEvent: class {}, URL, Image: class {} });
  assert.deepEqual(created, []);
});

// --- A fake DOM, just enough for emaki.js ---------------------------------

const descendants = (node) => node.children.flatMap((child) => [child, ...descendants(child)]);

// One compound selector: `.class`, `tag` or `tag[attr]`.
const matchesCompound = (node, part) => {
  if (part.startsWith('.')) return node.classes.has(part.slice(1));
  const [tag, attr] = part.split(/[[\]]/); // `[attr]` has an empty tag part
  return (tag === '' || node.tagName === tag.toUpperCase()) && (attr === undefined || node.hasAttribute(attr));
};

// A selector of compounds joined by descendant combinators, e.g. `.emaki button`.
const matchesSelector = (node, selector) => {
  const parts = selector.trim().split(/\s+/);
  if (!matchesCompound(node, parts[parts.length - 1])) return false;
  let ancestor = node.parent;
  for (let i = parts.length - 2; i >= 0; i -= 1) {
    while (ancestor && !matchesCompound(ancestor, parts[i])) ancestor = ancestor.parent;
    if (!ancestor) return false;
    ancestor = ancestor.parent;
  }
  return true;
};

function element(tag, attrs = {}) {
  const node = {
    tagName: tag.toUpperCase(),
    attrs: { ...attrs },
    children: [],
    parent: null,
    classes: new Set(),
    listeners: {},
    textContent: '',
    hidden: false,
    open: false,
    focused: null,
    get className() { return [...this.classes].join(' '); },
    set className(value) { this.classes = new Set(String(value).split(/\s+/).filter(Boolean)); },
    getAttribute(name) { return name in this.attrs ? this.attrs[name] : null; },
    setAttribute(name, value) { this.attrs[name] = String(value); },
    hasAttribute(name) { return name in this.attrs; },
    removeAttribute(name) { delete this.attrs[name]; },
    appendChild(child) { child.parent = this; this.children.push(child); return child; },
    querySelectorAll(selector) { return descendants(this).filter((n) => matchesSelector(n, selector)); },
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; },
    closest(selector) {
      for (let n = this; n; n = n.parent) {
        if (selector.split(',').some((s) => matchesSelector(n, s))) return n;
      }
      return null;
    },
    addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); },
    dispatchEvent(event) {
      event.target ??= this;
      for (let n = this; n; n = event.bubbles ? n.parent : null) {
        event.currentTarget = n;
        (n.listeners[event.type] || []).forEach((fn) => fn(event));
      }
      return !event.defaultPrevented;
    },
    focus(options) { this.focused = options ?? {}; },
  };
  node.classList = {
    add: (c) => node.classes.add(c),
    remove: (c) => node.classes.delete(c),
    contains: (c) => node.classes.has(c),
  };
  return node;
}

// Runs emaki.js on a page. Each container is a list of links:
// { href, srcset?, sizes?, caption?, image?, alt? } where alt puts an <img> inside.
function page(containers, { labels = {}, reduced = false, noDialog = false, prepare = () => {} } = {}) {
  const created = [];
  const images = [];
  const appended = [];
  const body = element('body');
  body.appendChild = (child) => { appended.push(child); child.parent = body; return child; };
  class FakeImage { constructor() { images.push(this); } }
  class FakeEvent {
    constructor(type, init = {}) { this.type = type; this.bubbles = Boolean(init.bubbles); this.detail = init.detail; }
    preventDefault() { this.defaultPrevented = true; }
  }
  const boxes = containers.map((links) => {
    const box = element('div', { 'data-emaki': '' });
    links.forEach((l) => {
      const a = element('a', { href: l.href });
      if (l.srcset !== undefined) a.setAttribute('data-srcset', l.srcset);
      if (l.sizes !== undefined) a.setAttribute('data-sizes', l.sizes);
      if (l.caption !== undefined) a.setAttribute('data-emaki-caption', l.caption);
      if (l.image !== undefined) a.setAttribute('data-emaki-image', l.image);
      if (l.alt !== undefined) a.appendChild(element('img', { src: l.href, alt: l.alt }));
      box.appendChild(a);
    });
    return box;
  });
  const document = {
    currentScript: { dataset: labels },
    body,
    createElement(tag) {
      const node = element(tag);
      if (tag === 'dialog') {
        node.showModal = () => { node.open = true; };
        node.close = () => {
          if (node.open) { node.open = false; node.dispatchEvent({ type: 'close' }); }
        };
      }
      created.push(node);
      return node;
    },
    // Every container in document order, nested ones too.
    querySelectorAll: (selector) => (selector === '[data-emaki]'
      ? boxes.flatMap((box) => [box, ...descendants(box)]).filter((n) => n.hasAttribute('data-emaki'))
      : []),
  };
  const window = {
    ...(noDialog ? {} : { HTMLDialogElement: function HTMLDialogElement() {} }),
    matchMedia: () => ({ matches: reduced }),
  };
  prepare(boxes);
  vm.runInNewContext(js, { window, document, CustomEvent: FakeEvent, Image: FakeImage });
  const dialog = created.find((n) => n.tagName === 'DIALOG');
  return { boxes, dialog, created, appended, images };
}

// Page with one gallery, the first link (or the given one) clicked.
const opened = (links, i = 0, options) => {
  const result = page([links], options);
  click(result.boxes[0].children[i]);
  return result;
};

const click = (target, init = {}) => {
  const event = { type: 'click', button: 0, bubbles: true, ...init, preventDefault() { this.defaultPrevented = true; } };
  target.dispatchEvent(event);
  return event;
};

const key = (target, name) => target.dispatchEvent({
  type: 'keydown', key: name, bubbles: true, preventDefault() { this.defaultPrevented = true; },
});

const swipe = (target, [x1, y1], [x2, y2]) => {
  target.dispatchEvent({ type: 'touchstart', changedTouches: [{ clientX: x1, clientY: y1 }] });
  target.dispatchEvent({ type: 'touchend', changedTouches: [{ clientX: x2, clientY: y2 }] });
};

const at = (node, selector) => node.querySelector(selector);
// Copies a value made inside the vm realm, so deepEqual compares plain objects.
const plain = (value) => JSON.parse(JSON.stringify(value));
const counterText = (dialog) => at(dialog, '.emaki-counter').textContent;

const three = [
  { href: '/media/one-2400x1600.jpg', alt: 'One' },
  { href: '/media/two-2400x1600.jpg', alt: 'Two' },
  { href: '/media/three-2400x1600.jpg', alt: 'Three' },
];

// --- behaviour -------------------------------------------------------------

test('a click opens the dialog on that image, with its srcset and sizes', () => {
  const { boxes, dialog } = page([[
    { href: '/media/one-2400x1600.jpg', srcset: '/media/one-800x.jpg 800w', sizes: '90vw', alt: 'One' },
    { href: '/media/two-2400x1600.jpg', alt: 'Two' },
  ]]);
  const event = click(boxes[0].children[0]);
  assert.equal(event.defaultPrevented, true);
  assert.equal(dialog.open, true);
  const img = at(dialog, '.emaki-image');
  assert.equal(img.getAttribute('src'), '/media/one-2400x1600.jpg');
  assert.equal(img.getAttribute('srcset'), '/media/one-800x.jpg 800w');
  assert.equal(img.getAttribute('sizes'), '90vw');
  key(dialog, 'ArrowRight');
  assert.equal(img.getAttribute('src'), '/media/two-2400x1600.jpg');
  assert.equal(img.hasAttribute('srcset'), false, 'srcset removed for a link without one');
  assert.equal(img.getAttribute('sizes'), '100vw', 'default sizes');
});

test('caption: the data attribute wins over the tile alt', () => {
  const { dialog } = opened([{ href: '/media/a.jpg', caption: 'Our courtyard', alt: 'Courtyard tile' }]);
  const caption = at(dialog, '.emaki-caption');
  assert.equal(caption.textContent, 'Our courtyard');
  assert.equal(caption.hidden, false);
  assert.equal(at(dialog, '.emaki-image').getAttribute('alt'), '', 'alt empty while a caption is shown');
  assert.equal(dialog.getAttribute('aria-describedby'), 'emaki-caption');
});

test('caption: an empty data attribute means no caption, even with a tile alt', () => {
  const { dialog } = opened([{ href: '/media/a.jpg', caption: '', alt: 'Courtyard tile' }]);
  assert.equal(at(dialog, '.emaki-caption').hidden, true);
  assert.equal(at(dialog, '.emaki-image').getAttribute('alt'), 'Courtyard tile');
  assert.equal(dialog.hasAttribute('aria-describedby'), false);
});

test('caption: without the attribute, the tile alt is the caption', () => {
  const { dialog } = opened([{ href: '/media/a.jpg', alt: 'Courtyard tile' }]);
  assert.equal(at(dialog, '.emaki-caption').textContent, 'Courtyard tile');
  assert.equal(at(dialog, '.emaki-caption').hidden, false);
  assert.equal(at(dialog, '.emaki-image').getAttribute('alt'), '');
});

test('caption: no attribute and no tile alt means no caption; a caption that goes away drops aria-describedby', () => {
  const { dialog } = opened([{ href: '/media/a.jpg', caption: 'Shown' }, { href: '/media/b.jpg' }]);
  assert.equal(dialog.getAttribute('aria-describedby'), 'emaki-caption');
  key(dialog, 'ArrowRight');
  assert.equal(at(dialog, '.emaki-caption').hidden, true);
  assert.equal(at(dialog, '.emaki-image').getAttribute('alt'), '');
  assert.equal(dialog.hasAttribute('aria-describedby'), false);
});

test('labels: {n} and {count} are filled in, the given labels are used', () => {
  const { dialog } = opened(three, 1, {
    labels: { dialog: 'Bild {n} von {count}', counter: '{n} / {count}', close: 'Schließen', prev: 'Zurück', next: 'Weiter' },
  });
  assert.equal(dialog.getAttribute('aria-label'), 'Bild 2 von 3');
  assert.equal(counterText(dialog), '2 / 3');
  assert.equal(at(dialog, '.emaki-close').getAttribute('aria-label'), 'Schließen');
  assert.equal(at(dialog, '.emaki-prev').getAttribute('aria-label'), 'Zurück');
  assert.equal(at(dialog, '.emaki-next').getAttribute('aria-label'), 'Weiter');
});

test('labels: English when the script tag carries none', () => {
  const { dialog } = opened(three, 1);
  assert.equal(dialog.getAttribute('aria-label'), 'Image 2 of 3');
  assert.equal(counterText(dialog), '2 / 3');
  assert.equal(at(dialog, '.emaki-close').getAttribute('aria-label'), 'Close');
  assert.equal(at(dialog, '.emaki-prev').getAttribute('aria-label'), 'Previous image');
  assert.equal(at(dialog, '.emaki-next').getAttribute('aria-label'), 'Next image');
});

test('keys: arrows wrap around, Home and End go to the ends, other keys do nothing', () => {
  const { dialog } = opened(three);
  key(dialog, 'ArrowLeft');
  assert.equal(counterText(dialog), '3 / 3');
  key(dialog, 'ArrowRight');
  assert.equal(counterText(dialog), '1 / 3');
  assert.equal(at(dialog, '.emaki-image').getAttribute('src'), '/media/one-2400x1600.jpg');
  key(dialog, 'End');
  assert.equal(counterText(dialog), '3 / 3');
  key(dialog, 'ArrowRight');
  assert.equal(counterText(dialog), '1 / 3');
  key(dialog, 'End');
  key(dialog, 'Home');
  assert.equal(counterText(dialog), '1 / 3');
  key(dialog, 'ArrowRight');
  assert.equal(counterText(dialog), '2 / 3');
  key(dialog, 'a');
  assert.equal(counterText(dialog), '2 / 3');
});

test('one image: arrows and counter hidden, arrow keys show and fire nothing', () => {
  const { boxes, dialog } = opened([{ href: '/media/only.jpg', alt: 'Only' }]);
  assert.equal(at(dialog, '.emaki-prev').hidden, true);
  assert.equal(at(dialog, '.emaki-next').hidden, true);
  assert.equal(at(dialog, '.emaki-counter').hidden, true);
  let fired = 0;
  boxes[0].addEventListener('emaki:open', () => { fired += 1; });
  key(dialog, 'ArrowRight');
  key(dialog, 'ArrowLeft');
  assert.equal(fired, 0);
});

test('several images: arrows and counter visible', () => {
  const { dialog } = opened(three);
  assert.equal(at(dialog, '.emaki-prev').hidden, false);
  assert.equal(at(dialog, '.emaki-next').hidden, false);
  assert.equal(at(dialog, '.emaki-counter').hidden, false);
});

test('emaki:open fires on the link for each image shown, with href, image, index and count', () => {
  const { boxes, dialog } = page([[
    { href: '/media/one.jpg', image: 'courtyard', alt: 'One' },
    { href: '/media/two.jpg', alt: 'Two' },
  ]]);
  const seen = [];
  boxes[0].addEventListener('emaki:open', (e) => seen.push({ target: e.target, detail: plain(e.detail) }));
  click(boxes[0].children[0]);
  key(dialog, 'ArrowRight');
  key(dialog, 'ArrowRight');
  assert.deepEqual(seen.map((s) => s.detail), [
    { href: '/media/one.jpg', image: 'courtyard', index: 0, count: 2 },
    { href: '/media/two.jpg', image: null, index: 1, count: 2 },
    { href: '/media/one.jpg', image: 'courtyard', index: 0, count: 2 },
  ]);
  assert.equal(seen[0].target, boxes[0].children[0], 'fired on the link');
});

test('close returns focus to the clicked link and drops the picture', () => {
  const { boxes, dialog } = opened(three, 2);
  const img = at(dialog, '.emaki-image');
  dialog.close();
  assert.equal(dialog.open, false);
  assert.deepEqual(plain(boxes[0].children[2].focused), { preventScroll: true });
  assert.equal(img.hasAttribute('src'), false);
  assert.equal(img.hasAttribute('srcset'), false);
});

test('the close button and a click beside the image close; the image, caption and buttons do not', () => {
  const { dialog } = opened(three);
  click(at(dialog, '.emaki-image'));
  assert.equal(dialog.open, true);
  click(at(dialog, '.emaki-caption'));
  assert.equal(dialog.open, true);
  click(at(dialog, '.emaki-next'));
  assert.equal(dialog.open, true);
  assert.equal(counterText(dialog), '2 / 3');
  click(at(dialog, '.emaki-stage'));
  assert.equal(dialog.open, false, 'click beside the image closes');
  const again = opened(three).dialog;
  click(at(again, '.emaki-close'));
  assert.equal(again.open, false, 'close button closes');
});

test('modified, middle and right clicks are left to the browser', () => {
  for (const init of [{ button: 1 }, { button: 2 }, { ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }]) {
    const { boxes, dialog } = page([three]);
    const event = click(boxes[0].children[0], init);
    assert.ok(!event.defaultPrevented, JSON.stringify(init));
    assert.equal(dialog.open, false, JSON.stringify(init));
  }
});

test('a click another script has already prevented is left alone', () => {
  const { boxes, dialog } = page([three], {
    prepare: (containers) => containers[0].children[0].addEventListener('click', (e) => e.preventDefault()),
  });
  click(boxes[0].children[0]);
  assert.equal(dialog.open, false);
});

test('links that are not images stay out of the sequence', () => {
  const { boxes, dialog } = page([[
    { href: '/media/one.jpg', alt: 'One' },
    { href: '/downloads/album.zip' },
    { href: '/media/two.jpg?v=2', alt: 'Two' },
  ]]);
  const download = click(boxes[0].children[1]);
  assert.ok(!download.defaultPrevented, 'download link left alone');
  assert.equal(dialog.open, false);
  click(boxes[0].children[2]);
  assert.equal(counterText(dialog), '2 / 2');
});

test('a link with data-srcset takes part even without an image extension', () => {
  const { boxes, dialog } = page([[
    { href: '/media/photo', srcset: '/media/photo-800x.jpg 800w' },
    { href: '/media/two.jpg', alt: 'Two' },
  ]]);
  click(boxes[0].children[0]);
  assert.equal(dialog.open, true);
  assert.equal(counterText(dialog), '1 / 2');
});

test('several containers are several sequences', () => {
  const { boxes, dialog } = page([
    [{ href: '/media/a1.jpg', alt: 'A1' }, { href: '/media/a2.jpg', alt: 'A2' }],
    [{ href: '/media/b1.jpg', alt: 'B1' }, { href: '/media/b2.jpg', alt: 'B2' }, { href: '/media/b3.jpg', alt: 'B3' }],
  ]);
  click(boxes[1].children[2]);
  assert.equal(counterText(dialog), '3 / 3');
  key(dialog, 'ArrowRight');
  assert.equal(at(dialog, '.emaki-image').getAttribute('src'), '/media/b1.jpg');
});

test('the two neighbours are preloaded', () => {
  const { images } = opened(three, 1);
  assert.deepEqual(images.map((i) => i.src).sort(), ['/media/one-2400x1600.jpg', '/media/three-2400x1600.jpg']);
});

test('the motion class is set unless the reader asks for reduced motion', () => {
  assert.equal(opened(three).dialog.classList.contains('emaki--motion'), true);
  assert.equal(opened(three, 0, { reduced: true }).dialog.classList.contains('emaki--motion'), false);
});

test('is-loading lasts until the image loads or fails', () => {
  const { dialog } = opened(three);
  const img = at(dialog, '.emaki-image');
  assert.equal(dialog.classList.contains('is-loading'), true);
  img.dispatchEvent({ type: 'load' });
  assert.equal(dialog.classList.contains('is-loading'), false);
  key(dialog, 'ArrowRight');
  assert.equal(dialog.classList.contains('is-loading'), true);
  img.dispatchEvent({ type: 'error' });
  assert.equal(dialog.classList.contains('is-loading'), false);
});

test('swipe: sideways from 45 px on; a short or vertical move changes nothing', () => {
  const { dialog } = opened(three, 1);
  assert.equal(counterText(dialog), '2 / 3');
  swipe(dialog, [200, 100], [140, 104]);
  assert.equal(counterText(dialog), '3 / 3', 'sideways 60 px: next');
  swipe(dialog, [200, 100], [240, 100]);
  assert.equal(counterText(dialog), '3 / 3', '40 px: too short');
  swipe(dialog, [200, 100], [210, 300]);
  assert.equal(counterText(dialog), '3 / 3', 'vertical');
  swipe(dialog, [100, 100], [160, 100]);
  assert.equal(counterText(dialog), '2 / 3', 'sideways to the right: previous');
});

test('no element is created on a page without a gallery, or with links that are not images', () => {
  const none = page([]);
  assert.equal(none.created.length, 0);
  assert.equal(none.appended.length, 0);
  const downloads = page([[{ href: '/downloads/album.zip' }]]);
  assert.equal(downloads.created.length, 0);
  assert.equal(downloads.appended.length, 0);
});

test('the dialog is built once and appended once, with no style attribute anywhere', () => {
  const { created, appended, boxes, dialog } = opened(three);
  dialog.close();
  click(boxes[0].children[2]);
  assert.equal(created.filter((n) => n.tagName === 'DIALOG').length, 1);
  assert.deepEqual(appended, [dialog]);
  assert.equal(created.some((n) => n.hasAttribute('style')), false);
});

test('nested containers: a link belongs to the nearest container, each sequence has its own links', () => {
  const { boxes, dialog } = page([
    [{ href: '/media/outer-1.jpg', alt: 'O1' }, { href: '/media/outer-2.jpg', alt: 'O2' }],
  ], {
    prepare: ([outer]) => {
      // An inner container as the last child of the outer one, with two links.
      const inner = element('div', { 'data-emaki': '' });
      ['/media/inner-1.jpg', '/media/inner-2.jpg'].forEach((href) => {
        const a = element('a', { href });
        a.appendChild(element('img', { src: href, alt: href }));
        inner.appendChild(a);
      });
      outer.appendChild(inner);
    },
  });
  const [outer] = boxes;
  click(outer.children[2].children[0]);
  assert.equal(counterText(dialog), '1 / 2', 'the inner sequence has two images, not four');
  assert.equal(at(dialog, '.emaki-image').getAttribute('src'), '/media/inner-1.jpg');
  key(dialog, 'ArrowRight');
  assert.equal(at(dialog, '.emaki-image').getAttribute('src'), '/media/inner-2.jpg');
  dialog.close();
  click(outer.children[1]);
  assert.equal(counterText(dialog), '2 / 2', 'the outer sequence has its own two links');
  key(dialog, 'ArrowLeft');
  assert.equal(at(dialog, '.emaki-image').getAttribute('src'), '/media/outer-1.jpg');
});

test('without HTMLDialogElement a page with a gallery gets no element and no listener', () => {
  const { boxes, created, appended } = page([three], { noDialog: true });
  assert.equal(created.length, 0);
  assert.equal(appended.length, 0);
  assert.deepEqual(Object.keys(boxes[0].listeners), []);
  for (const link of boxes[0].children) assert.deepEqual(Object.keys(link.listeners), []);
});
