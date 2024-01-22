<?php declare(strict_types=1);
/**
 * This program is free software: you can redistribute it and/or modify it under the terms of the
 * GNU Affero General Public License as published by the Free Software Foundation, either version 3
 * of the License, or (at your option) any later version.
 *
 * @license AGPL-3.0-or-later
 * @author KampfCaspar <code@kampfcaspar.ch>
 */

namespace KampfCaspar\Language;

use KampfCaspar\Language\Registry\RegistryInterface;
use KampfCaspar\Language\Registry\StaticRegistry;
use KampfCaspar\Polyfill\Types;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Language Representation according to RFC 5646
 *
 * @property-read string                 $language
 * @property-read string                 $extlang
 * @property-read string                 $script
 * @property-read string                 $region
 * @property-read string[]               $variants
 * @property-read array<string,string[]> $extensions
 *
 * @phpstan-type SubtagArray array{
 *      'language': string,
 *      'extlang': string,
 *      'script': string,
 *      'region': string,
 *      'variants': string[],
 *      'extensions': array<string,string[]>}
 * @phpstan-type SubtagArrayMinimal array{
 *       'language': string,
 *       'extlang'?: string,
 *       'script'?: string,
 *       'region'?: string,
 *       'variants'?: string[],
 *       'extensions'?: array<string,string[]>}
 * @phpstan-type SubtagIndex key-of<SubtagArray>
 * @phpstan-import-type RegistryType from RegistryInterface
 */
class LanguageTag implements LoggerAwareInterface, \Stringable, \JsonSerializable
{
	use LoggerAwareTrait;

	/** pregex pattern to match language tags
	 *
	 * Although the RFC allows for upto three extlang subtags,
	 * all but one is 'permanently reserved'. I don't know if
	 * multiple extlang subtags should even exist and therefore don't accept it...
	 *
	 * @see https://datatracker.ietf.org/doc/html/rfc5646#section-2.1
	 */
	private const PREG = <<<'EOS'
		/^
		(?:                                            
			(?'language'[a-z]{2,8})?
			(?:-(?'extlang'[a-z]{3}))?
			(?:-(?'script'[a-z]{4}))?
			(?:-(?'region'[a-z]{2}|\d{3}))?
			(?'variants'(?:-(?:[a-z]{5,}|\d{4,}))*)
		)?
		(?'extensions'(?:(?:(?<!^)-|^)[a-z](?:-[a-z]{2,8})+)*)
		$/ix
		EOS;

	/** all allowed subtags incl. default values
	 * @var SubtagArray
	 */
	private const NIL = [
		'language' => '',
		'extlang' => '',
		'script' => '',
		'region' => '',
		'variants' => [],
		'extensions' => [],
	];

	/** RegistryInterface used for canonicalization
	 * @see self::setRegistry()
	 */
	private static RegistryInterface $registry;

	/** subtags of the language tag
	 *
	 * @see https://datatracker.ietf.org/doc/html/rfc5646#section-2.2
	 * @var SubtagArray
	 */
	protected array $subtags;

	/** flag if the language tag is deprecated
	 */
	protected bool $deprecated;

	/**
	 * change the registry used for canonicalization
	 */
	public static function setRegistry(RegistryInterface $registry): void
	{
		self::$registry = $registry;
	}

