<?php declare(strict_types=1);
/**
 * This program is free software: you can redistribute it and/or modify it under the terms of the
 * GNU Affero General Public License as published by the Free Software Foundation, either version 3
 * of the License, or (at your option) any later version.
 *
 * @license AGPL-3.0-or-later
 * @author KampfCaspar <code@kampfcaspar.ch>
 */

namespace KampfCaspar\Language\Registry\Util;

final class RegistryReader
{
	public const KEY_FIELDS = ['Tag', 'Subtag'];
	public const SCALAR_FIELDS = ['Tag', 'Deprecated', 'Suppress-Script', 'Preferred-Value', 'Prefix'];
	public const ARRAY_FIELDS = ['Prefix'];

	public static function fromStream($fh): array
	{
		$registry = [];
		$entry_key = null;
		$entry_type = null;
		$entry = [];
		$line = '';
		$next_line = rtrim(fgets($fh));

		while ($next_line !== false) {
			$line = $next_line;
			do {
				$next_line = fgets($fh);
				if ($next_line === false) {
					break;
				}
				$next_line = rtrim($next_line);
				$add = str_starts_with($next_line, ' ');
				if ($add) {
					$line .= $next_line;
				}
			}
			while ($add);

			[$key, $value] = explode(':', $line, 2);
			$value = trim($value);
			if ($key == 'Type') {
				$entry_type = $value;
			}
			elseif (in_array($key, self::KEY_FIELDS)) {
				$entry_key = $value;
			}

			if (in_array($key, self::ARRAY_FIELDS)) {
				$entry[$key][] = $value;
			}
			elseif (in_array($key, self::SCALAR_FIELDS)) {
				$entry[$key] = $value;
			}

			if ($next_line === '%%' || $next_line === false) {
				if ($entry_key && !str_contains($entry_key, '..')) {
					$registry[$entry_type][strtolower($entry_key)] = $entry;
				}
				$entry = [];
				$entry_key = null;
				$entry_type = null;
				$next_line = fgets($fh);
				$next_line = is_string($next_line) ? rtrim($next_line) : $next_line;
			}
		}

		return $registry;
	}
}