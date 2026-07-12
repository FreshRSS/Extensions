<?php

declare(strict_types=1);

class ImageProxyTestMinzExtension {
	public function init(): void {
	}

	public function handleConfigureAction(): void {
	}
}

class ImageProxyTestContextException extends RuntimeException {
}

final class ImageProxyTestConfiguration {
	/** @param array<string, bool|string> $values */
	public function __construct(private array $values) {
	}

	public function attributeBool(string $key): ?bool {
		$value = $this->values[$key] ?? null;
		return is_bool($value) ? $value : null;
	}

	public function attributeString(string $key): ?string {
		$value = $this->values[$key] ?? null;
		return is_string($value) ? $value : null;
	}
}

final class ImageProxyTestContext {
	private static ImageProxyTestConfiguration $configuration;

	/** @param array<string, bool|string> $values */
	public static function configure(array $values): void {
		self::$configuration = new ImageProxyTestConfiguration($values);
	}

	public static function userConf(): ImageProxyTestConfiguration {
		return self::$configuration;
	}
}

class_alias(ImageProxyTestMinzExtension::class, 'Minz_Extension');
class_alias(ImageProxyTestContextException::class, 'FreshRSS_Context_Exception');
class_alias(ImageProxyTestContext::class, 'FreshRSS_Context');

require_once __DIR__ . '/../xExtension-ImageProxy/extension.php';

final class ImageProxyTest {
	private const PROXY_URL = 'https://proxy.example/?url=';

	private int $assertions = 0;

