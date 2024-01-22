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

/**
 * IANA Language Subtag Registry Interface
 *
 * @phpstan-type RegistryType 'language'|'extlang'|'script'|'region'|'variant'|'grandfathered'
 * @phpstan-type RegistryResult array{
 *      'Type'?: string,
 *      'Tag'?: string,
 *      'Deprecated'?: string|bool,
 *      'Suppress-Script'?: string,
 *      'Preferred-Value'?: string,
 *      'Prefix'?: string[] }
 */
interface RegistryInterface
{
	/**
	 * lookup a subtag in the registry
	 *
	 * Returns an array of possibly relevant entries; an empty array if entry is not found.
	 *
	 * @param RegistryType $type
	 * @return RegistryResult
	 */
	public function getRegistryEntry(string $type, string $subtag): array;
}