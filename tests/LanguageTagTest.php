<?php declare(strict_types=1);
/**
 * This program is free software: you can redistribute it and/or modify it under the terms of the
 * GNU Affero General Public License as published by the Free Software Foundation, either version 3
 * of the License, or (at your option) any later version.
 *
 * @license AGPL-3.0-or-later
 * @author KampfCaspar <code@kampfcaspar.ch>
 */

namespace KampfCaspar\Test\Language;

use KampfCaspar\Language\LanguageTag;
use PHPUnit\Framework\TestCase;

class LanguageTagTest extends TestCase
{

	/**
	 * @see https://datatracker.ietf.org/doc/html/rfc5646#appendix-A
	 * @return array<string[]>
	 */
	public static function Rfc5646ExamplesProvider(): array
	{
		return [
			['de', 'DE', 'de', 'de'],
			['fr', 'Fr', 'fr', 'fr'],
			['ja', 'jA', 'ja', 'ja'],
			['i-enochian', 'I-Enochian', 'i-enochian', 'i-enochian'],
			['zh-Hant', 'zh-Hant', 'zh-Hant', 'zh-Hant'],
			['zh-Hans', 'zh-Hans', 'zh-Hans', 'zh-Hans'],
			['sr-Cyrl', 'sr-Cyrl', 'sr-Cyrl', 'sr-Cyrl'],
			['sr-Latn', 'sr-Latn', 'sr-Latn', 'sr-Latn'],
			['zh-cmn-Hans-CN', 'zh-cmn-Hans-CN', 'cmn-Hans-CN', 'zh-cmn-Hans-CN'],
			['cmn-Hans-CN', 'cmn-Hans-CN', 'cmn-Hans-CN', 'zh-cmn-Hans-CN'],
			['zh-yue-HK', 'zh-yue-HK', 'yue-HK', 'zh-yue-HK'],
			['yue-HK', 'yue-HK', 'yue-HK', 'zh-yue-HK'],
			['zh-Hans-CN', 'zh-Hans-CN', 'zh-Hans-CN', 'zh-Hans-CN'],
			['sr-Latn-RS', 'sr-Latn-RS', 'sr-Latn-RS', 'sr-Latn-RS'],
			['sl-rozaj', 'sl-rozaj', 'sl-rozaj', 'sl-rozaj'],
			['sl-rozaj-biske', 'sl-rozaj-biske', 'sl-rozaj-biske', 'sl-rozaj-biske'],
			['sl-nedis', 'sl-nedis', 'sl-nedis', 'sl-nedis'],
			['de-CH-1901', 'de-CH-1901', 'de-CH-1901', 'de-CH-1901'],
			['sl-IT-nedis', 'sl-IT-nedis', 'sl-IT-nedis', 'sl-IT-nedis'],
			['hy-Latn-IT-arevela', 'hy-Latn-IT-arevela', 'hy-Latn-IT-arevela', 'hy-Latn-IT-arevela'],
			['de-DE', 'de-DE', 'de-DE', 'de-DE'],
			['en-US', 'en-US', 'en-US', 'en-US'],
			['es-419', 'es-419', 'es-419', 'es-419'],
			['de-CH-x-phonebk', 'de-CH-x-phonebk', 'de-CH-x-phonebk', 'de-CH-x-phonebk'],
			['az-Arab-x-AZE-derbend', 'az-Arab-x-AZE-derbend', 'az-Arab-x-aze-derbend', 'az-Arab-x-aze-derbend'],
			['x-whatever', 'x-whatever', 'x-whatever', 'x-whatever'],
			['qaa-Qaaa-QM-x-southern', 'qaa-Qaaa-QM-x-southern', 'qaa-Qaaa-QM-x-southern', 'qaa-Qaaa-QM-x-southern'],
			['de-Qaaa', 'de-Qaaa', 'de-Qaaa', 'de-Qaaa'],
			['sr-Latn-QM', 'sr-Latn-QM', 'sr-Latn-QM', 'sr-Latn-QM'],
			['sr-Qaaa-RS', 'sr-Qaaa-RS', 'sr-Qaaa-RS', 'sr-Qaaa-RS'],
			['en-US-u-islamcal', 'en-US-u-islamcal', 'en-US-u-islamcal', 'en-US-u-islamcal'],
			['zh-CN-a-myext-x-private', 'zh-CN-a-myext-x-private', 'zh-CN-a-myext-x-private', 'zh-CN-a-myext-x-private'],
		];
	}

	public function test__constructWithString(): void
	{
		foreach (self::Rfc5646ExamplesProvider() as $one) {
			$lt = new LanguageTag($one[0]);
			self::assertInstanceOf(LanguageTag::class, $lt);
		}
	}

	public function test__constructWithInvalidFormat(): void
	{
		self::expectException(\InvalidArgumentException::class);
		new LanguageTag('en-1-ö');
	}

	public function test__constructWithRepeatedVariant(): void
	{
		self::expectException(\InvalidArgumentException::class);
		new LanguageTag('en-variant-variant');
	}

	public function test__constructWithRepeatedSingleton(): void
	{
		self::expectException(\InvalidArgumentException::class);
		new LanguageTag('en-u-islamcal-v-islamcal-u-double');
	}

	public function testCanonicalize(): void
	{
		foreach (self::Rfc5646ExamplesProvider() as $one) {
			$lt = new LanguageTag($one[1]);
			$lt->canonicalize();
			self::assertEquals($one[2], (string)$lt);
		}
	}

	public function testCanonicalizeWithInvalidSubtag(): void
	{
		$lt = new LanguageTag('en-invalidvariant');
		self::expectException(\DomainException::class);
		$lt->canonicalize();
	}

	public function testCanonicalizeInExtlangFormat(): void
	{
		foreach (self::Rfc5646ExamplesProvider() as $one) {
			$lt = new LanguageTag($one[1]);
			$lt->canonicalize(true);
			self::assertEquals($one[3], (string)$lt);
		}
	}

	public function test__constructWithSubtags(): void
	{
		$lt = new LanguageTag(['language' => 'de']);
		self::assertEquals('de', $lt->language);
		self::assertEquals('', $lt->extlang);
		self::assertEquals([], $lt->variants);
	}

	public function test__constructWithInvalidSingleton(): void
	{
		self::expectException(\InvalidArgumentException::class);
		new LanguageTag([
			'language' => 'en',
			'extensions' => [
				'invalid' => ['alpha'],
			],
		]);
	}
}
