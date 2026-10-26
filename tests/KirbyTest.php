<?php

/**
 * The plugin with real Kirby, in a throwaway site directory: real GD images
 * (JPEG, PNG, WebP), a JPEG turned by EXIF orientation 6 (a minimal APP1
 * block), an SVG and a PDF. Checks the attributes of SPEC §2.3 and the markup
 * of §3.2 (`emaki/gallery`, `$attrs`, captions, escaping), the `emaki`
 * snippet and its labels, that no output carries inline style or script, and
 * that no original file changed.
 *
 * The plugin reaches the site through site/plugins, the way Kirby loads it.
 */

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Template\Snippet;
use PHPUnit\Framework\Attributes\CoversNothing;
use Sayamaapps\Emaki\Images;

#[CoversNothing]
final class KirbyTest extends PluginTestCase
{
	/** The throwaway site of the current test. */
	private string $site;

	/** @var array<string,string> SHA-256 of every original, taken after it was written. */
	private array $originals = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->site = sys_get_temp_dir() . '/emaki-kirby-' . bin2hex(random_bytes(6));
		foreach (['public', 'content/home', 'site/plugins', 'storage'] as $dir) {
			mkdir($this->site . '/' . $dir, 0700, true);
		}
		// The plugin as Kirby loads it: through site/plugins.
		symlink(dirname(__DIR__), $this->site . '/site/plugins/emaki');
		file_put_contents($this->home() . 'default.txt', "Title: Home\n");