	/**
	 * construct a new language tag from string or subtag array
	 *
	 * Only a subset of format compliance is checked:
	 *   * string format as per RFC 5646
	 *   * no repeated variants
	 *   * no repeated/invalid extension singletons
	 *
	 * @param string|SubtagArrayMinimal $tag
	 */
	public function __construct(
		string|array $tag,
		?LoggerInterface $logger = null,
	) {
		if ($logger) {
			$this->setLogger($logger);
		}
		if (is_string($tag)) {
			$registry = $this->getRegistry();
			$entry = $registry->getRegistryEntry('grandfathered', $tag);
			if ($entry['Tag'] ?? false) {
				$tag = ['language' => $entry['Tag']];
			}
			else {
				$tag = $this->interpretString($tag);
			}
		}
		else {
			// make real copies of nested arrays (they are only passed by reference...)
			/** @var SubtagArray $tag */
			$tag = Types::cloneArray($tag);
		}

		$this->subtags = array_intersect_key($tag, self::NIL) + self::NIL;

		$check_variants = array_map(fn($x) => strtolower($x), $this->subtags['variants']);
		if (count($check_variants) !== count(array_unique($check_variants))) {
			throw new \InvalidArgumentException(sprintf(
				'illegal Language with repeated variant: %s',
				implode(', ', $this->subtags['variants'])
			));
		}
		if (array_filter(
			array_keys($this->subtags['extensions']),
			fn($x) => strlen($x) !== 1 || !ctype_alnum($x)
		)) {
			throw new \InvalidArgumentException(sprintf(
				'illegal Language with non-singleton/alphanumeric extensions: %s ',
				implode(', ', array_keys($this->subtags['extensions']))
			));
		}
		$check_keys = array_keys($this->subtags['extensions']);
		$check_extensions = array_change_key_case($this->subtags['extensions']);
		if (count($check_keys) !== count($check_extensions)) {
			throw new \InvalidArgumentException(sprintf(
				'illegal Language with case-doubled singleton extensions: %s',
				implode(', ', $check_keys))
			);
		}
	}

	/**
	 * convert language tag to canonical format
	 *
	 * Convert the Language to canonical format. This includes capitalization
	 * according to the RFC (but tags are case-insensitive!). All subtags
	 * are furthermore checked against the subtag registry.
	 *
	 * Invalid subtags (according the registry) result in an exception.
	 *
	 * @param bool $extlangFormat  (optional) whether to canonicalize to extlang format
	 * @return $this
	 *
	 * @see https://datatracker.ietf.org/doc/html/rfc5646#section-4.5
	 * @throws \DomainException    if any subtag is invalid
	 */
	public function canonicalize(bool $extlangFormat = false): self
	{
		$this->deprecated = false;
		if (!$this->canonicalizeGrandfatheredToken()) {
			return $this;
		}

		$this->subtags = [
			'language' => strtolower($this->subtags['language']),
			'extlang' => strtolower($this->subtags['extlang']),
			'script' => ucwords($this->subtags['script']),
			'region' => strtoupper($this->subtags['region']),
			'variants' => array_map(fn($x) => strtolower($x), $this->subtags['variants']),
			'extensions' => array_change_key_case($this->subtags['extensions']),
		];

		$this->subtags['extensions'] = array_map(fn($x) => array_map(fn($y) => strtolower($y), $x), $this->subtags['extensions']);
		$sorter = array_flip(array_keys($this->subtags['extensions']));
		$sorter['x'] = 999;
		uksort($this->subtags['extensions'], fn($a,$b) => $sorter[$a] - $sorter[$b]);

		foreach (['language', 'extlang', 'script', 'region'] as $type) {
			$this->subtags[$type] = $this->canonicalizeSubtagValue($type, $this->subtags[$type]);
		}
		foreach ($this->subtags['variants'] as &$variant) {
			$variant = $this->canonicalizeSubtagValue('variant', $variant);
		}
		unset($variant);

		if ($extlangFormat) {
			$registry = $this->getRegistry()->getRegistryEntry('extlang', $this->subtags['language']);
			if ($registry) {
				$this->subtags['extlang'] = $this->subtags['language'];
				$this->subtags['language'] = $registry['Prefix'][0];
			}
		}

		return $this;
	}

	/**
	 * indicate if any subtag is deprecated
	 *
	 * Value is only available after canonicalization.
	 */
	public function isDeprecated(): bool
	{
		return $this->deprecated
		       ?? throw new \LogicException('only after canonicalization is deprecation state available');
	}

	/**
	 * convert language tag from its string representation to subtags
	 * @return SubtagArray
	 */
	protected function interpretString(string $tag): array
	{
		$matches = [];
		if (!preg_match(self::PREG, $tag,$matches)) {
			throw new \InvalidArgumentException(sprintf(
				'illegal Language format: %s',
				$tag
			));
		}
		/** @var array<SubtagIndex,string> $matches */
		$matches['variants'] = array_filter(explode('-', $matches['variants']));
		$extensions = array_filter(explode('-', $matches['extensions']));
		$matches['extensions'] = [];

		$singleton = null;
		foreach ($extensions as $extension) {
			if (strlen($extension) === 1 && $singleton !== 'x' ) {
				$extension = strtolower($extension);
				if (isset($matches['extensions'][$extension])) {
					throw new \InvalidArgumentException(sprintf(
						'illegal Language with repeated singleton "%s": %s',
						$extension,
						$tag
					));
				}
				$matches['extensions'][$extension] = [];
				$singleton = $extension;
			}
			else {
				$matches['extensions'][$singleton][] = $extension;
			}
		}
		// as there is no way to prohibit preg_match from adding the numeric indexes
		/** @var SubtagArray $matches */
		$matches = array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
		return $matches;
	}