	public function run(): void {
		set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
			throw new ErrorException($message, 0, $severity, $file, $line);
		});

		try {
			$this->configure();
			$this->testEmptyContent();
			$this->testUnicodeText();
			$this->testExistingEntities();
			$this->testInvalidUtf8();
			$this->testHtmlDocumentsAndTagSoup();
			$this->testImageAttributes();
			$this->testUriSchemes();
		} catch (FreshRSS_Context_Exception $e) {
			throw new RuntimeException('ImageProxy configuration failed', 0, $e);
		} finally {
			restore_error_handler();
		}

		fwrite(STDOUT, "ImageProxy regression tests passed ({$this->assertions} assertions).\n");
	}

	private function configure(bool $includeScheme = true, bool $encodeUrl = false): void {
		ImageProxyTestContext::configure([
			'image_proxy_url' => self::PROXY_URL,
			'image_proxy_scheme_http' => true,
			'image_proxy_scheme_https' => true,
			'image_proxy_scheme_default' => 'auto',
			'image_proxy_scheme_include' => $includeScheme,
			'image_proxy_url_encode' => $encodeUrl,
		]);
	}

	/** @throws FreshRSS_Context_Exception */
	private function testEmptyContent(): void {
		$this->assertSame('', ImageProxyExtension::swapUris(''), 'Empty content must remain empty');
	}

	/** @throws FreshRSS_Context_Exception */
	private function testUnicodeText(): void {
		$expected = 'ASCII café 日本語 中文 😀 𝄞';
		$output = ImageProxyExtension::swapUris('<p>' . $expected . '</p>');
		$this->assertSame($expected, $this->textContent($output), 'Unicode text must survive the DOM round trip');
	}

	/** @throws FreshRSS_Context_Exception */
	private function testExistingEntities(): void {
		$output = ImageProxyExtension::swapUris('<p>Rock &amp; Roll &quot;quote&quot; &#169; &#x1F600;</p>');
		$this->assertSame('Rock & Roll "quote" © 😀', $this->textContent($output), 'Existing entities must not be double encoded');
		$this->assertNotContains('&amp;amp;', $output, 'Named entities must not be double encoded');
	}

	/** @throws FreshRSS_Context_Exception */
	private function testInvalidUtf8(): void {
		$oldSubstitute = mb_substitute_character();
		mb_substitute_character(0xFFFD);
		try {
			$output = ImageProxyExtension::swapUris("<p>invalid \xC3\x28 sequence</p>");
			$this->assertNotSame('', $output, 'Invalid UTF-8 must not discard the complete article');
			$this->assertContains('sequence', $this->textContent($output), 'Text following invalid UTF-8 must be preserved');
		} finally {
			mb_substitute_character($oldSubstitute);
		}
	}

	/** @throws FreshRSS_Context_Exception */
	private function testHtmlDocumentsAndTagSoup(): void {
		$document = '<!doctype html><html><head><title>Ignored</title></head><body><p>Body text</p></body></html>';
		$this->assertSame('Body text', $this->textContent(ImageProxyExtension::swapUris($document)), 'Only body contents must be returned');

		$tagSoup = ImageProxyExtension::swapUris('<section data-kind="sample"><p>one<p>two</section>');
		$this->assertSame('onetwo', $this->textContent($tagSoup), 'Tag soup text must remain readable');
		$this->assertContains('data-kind="sample"', $tagSoup, 'Element attributes must survive parsing');
	}

	/** @throws FreshRSS_Context_Exception */
	private function testImageAttributes(): void {
		$input = '<p>Images</p>'
			. '<img src="http://images.example/one café.jpg" alt="café">'
			. '<img src="https://images.example/two.png" srcset="http://images.example/small.png 1x, https://images.example/large.png 2x">'
			. '<img alt="no source">';
		$output = ImageProxyExtension::swapUris($input);
		$images = $this->images($output);

		$this->assertSame(3, count($images), 'All image elements must remain present');
		$this->assertSame(
			self::PROXY_URL . 'http://images.example/one%20caf%C3%A9.jpg',
			$images[0]->getAttribute('src'),
			'Unicode image URLs must be proxied',
		);
		$this->assertSame(
			'http://images.example/one café.jpg',
			$images[0]->getAttribute('data-xextension-imageproxy-original-src'),
			'Original src must be retained',
		);
		$this->assertSame('café', $images[0]->getAttribute('alt'), 'Unicode attributes must remain readable');
		$this->assertSame(
			self::PROXY_URL . 'http://images.example/small.png 1x, ' . self::PROXY_URL . 'https://images.example/large.png 2x',
			$images[1]->getAttribute('srcset'),
			'Every srcset candidate must be proxied',
		);
		$this->assertSame(
			'http://images.example/small.png 1x, https://images.example/large.png 2x',
			$images[1]->getAttribute('data-xextension-imageproxy-original-srcset'),
			'Original srcset must be retained',
		);
		$this->assertSame(false, $images[2]->hasAttribute('src'), 'Images without src must remain without src');
	}

	/** @throws FreshRSS_Context_Exception */
	private function testUriSchemes(): void {
		$this->assertSame(
			'data:image/png;base64,abc',
			ImageProxyExtension::getProxyImageUri('data:image/png;base64,abc'),
			'Unsupported schemes must not be proxied',
		);

		$_SERVER['HTTPS'] = 'on';
		$this->assertSame(
			self::PROXY_URL . 'https://images.example/a.png',
			ImageProxyExtension::getProxyImageUri('//images.example/a.png'),
			'Protocol-relative URLs must use the current HTTPS scheme',
		);

		$this->configure(includeScheme: false, encodeUrl: true);
		$this->assertSame(
			self::PROXY_URL . rawurlencode('images.example/a b.png'),
			ImageProxyExtension::getProxyImageUri('http://images.example/a b.png'),
			'Configured scheme removal and URL encoding must be preserved',
		);
	}

	private function textContent(string $html): string {
		$doc = $this->parseFragment($html);
		$body = $doc->getElementsByTagName('body')->item(0);
		if (!($body instanceof DOMElement)) {
			throw new RuntimeException('Could not parse HTML body');
		}
		return $body->textContent;
	}

	/** @return list<DOMElement> */
	private function images(string $html): array {
		$doc = $this->parseFragment($html);
		$images = [];
		foreach ($doc->getElementsByTagName('img') as $image) {
			if ($image instanceof DOMElement) {
				$images[] = $image;
			}
		}
		return $images;
	}

	private function parseFragment(string $html): DOMDocument {
		$doc = new DOMDocument();
		libxml_use_internal_errors(true);
		if (!$doc->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>')) {
			throw new RuntimeException('Could not parse HTML fragment');
		}
		return $doc;
	}

	private function assertSame(mixed $expected, mixed $actual, string $message): void {
		++$this->assertions;
		if ($expected !== $actual) {
			throw new RuntimeException($message . sprintf("\nExpected: %s\nActual: %s", var_export($expected, true), var_export($actual, true)));
		}
	}

	private function assertNotSame(mixed $unexpected, mixed $actual, string $message): void {
		++$this->assertions;
		if ($unexpected === $actual) {
			throw new RuntimeException($message . sprintf("\nUnexpected: %s", var_export($unexpected, true)));
		}
	}

	private function assertContains(string $needle, string $haystack, string $message): void {
		++$this->assertions;
		if (!str_contains($haystack, $needle)) {
			throw new RuntimeException($message . sprintf("\nMissing: %s\nActual: %s", var_export($needle, true), var_export($haystack, true)));
		}
	}

	private function assertNotContains(string $needle, string $haystack, string $message): void {
		++$this->assertions;
		if (str_contains($haystack, $needle)) {
			throw new RuntimeException($message . sprintf("\nUnexpected: %s\nActual: %s", var_export($needle, true), var_export($haystack, true)));
		}
	}
}

(new ImageProxyTest())->run();