		$this->writeOriginals();
	}

	protected function tearDown(): void
	{
		try {
			$this->assertOriginalsUnchanged();
		} finally {
			// Dir::remove() unlinks the plugin's symlink without touching the repository behind it.
			Dir::remove($this->site);
			parent::tearDown();
		}
	}

	public function testAttributesOfAWideJpeg(): void
	{
		$kirby = $this->app(['emaki.active' => true]);
		$wide  = $this->file($kirby, 'wide.jpg');
		$a     = Images::attributes($wide);

		expect(['href', 'data-srcset', 'data-sizes', 'src', 'srcset', 'sizes', 'width', 'height', 'alt', 'loading', 'decoding'], array_keys($a), 'every key of §2.3, in this order');
		holds(str_starts_with($a['href'], 'https://site.example/media/'), 'href is an address of the site');
		holds($a['href'] !== $wide->url(), 'href is a rendered variant, never the original');
		expect(1, preg_match('~/wide-2400x\.jpg$~', $a['href']), 'href: the largest large variant (2400 px)');
		expect([800, 1200, 1800, 2400], self::srcsetWidths($a['data-srcset']), 'data-srcset: the large widths');

		preg_match_all('~(\S+) (\d+)w~', $a['data-srcset'], $pairs, PREG_SET_ORDER);
		foreach ($pairs as $pair) {
			expect((int)$pair[2], getimagesize($this->onDisk($pair[1]))[0], "data-srcset: the file on disk is {$pair[2]} px wide");
		}

		expect([320, 480, 720, 960], self::srcsetWidths($a['srcset']), 'srcset: the tile widths');
		expect(1, preg_match('~-720x\.jpg$~', $a['src']), 'src: the variant at 720 px (smallest at or above 720)');
		expect(720, $a['width'], 'width of the tile src');
		expect(480, $a['height'], 'height of the tile src');
		expect(720, getimagesize($this->onDisk($a['src']))[0], 'src: the file on disk is 720 px wide');
		expect('(max-width: 40rem) 100vw, 33vw', $a['sizes'], 'sizes: the tile default');
		expect('100vw', $a['data-sizes'], 'data-sizes: the large-view default');
		expect('Our courtyard', $a['alt'], 'alt from the file');
		expect('lazy', $a['loading'], 'loading');
		expect('async', $a['decoding'], 'decoding');
		expect('50vw', Images::attributes($wide, ['sizes' => '50vw'])['sizes'], 'per call: tile sizes');
		expect('100vw', Images::attributes($wide, ['sizes' => '50vw'])['data-sizes'], 'per call sizes leave data-sizes alone');
		expect('', Images::attributes($this->file($kirby, 'flat.png'))['alt'], 'no alt in the file: empty string');
	}

	public function testFileMethodAndFunctionGiveTheSameAttributes(): void
	{
		$kirby = $this->app(['emaki.active' => true]);
		$wide  = $this->file($kirby, 'wide.jpg');
		$a     = Images::attributes($wide);

		expect($a, $wide->emaki(), 'the file method $file->emaki() gives the same attributes');
		expect($a, emaki_attributes($wide), 'emaki_attributes() gives the same attributes');
	}

	public function testSizesAndFormatOptions(): void
	{
		$kirby = $this->app(['emaki.active' => true, 'emaki.sizes' => '80vw']);
		expect('80vw', Images::attributes($this->file($kirby, 'wide.jpg'))['data-sizes'], 'emaki.sizes sets data-sizes');

		$kirby      = $this->app(['emaki.active' => true, 'emaki.format' => 'webp']);
		$fromOption = Images::attributes($this->file($kirby, 'flat.png'));
		expect(1, preg_match('~\.webp$~', $fromOption['href']), 'emaki.format: href is webp');
		expect(IMAGETYPE_WEBP, getimagesize($this->onDisk($fromOption['href']))[2], 'emaki.format: the file on disk is webp');

		$kirby   = $this->app(['emaki.active' => true]);
		$perCall = Images::attributes($this->file($kirby, 'flat.png'), ['format' => 'webp']);
		expect(1, preg_match('~\.webp$~', $perCall['href']), 'format per call: href is webp');
		expect(1, preg_match('~\.webp$~', $perCall['src']), 'format per call: src is webp');
		expect(1, preg_match('~\.png$~', Images::attributes($this->file($kirby, 'flat.png'))['href']), 'no format: the file keeps its own format');

		// format null per call: `emaki.format` applies; with that unset too, the site's `thumbs.format`.
		$kirby = $this->app(['emaki.active' => true, 'emaki.format' => 'webp']);
		expect(1, preg_match('~\.webp$~', Images::attributes($this->file($kirby, 'flat.png'), ['format' => null])['href']), 'format null per call: emaki.format applies');

		$kirby = $this->app(['emaki.active' => true, 'thumbs' => ['format' => 'jpg']]);
		expect(1, preg_match('~\.jpg$~', Images::attributes($this->file($kirby, 'flat.png'), ['format' => null])['href']), 'format null, emaki.format unset: thumbs.format of the site applies');
	}

	public function testListsAndPresetsOfTheSite(): void
	{
		$kirby  = $this->app(['emaki.active' => true]);
		$wide   = $this->file($kirby, 'wide.jpg');
		$custom = Images::attributes($wide, ['large' => [600, 1200], 'tile' => [200, 400]]);
		expect([600, 1200], self::srcsetWidths($custom['data-srcset']), 'large as a list');
		expect([200, 400], self::srcsetWidths($custom['srcset']), 'tile as a list');
		expect(400, $custom['width'], 'tile list without 720: the largest');

		// A preset of the site's `thumbs.srcsets`, and a name that does not exist.
		$kirby = $this->app(['emaki.active' => true, 'emaki.srcset' => 'gallery', 'thumbs' => ['srcsets' => ['gallery' => [500, 1000]]]]);
		expect([500, 1000], self::srcsetWidths(Images::attributes($this->file($kirby, 'wide.jpg'))['data-srcset']), 'emaki.srcset names a preset of the site');

		$kirby = $this->app(['emaki.active' => true, 'emaki.srcset' => 'nope']);
		expect([800, 1200, 1800, 2400], self::srcsetWidths(Images::attributes($this->file($kirby, 'wide.jpg'))['data-srcset']), 'missing preset: the fallback list');
	}

	public function testRotatedJpegIsUpright(): void
	{
		$kirby  = $this->app(['emaki.active' => true]);
		$turned = $this->file($kirby, 'turned.jpg');
		expect(1000, $turned->width(), 'EXIF orientation 6: width of the oriented file');
		expect(1600, $turned->height(), 'EXIF orientation 6: height of the oriented file');

		$t = Images::attributes($turned);
		expect(1, preg_match('~-1000x\.jpg$~', $t['href']), 'oriented: href at the oriented width');
		expect([1000, 1600], array_slice(getimagesize($this->onDisk($t['href'])), 0, 2), 'oriented: the large variant on disk is upright');
		expect(720, $t['width'], 'oriented: tile width');
		expect(1152, $t['height'], 'oriented: tile height is portrait');
		expect([720, 1152], array_slice(getimagesize($this->onDisk($t['src'])), 0, 2), 'oriented: the tile on disk is upright');
	}

	public function testPngWebpSmallSvgAndPdf(): void
	{
		$kirby = $this->app(['emaki.active' => true]);

		$flat = Images::attributes($this->file($kirby, 'flat.png'));
		expect(1, preg_match('~-1200x\.png$~', $flat['href']), 'PNG: href at its own width');

		$modern = Images::attributes($this->file($kirby, 'modern.webp'));
		expect(1, preg_match('~-1000x\.webp$~', $modern['href']), 'WebP: href at its own width');
		expect(1, preg_match('~-720x\.webp$~', $modern['src']), 'WebP: tile at 720 px');

		$small = Images::attributes($this->file($kirby, 'small.png'));
		expect([300], self::srcsetWidths($small['data-srcset']), 'no upscaling: a 300 px file has one large variant');
		expect([300], self::srcsetWidths($small['srcset']), 'no upscaling: a 300 px file has one tile variant');
		expect(300, $small['width'], 'no upscaling: src is 300 px');
		expect([300, 200], array_slice(getimagesize($this->onDisk($small['href'])), 0, 2), 'no upscaling: the variant on disk is 300 px');

		$svg = Images::attributes($this->file($kirby, 'logo.svg'));
		expect($this->file($kirby, 'logo.svg')->url(), $svg['href'], 'SVG: href is the file itself');
		expect($svg['href'], $svg['src'], 'SVG: src is the file itself');
		expect(null, $svg['data-srcset'], 'SVG: no large srcset');
		expect(null, $svg['srcset'], 'SVG: no tile srcset');
		expect(200, $svg['width'], 'SVG: its own width');
		expect(100, $svg['height'], 'SVG: its own height');

		$pdf = Images::attributes($this->file($kirby, 'notes.pdf'));
		expect($this->file($kirby, 'notes.pdf')->url(), $pdf['href'], 'PDF: href is the file itself');
		expect([null, null], [$pdf['width'], $pdf['height']], 'PDF: no size');
		expect(null, $pdf['srcset'], 'PDF: no srcset');
	}

	public function testGalleryMarkupWhileActiveAndInactive(): void
	{
		$kirby = $this->app(['emaki.active' => true]);
		$html  = snippet('emaki/gallery', ['files' => $kirby->page('home')->files()], true);

		expect(1, preg_match('~^<div class="emaki-gallery" data-emaki>\n~', $html), 'container with data-emaki while active');
		expect(7, substr_count($html, '<a class="emaki-item"'), 'one tile per image; the PDF is skipped');
		holds(!str_contains($html, 'notes.pdf'), 'the PDF is not in the markup');
		expect(7, substr_count($html, 'loading="lazy" decoding="async"'), 'every <img> lazy and async');

		$tile = '~<a class="emaki-item" href="[^"]+-2400x\.jpg" data-srcset="[^"]+" data-sizes="100vw" data-emaki-caption="Our courtyard">\n'
			. '    <img src="[^"]+" srcset="[^"]+" sizes="\(max-width: 40rem\) 100vw, 33vw" width="720" height="480" alt="Our courtyard" loading="lazy" decoding="async">\n'
			. '  </a>\n~';
		expect(1, preg_match($tile, $html), 'tile markup of §3.2');

		$inactive = $this->app([]);
		$offHtml  = snippet('emaki/gallery', ['files' => $inactive->page('home')->files()], true);
		expect(str_replace(' data-emaki>', '>', $html), $offHtml, 'inactive: the same markup without data-emaki');
		holds(preg_match('~<div[^>]* data-emaki[\s>]~', $offHtml) === 0, 'inactive: no data-emaki on the container');
	}

	public function testGalleryContainerOptionsAndAttrs(): void
	{
		$kirby = $this->app(['emaki.active' => true]);
		$page  = $kirby->page('home');

		$opt = snippet('emaki/gallery', [
			'files' => $page->files(), 'class' => 'row extra', 'tag' => 'section', 'sizes' => '50vw',
			'image' => 'slug', 'caption' => 'caption',
			'attrs' => ['id' => 'g1', 'data-track' => 'hero', 'aria-label' => 'Gallery', 'onclick' => 'evil()', 'style' => 'color:red', 'class' => 'spoof', 'data-emaki' => 'spoof', 'role' => 'list'],
		], true);
		holds(preg_match('~^<section class="emaki-gallery row extra" data-emaki id="g1" data-track="hero" aria-label="Gallery">\n~', $opt) === 1, 'class, tag and $attrs: container tag');
		holds(preg_match('~onclick|style=|spoof|role=~', $opt) === 0, '$attrs: other attributes dropped');
		expect(1, preg_match_all('~ data-emaki(?=[\s>])~', $opt), 'exactly one data-emaki, on the container');
		expect(1, preg_match('~data-emaki-image="courtyard"~', $opt), '$image: the slug field becomes data-emaki-image');
		expect(1, preg_match('~data-emaki-caption="Courtyard caption"~', $opt), '$caption: the named field');
		holds(preg_match('~data-emaki-caption=""~', $opt) === 1, 'a named field that is empty: empty caption');
		holds(preg_match('~data-emaki-image=""~', $opt) === 0, 'no empty data-emaki-image');
		expect(1, preg_match('~<img src="[^"]+" srcset="[^"]+" sizes="50vw"~', $opt), '$sizes: sizes of the tile');

		$none = snippet('emaki/gallery', ['files' => $page->files(), 'caption' => false], true);
		expect(7, substr_count($none, 'data-emaki-caption=""'), 'caption false: every tile has an empty caption, so no caption shows');

		$hostile = snippet('emaki/gallery', ['files' => [$this->file($kirby, 'hostile.png')]], true);
		holds(str_contains($hostile, 'data-emaki-caption="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"'), 'hostile alt escaped in the caption');
		holds(str_contains($hostile, 'alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"'), 'hostile alt escaped in the img');
		holds(!str_contains($hostile, '<script'), 'hostile alt: no script element');

		$tagInvalid = snippet('emaki/gallery', ['files' => [$this->file($kirby, 'flat.png')], 'tag' => 'div onclick="x"'], true);
		holds(preg_match('~^<div class="emaki-gallery" data-emaki>~', $tagInvalid) === 1, 'an invalid tag name falls back to div');

		expect('', trim(snippet('emaki/gallery', ['files' => []], true)), 'empty files: nothing');
		expect('', trim(snippet('emaki/gallery', ['files' => [$page->file('notes.pdf')]], true)), 'only non-images: nothing');
	}

	public function testTagsArePrintedOnceAndLabelsAreEnglish(): void
	{
		$kirby = $this->app(['emaki.active' => true]);
		$tags  = snippet('emaki', [], true);

		expect(1, substr_count($tags, '<link rel="stylesheet"'), 'one stylesheet');
		expect(1, substr_count($tags, '<script src='), 'one script');
		holds(str_contains($tags, ' defer'), 'script deferred');
		holds(str_contains($tags, 'data-dialog="Image {n} of {count}"'), 'English labels by default');
		expect('', trim(snippet('emaki', [], true)), 'the tags are printed once per page');
		holds(preg_match('~^https?://[^/]+/media/plugins/sayamaapps/emaki/[^/]+-\d+/emaki\.js$~', (string)emaki_asset_url('emaki.js')) === 1, 'asset URL: /media/plugins/sayamaapps/emaki/<hash>-<time>/emaki.js on the site, no query string');
	}

	public function testSwitchedOffPrintsNothing(): void
	{
		$this->app(['emaki.active' => false]);
		expect('', trim(snippet('emaki', [], true)), 'emaki off: nothing');
	}

	public function testTextsAndLanguageReachTheScriptTag(): void
	{
		$this->app(['emaki.active' => true, 'emaki.texts' => ['button.close' => 'Zumachen']]);
		holds(str_contains(snippet('emaki', [], true), 'data-close="Zumachen"'), 'emaki.texts reaches the script tag');

		$this->app(['emaki.active' => true, 'emaki.language' => 'de']);
		$german = snippet('emaki', [], true);
		holds(str_contains($german, 'data-close="Schließen"') && str_contains($german, 'data-dialog="Bild {n} von {count}"'), 'German labels in the script tag');
	}

	public function testStyleOptionOmitsTheStylesheet(): void
	{
		$this->app(['emaki.active' => true, 'emaki.style' => false]);
		holds(!str_contains(snippet('emaki', [], true), '<link'), 'emaki.style false: no stylesheet tag');
	}

	public function testQueuedThumbInUnwritableFolderGivesNull(): void
	{
		// A thumb that is queued (its job file exists) but whose folder is not writable: it cannot be
		// saved, so there are no attributes and no exception. The folder is checked before the save.
		$kirby       = $this->app(['emaki.active' => true]);
		$queuedFile  = $this->file($kirby, 'flat.png');
		$queuedThumb = $queuedFile->thumb(['width' => 444, 'autoOrient' => true]);
		$thumbDir    = dirname($queuedThumb->root());
		holds(!is_file($queuedThumb->root()), 'precondition: the 444 px thumb does not exist yet');
		holds(is_file($thumbDir . '/.jobs/' . basename($queuedThumb->root()) . '.json'), 'precondition: the thumb is queued (job file exists)');

		chmod($thumbDir, 0555);
		try {
			holds(!is_writable($thumbDir), 'precondition: the thumb folder is read-only (not running as root)');
			expect(null, Images::attributes($queuedFile, ['large' => [444], 'tile' => [444]]), 'queued thumb, folder not writable: attributes are null, no error');
		} finally {
			chmod($thumbDir, 0755);
		}
	}

	public function testUnwritableMediaFolderLeavesImagesOut(): void
	{
		// A page's media folder that cannot be written: Kirby hands back the original for the thumb,
		// so there are no attributes, and nothing that links the original. (The new file's folder is
		// created inside media/pages/home, so that folder is the one that is made read-only.)
		$unwritable = $this->home() . 'unwritable.png';
		self::makeImage($unwritable, 1000, 700);
		file_put_contents($this->home() . 'unwritable.png.txt', "Alt: Unwritable\n");
		$this->originals[$unwritable] = hash_file('sha256', $unwritable);

		$kirby     = $this->app(['emaki.active' => true]);
		$pageMedia = $this->site . '/public/media/pages/home';
		mkdir($pageMedia, 0700, true);
		// flat.png makes its own folder and thumbs first, so only the folder of the new file is refused.
		Images::attributes($this->file($kirby, 'flat.png'));
		chmod($pageMedia, 0555);
		try {
			holds(!is_writable($pageMedia), 'precondition: the page media folder is read-only (not running as root)');

			$noThumb = $this->file($kirby, 'unwritable.png');
			expect(null, Images::attributes($noThumb), 'no thumb can be made: attributes are null');
			expect(null, $noThumb->emaki(), '$file->emaki() is null too');

			$blocked = snippet('emaki/gallery', ['files' => [$noThumb]], true);
			holds(str_contains($blocked, '<!-- emaki: no thumbs for unwritable.png -->'), 'gallery: the image is left out and marked in an HTML comment');
			holds(!str_contains($blocked, 'href='), 'gallery: no link at all, so never the original');
			holds(!str_contains($blocked, '<img'), 'gallery: no image tag');
			expect('<!-- emaki: no thumbs for unwritable.png -->', trim($blocked), 'gallery: no tile left, so no container, only the comment');

			// With a tile, the container stays and the comment of the left-out image sits inside it.
			$mixed = snippet('emaki/gallery', ['files' => [$this->file($kirby, 'flat.png'), $noThumb]], true);
			expect(1, substr_count($mixed, '<a class="emaki-item"'), 'gallery mixed: one tile');
			holds(str_starts_with($mixed, '<div class="emaki-gallery" data-emaki>'), 'gallery mixed: the container is printed');
			holds(str_ends_with($mixed, "  <!-- emaki: no thumbs for unwritable.png -->\n</div>\n"), 'gallery mixed: the comment is inside the container');

			// The README pattern (`if ($e = $file->emaki(...))`): no output and no error without thumbs, the container with thumbs.
			mkdir($this->site . '/site/snippets', 0700);
			file_put_contents($this->site . '/site/snippets/readme-emaki.php', <<<'PHP'
	<?php if ($e = $file->emaki(['sizes' => '(max-width: 40rem) 100vw, 33vw'])) : ?>
	<div data-emaki>
	  <a <?= attr([
	      'href'               => $e['href'],
	      'data-srcset'        => $e['data-srcset'],
	      'data-sizes'         => $e['data-sizes'],
	      'data-emaki-caption' => 'Terrace of Gasthaus Muster',
	      'data-emaki-image'   => 'terrace',
	  ]) ?>>
	    <img <?= attr([
	        'src'      => $e['src'],
	        'srcset'   => $e['srcset'],
	        'sizes'    => $e['sizes'],
	        'width'    => $e['width'],
	        'height'   => $e['height'],
	        'alt'      => $e['alt'],
	        'loading'  => $e['loading'],
	        'decoding' => $e['decoding'],
	    ]) ?>>
	  </a>
	</div>
	<?php endif ?>
	PHP);
			expect('', trim(snippet('readme-emaki', ['file' => $noThumb], true)), 'README pattern: no thumbs, no output, no error');
			holds(str_contains(snippet('readme-emaki', ['file' => $this->file($kirby, 'flat.png')], true), '<div data-emaki>'), 'README pattern: with thumbs the container is printed');
		} finally {
			chmod($pageMedia, 0755);
		}
	}

	public function testNoInlineStyleOrScriptInOutput(): void
	{
		$kirby   = $this->app(['emaki.active' => true]);
		$outputs = [
			snippet('emaki/gallery', ['files' => $kirby->page('home')->files(), 'attrs' => ['id' => 'g1']], true),
			snippet('emaki/gallery', ['files' => [$this->file($kirby, 'hostile.png')]], true),
			snippet('emaki', [], true),
		];

		$kirby     = $this->app(['emaki.active' => true, 'emaki.language' => 'de']);
		$outputs[] = snippet('emaki', [], true);

		foreach ($outputs as $output) {
			expect(null, self::inlineCode($output), 'no inline style or script');
		}
	}

	/** Each original file's hash is still the one it had when it was written. */
	private function assertOriginalsUnchanged(): void
	{
		foreach ($this->originals as $path => $hash) {
			expect($hash, hash_file('sha256', $path), 'original unchanged: ' . basename($path));
		}
	}

	/** Writes the test page's originals and records their hashes. */
	private function writeOriginals(): void
	{
		$home = $this->home();
		self::makeImage($home . 'wide.jpg', 2400, 1600);
		self::makeImage($home . 'flat.png', 1200, 800);
		self::makeImage($home . 'modern.webp', 1000, 500);
		self::makeImage($home . 'small.png', 300, 200);
		self::makeImage($home . 'hostile.png', 300, 200);
		self::makeImage($home . 'turned.jpg', 1600, 1000);
		file_put_contents($home . 'turned.jpg', self::withOrientation6(file_get_contents($home . 'turned.jpg')));
		file_put_contents($home . 'logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100" viewBox="0 0 200 100"><rect width="200" height="100" fill="#1d5c8c"/></svg>');
		file_put_contents($home . 'notes.pdf', "%PDF-1.4\n%%EOF\n");
		file_put_contents($home . 'wide.jpg.txt', "Alt: Our courtyard\n\n----\n\nCaption: Courtyard caption\n\n----\n\nSlug: courtyard\n");
		file_put_contents($home . 'modern.webp.txt', "Alt: Web picture\n");
		file_put_contents($home . 'turned.jpg.txt', "Alt: Turned\n");
		file_put_contents($home . 'logo.svg.txt', "Alt: Logo\n");
		file_put_contents($home . 'small.png.txt', "Alt: Small\n");
		file_put_contents($home . 'hostile.png.txt', "Alt: \"><script>alert(1)</script>\n");

		foreach (array_diff(scandir($home), ['.', '..']) as $name) {
			if (!in_array(pathinfo($name, PATHINFO_EXTENSION), ['jpg', 'png', 'webp', 'svg', 'pdf'], true)) {
				continue;
			}
			$path = $home . $name;
			$this->originals[$path] = hash_file('sha256', $path);
		}
		$this->assertCount(8, $this->originals, 'eight originals written');
	}

	/** The content folder of the test page, with its trailing slash. */
	private function home(): string
	{
		return $this->site . '/content/home/';
	}

	/** A new Kirby instance over the throwaway site. Becomes the current instance. */
	private function app(array $options = []): App
	{
		Snippet::$cache = []; // Kirby remembers snippet paths per process

		return new App([
			'roots' => [
				'index'   => $this->site . '/public',
				'content' => $this->site . '/content',
				'site'    => $this->site . '/site',
				'storage' => $this->site . '/storage',
				'media'   => $this->site . '/public/media',
			],
			'options' => ['url' => 'https://site.example'] + $options,
		]);
	}

	/** A file of the test page, by name. */
	private function file(App $kirby, string $name): mixed
	{
		return $kirby->page('home')->file($name);
	}

	/** The file on disk behind an address of the site. */
	private function onDisk(string $url): string
	{
		return $this->site . '/public' . parse_url($url, PHP_URL_PATH);
	}

	/** A picture with a sky, a sun and a meadow, saved in the format of the path's extension. */
	private static function makeImage(string $path, int $width, int $height): void
	{
		$im = imagecreatetruecolor($width, $height);
		imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 30, 90, 140));
		imagefilledellipse($im, (int)($width * 0.7), (int)($height * 0.4), (int)($width * 0.3), (int)($height * 0.3), imagecolorallocate($im, 240, 200, 60));
		imagefilledrectangle($im, 0, (int)($height * 0.75), $width, $height, imagecolorallocate($im, 60, 140, 70));

		match (pathinfo($path, PATHINFO_EXTENSION)) {
			'jpg'  => imagejpeg($im, $path, 90),
			'png'  => imagepng($im, $path),
			'webp' => imagewebp($im, $path, 90),
		};
	}

	/** The same JPEG with an APP1 Exif block saying orientation 6: stored landscape, shown portrait. */
	private static function withOrientation6(string $jpeg): string
	{
		$tiff = "II*\0" . pack('V', 8)                              // little endian, first IFD at offset 8
			. pack('v', 1)                                          // one entry
			. pack('vvV', 0x0112, 3, 1) . pack('v', 6) . "\0\0"    // Orientation, SHORT, one value: 6
			. pack('V', 0);                                         // no further IFD
		$payload = "Exif\0\0" . $tiff;

		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
	}

	/** The widths of a srcset, in order. @return int[] */
	private static function srcsetWidths(string $set): array
	{
		preg_match_all('/\s(\d+)w(?=,|$)/', $set, $m);

		return array_map('intval', $m[1]);
	}

	/** Inline style or script anywhere in an output: a Content Security Policy problem. */
	private static function inlineCode(string $html): string|null
	{
		foreach (['~<style\b~i', '~<script\b(?![^>]*\ssrc=)~i', '~\sstyle\s*=~i', '~\son[a-z]+\s*=~i'] as $pattern) {
			if (preg_match($pattern, $html, $hit) === 1) {
				return $hit[0];
			}
		}

		return null;
	}
}
