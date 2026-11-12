<?php
// PHPStan symbol stubs for the PHP 8.4+ \Dom HTML5 parser (phpVersion min < 8.4 hides the real ones).
// Never executed; registered via scanFiles in phpstan.dist.neon.
// Can be removed once the minimum PHP version is 8.4.

namespace Dom;

class Node {}

class Element extends Node {
	public string $innerHTML;
	public function getAttribute(string $qualifiedName): ?string {}
	public function hasAttribute(string $qualifiedName): bool {}
	public function setAttribute(string $qualifiedName, string $value): void {}
}

class HTMLElement extends Element {}

/**
 * @implements \IteratorAggregate<int, Element>
 */
class HTMLCollection implements \IteratorAggregate, \Countable {
	public function count(): int {}
	/** @return \Iterator<int, Element> */
	public function getIterator(): \Iterator {}
	public function item(int $index): ?Element {}
}

abstract class Document extends Node {
	public ?HTMLElement $body;
	public function getElementsByTagName(string $qualifiedName): HTMLCollection {}
}

final class HTMLDocument extends Document {
	public static function createFromString(string $source, int $options = 0, ?string $overrideEncoding = null): HTMLDocument {}
}
