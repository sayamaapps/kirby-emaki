# Emaki: privacy notes

This is an English summary of [DATENSCHUTZ.md](DATENSCHUTZ.md), the German
text block for privacy policies, which is the authoritative version. Both
describe the implementation. They are not legal advice.

- The viewer is a script and a stylesheet **served from the site's own
  server** (`/media/plugins/sayamaapps/emaki/…`).
- Emaki loads only what the site's markup links: the image that is opened,
  and its two neighbours, which are fetched in advance. If a page links an
  image on a foreign host, the browser loads it from there when the image is
  opened or fetched in advance. Images of the site's own gallery, in a
  rendered size, stay on the site's own server.
- **No cookies, nothing stored in the browser** (no local storage, no session
  storage, no IndexedDB).
- **No data is sent anywhere.** Emaki does not count. It fires a browser event
  `emaki:open` that a site *may* use for its own counting; what such a count
  records is described by that tool's privacy notes (for example Kizami), not
  by this one.
- Emaki honours the operating system's "reduce motion" setting and reads no
  other device property.

Usually no separate paragraph in a privacy policy is needed. A possible
sentence: "To enlarge images we use an image viewer that is loaded entirely
from our own server. It sets no cookies, stores nothing in your browser and
sends no data to third parties as long as the images are on our own server."