	/**
	 * get registry used for canonicalization, defaults to {@see StaticRegistry}
	 */
	protected function getRegistry(): RegistryInterface
	{
		if (!isset(self::$registry)) {
			self::$registry = new StaticRegistry($this->logger);
		}
		return self::$registry;
	}

	/**
	 * check language against possible grandfathered values
	 * @return bool  if canonicalization should continue
	 */
	protected function canonicalizeGrandfatheredToken(): bool
	{
		$registry = $this->getRegistry();
		$old = $this->subtags['language'];

		$registry_entry = $registry->getRegistryEntry('grandfathered', $old);
		if ($registry_entry['Preferred-Value'] ?? false) {
			$new = $registry_entry['Preferred-Value'];
			$this->logger?->notice('exchange {reason} language tag from "{old}" to "{new}', [
				'reason' => $registry_entry['Type'] ?? 'nuclear',
				'old' => $old,
				'new' => $new,
			]);
			$this->subtags = $this->interpretString($new);
			return true;
		}
		elseif ($registry_entry['Tag'] ?? false) {
			$this->deprecated = boolval($registry_entry['Deprecated'] ?? false);
			$this->subtags['language'] = $registry_entry['Tag'];
			return false;
		}
		return true;
	}

	/**
	 * canonicalize a subtag with the registry
	 * @param RegistryType $type
	 */
	protected function canonicalizeSubtagValue(string $type, string $value): string
	{
		if (!$value) {
			return $value;
		}
		$registry = $this->getRegistry()->getRegistryEntry($type, $value);
		if (!$registry) {
			throw new \DomainException(sprintf(
				'illegal subtag "%s" not in registry for type "%s"',
				$value,
				$type,
			));
		}
		if ($registry['Prefix'] ?? false) {
			$current_string = $this->__toString();
			$found_prefix = false;
			foreach ($registry['Prefix'] as $prefix) {
				if (str_starts_with($current_string, $prefix)) {
					$found_prefix = true;
					break;
				}
			}
			if (!$found_prefix) {
				throw new \DomainException(sprintf(
					'no valid prefix for subtag "%s" of type "%s" found',
					$value,
					$type,
				));
			}
		}
		if ($registry['Preferred-Value'] ?? false) {
			if ($type === 'language' || $type === 'extlang') {
				$replacement = $this->interpretString($registry['Preferred-Value']);
				$replacement   = array_intersect_key($replacement, ['language' => 0, 'extlang' => 1]);
				$this->subtags = $replacement + $this->subtags;
				$value = $this->subtags[$type];
			}
			else {
				$value = $registry['Preferred-Value'];
			}
		}
		elseif ($registry['Deprecated'] ?? false) {
			$this->deprecated = true;
		}

		if ($type === 'language') {
			if (($registry['Suppress-Script'] ?? false) === $this->subtags['script']) {
				$this->subtags['script'] = '';
			}
		}

		return $value;
	}

	/**
	 * get subtag
	 *
	 * @param SubtagIndex $key
	 * @return mixed
	 */
	public function __get(string $key): mixed
	{
		return $this->subtags[$key]
		       ?? throw new \BadMethodCallException('unknown language subtag: ' . $key);
	}

	public function __toString(): string
	{
		$parts = [
			$this->subtags['language'],
			$this->subtags['extlang'],
			$this->subtags['script'],
			$this->subtags['region'],
			join('-', $this->subtags['variants'])
		];
		foreach ($this->subtags['extensions'] as $k => $v) {
			$parts[] = $k . '-' . join('-', $v);
		}
		$parts = array_filter($parts);
		return join('-', $parts);
	}

	public function jsonSerialize(): mixed
	{
		return $this->__toString();
	}

}