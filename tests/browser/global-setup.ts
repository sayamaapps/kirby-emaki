import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

/** The fixture images are generated with PHP's GD library before the browser tests run. */
export default function globalSetup(): void {
  execFileSync('php', [join(__dirname, 'make-fixture-images.php')], { stdio: 'inherit' });
}
