/**
 * Emaki — image viewer for galleries. No library, no request to any server,
 * nothing stored in the browser. Behaviour: SPEC.md §4.
 *
 * Each container with `data-emaki` is one sequence. Every link inside it that
 * points at an image file (or has data-srcset) takes part; other links are
 * left alone. A link in a container nested in another one belongs to the
 * inner one. Without JavaScript the link opens the file as usual.
 *
 * The <dialog> is built once, when there is something to show, and appended
 * to <body>. Labels come from this script tag's data attributes (dialog,
 * close, prev, next, counter), with {n} and {count} as placeholders; English
 * is the fallback.
 *
 * Each image shown fires `emaki:open` on the link that holds it (bubbles):
 * detail { href, image, index, count }. Nothing else leaves the page; what
 * the site does with the event is the site's business.
 */
(() => {
  'use strict';

  const containers = document.querySelectorAll('[data-emaki]');
  if (!containers.length || !window.HTMLDialogElement) return;

  // Labels: the script tag carries them (see emaki_tags()). An empty value
  // counts as not given.
  const given = (document.currentScript && document.currentScript.dataset) || {};
  const label = (key, fallback) => given[key] || fallback;
  const words = {
    dialog: label('dialog', 'Image {n} of {count}'),
    counter: label('counter', '{n} / {count}'),
    close: label('close', 'Close'),
    prev: label('prev', 'Previous image'),
    next: label('next', 'Next image'),
  };
  const fill = (s, n, count) => s.replace(/\{n\}/g, () => String(n)).replace(/\{count\}/g, () => String(count));

  // One sequence per container: its links to images (or with a srcset), in document order.
  // A link belongs to the nearest container around it, so an inner container's links
  // are not counted a second time for the outer one.
  const isImage = (a) => /\.(jpe?g|png|gif|webp|avif|svg)(\?|$)/i.test(a.getAttribute('href')) || a.getAttribute('data-srcset') !== null;
  const sequences = [];
  containers.forEach((box) => {
    const links = [...box.querySelectorAll('a[href]')].filter((a) => a.closest('[data-emaki]') === box && isImage(a));
    if (links.length) sequences.push(links);
  });
  if (!sequences.length) return;

  // The dialog, built once. Its markup lives here, not in the page.
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const make = (tag, className, attrs) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    Object.keys(attrs || {}).forEach((name) => node.setAttribute(name, attrs[name]));
    return node;
  };
  const dialog = make('dialog', 'emaki');
  if (!reduced) dialog.classList.add('emaki--motion');
  const figure = make('figure', 'emaki-stage');
  const img = make('img', 'emaki-image', { alt: '' });
  const caption = make('figcaption', 'emaki-caption', { id: 'emaki-caption' });
  const close = make('button', 'emaki-close', { type: 'button', 'aria-label': words.close });
  const prev = make('button', 'emaki-prev', { type: 'button', 'data-step': '-1', 'aria-label': words.prev });
  const next = make('button', 'emaki-next', { type: 'button', 'data-step': '1', 'aria-label': words.next });
  const counter = make('p', 'emaki-counter', { 'aria-live': 'polite' });
  close.textContent = '×';
  prev.textContent = '‹';
  next.textContent = '›';
  figure.appendChild(img);
  figure.appendChild(caption);
  [figure, close, prev, next, counter].forEach((node) => dialog.appendChild(node));
  document.body.appendChild(dialog);

  const loaded = () => dialog.classList.remove('is-loading');
  img.addEventListener('load', loaded);
  img.addEventListener('error', loaded);

  let seq = [];       // the sequence on show
  let index = 0;      // position in seq, 0-based
  let origin = null;  // the link that opened the dialog

  // Show image i of the sequence.
  const show = (i) => {
    const count = seq.length;
    const link = seq[i];
    index = i;
    const tile = link.querySelector('img');
    const tileAlt = tile ? tile.getAttribute('alt') || '' : '';
    const own = link.getAttribute('data-emaki-caption');
    const legend = own !== null ? own : tileAlt; // an empty attribute wins: no caption

    dialog.classList.add('is-loading');
    if (link.getAttribute('data-srcset') === null) img.removeAttribute('srcset');
    else img.setAttribute('srcset', link.getAttribute('data-srcset'));
    img.setAttribute('sizes', link.getAttribute('data-sizes') || '100vw');
    img.setAttribute('src', link.getAttribute('href'));
    img.setAttribute('alt', legend !== '' ? '' : tileAlt);
    caption.textContent = legend;
    caption.hidden = legend === '';
    if (legend !== '') dialog.setAttribute('aria-describedby', 'emaki-caption');
    else dialog.removeAttribute('aria-describedby');
    dialog.setAttribute('aria-label', fill(words.dialog, i + 1, count));
    counter.textContent = fill(words.counter, i + 1, count);
    const single = count < 2;
    counter.hidden = single;
    prev.hidden = single;
    next.hidden = single;

    // The neighbours are fetched now, so stepping does not wait on the network.
    [1, -1].forEach((step) => {
      const near = seq[(i + step + count) % count];
      if (near !== link) new Image().src = near.getAttribute('href');
    });

    link.dispatchEvent(new CustomEvent('emaki:open', {
      bubbles: true,
      detail: { href: link.getAttribute('href'), image: link.getAttribute('data-emaki-image'), index: i, count },
    }));
  };

  // Move within the sequence. It wraps around; a step that changes nothing
  // (a single image, Home on the first) shows and fires nothing.
  const go = (to) => {
    const count = seq.length;
    const target = (to + count) % count;
    if (target !== index) show(target);
  };

  const open = (links, i, from) => {
    seq = links;
    origin = from;
    show(i);
    dialog.showModal();
  };

  sequences.forEach((links) => {
    links.forEach((a, i) => {
      a.addEventListener('click', (e) => {
        // Modified and middle clicks belong to the browser: a new tab stays a new tab.
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        open(links, i, a);
      });
    });
  });

  [prev, next].forEach((button) => {
    button.addEventListener('click', () => go(index + Number(button.getAttribute('data-step'))));
  });
  close.addEventListener('click', () => dialog.close());

  // The stage fills the dialog, so a click that lands on it is a click beside
  // the image: it closes, unless it hit the image, the caption or a button.
  dialog.addEventListener('click', (e) => {
    if (!e.target.closest('.emaki-image, .emaki-caption, .emaki button')) dialog.close();
  });

  dialog.addEventListener('keydown', (e) => {
    let to;
    if (e.key === 'ArrowLeft') to = index - 1;
    else if (e.key === 'ArrowRight') to = index + 1;
    else if (e.key === 'Home') to = 0;
    else if (e.key === 'End') to = seq.length - 1;
    else return;
    e.preventDefault();
    go(to);
  });

  // Swipe: sideways only, from 45 px on, so a loose tap changes nothing.
  let startX = 0;
  let startY = 0;
  dialog.addEventListener('touchstart', (e) => {
    startX = e.changedTouches[0].clientX;
    startY = e.changedTouches[0].clientY;
  }, { passive: true });
  dialog.addEventListener('touchend', (e) => {
    const dx = e.changedTouches[0].clientX - startX;
    const dy = e.changedTouches[0].clientY - startY;
    if (Math.abs(dx) >= 45 && Math.abs(dx) > Math.abs(dy)) go(index + (dx < 0 ? 1 : -1));
  }, { passive: true });

  // Closing, however it happened (button, click beside, Escape): focus goes
  // back to the link that was clicked, and the picture is dropped so the next
  // opening does not flash the previous one.
  dialog.addEventListener('close', () => {
    const from = origin;
    origin = null;
    dialog.classList.remove('is-loading');
    img.removeAttribute('src');
    img.removeAttribute('srcset');
    if (from) from.focus({ preventScroll: true });
  });
})();
