<?php declare(strict_types=1);
/**
 * This program is free software: you can redistribute it and/or modify it under the terms of the
 * GNU Affero General Public License as published by the Free Software Foundation, either version 3
 * of the License, or (at your option) any later version.
 *
 * @license AGPL-3.0-or-later
 * @author KampfCaspar <code@kampfcaspar.ch>
 */

namespace KampfCaspar\Language\Registry;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Minimal Subtag Registry reduced to grandfathered tags
 *
 * All subtags are accepted as is.
 */
class MinimalRegistry implements RegistryInterface, LoggerAwareInterface
{
	use LoggerAwareTrait;

	private const GRANDFATHERED = [
		'en-gb-oed'   => 'en-GB-oxendict',
		'i-ami'       => 'ami',
		'i-bnn'       => 'bnn',
		'i-default'   => 'i-default',
		'i-enochian'  => false,  // Deprecated 2015-03-29
		'i-hak'       => 'hak',
		'i-klingon'   => 'tlh',
		'i-lux'       => 'lb',
		'i-mingo'     => 'i-mingo',
		'i-navajo'    => 'nv',
		'i-pwn'       => 'pwn',
		'i-tao'       => 'tao',
		'i-tay'       => 'tay',
		'i-tsu'       => 'tsu',
		'sgn-BE-FR'   => 'sfb',
		'sgn-BE-NL'   => 'vgt',
		'sgn-CH-DE'   => 'sgg',
		'art-lojban'  => 'jbo',
		'cel-gaulish' => false,  // Deprecated 2015-03-29, see xcg, xga, xtg
		'no-bok'      => 'nb',
		'no-nyn'      => 'nn',
		'zh-guoyu'    => 'cmn',
		'zh-hakka'    => 'hak',
		'zh-min'      => false,  // Deprecated 2009-07-29, see cdo, cpx, czo, mnp, nan
		'zh-min-nan'  => 'nan',
		'zh-xiang'    => 'hsn',
	];

	public function __construct(?LoggerInterface $logger = null)
	{
		if ($logger) {
			$this->setLogger($logger);
		}
	}

	public function getRegistryEntry(string $type, string $subtag): array
	{
		if ($type === 'grandfathered') {
			$subtag = strtolower($subtag);
			if (isset(self::GRANDFATHERED[$subtag])) {
				return [
					'Type' => 'grandfathered',
					'Tag' => $subtag,
					'Preferred-Value' => self::GRANDFATHERED[$subtag],
					'Deprecated' => !self::GRANDFATHERED[$subtag],
				];
			}
			return [];
		}
		$this->logger?->notice('blindly accept subtag "{subtag}" of type "{type}"', [
			'subtag' => $subtag,
			'type' => $type
		]);
		return [
			'Type' => $type,
		];
	}

}