<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace App\Framework\Localization\Translate;

use App\Framework\Localization\Locales\Locales;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\TranslatableModuleException;
use Phpfastcache\Exceptions\PhpfastcacheExceptionInterface;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

use function intl_get_error_message;

/**
 * Class Translator
 *
 * This class provides functionality for managing translations within an application.
 * It supports translation lookups, pluralization handling, and replacement of placeholders
 * within translation strings. Translations can be loaded, cached, and retrieved using a
 * dynamic key and module approach.
 *
 * The cache is only an optimization: if it fails, translations are loaded directly from the loader.
 */
class Translator
{
	protected Locales $locales;
	protected TranslationLoaderInterface $loader;
	protected CacheInterface $cache;
	/** @var array<string,mixed>  */
	protected array $translationStack = [];
	protected MessageFormatterFactory $MessageFormatterFactory;

	public function __construct(Locales $locales, TranslationLoaderInterface $loader, MessageFormatterFactory
	$messageFormatterFactory, Psr16Adapter $cache)
	{
		$this->locales = $locales;
		$this->loader  = $loader;
		$this->cache   = $cache;

		$this->MessageFormatterFactory = $messageFormatterFactory;
	}

	/**
	 * @param array<string,string> $replacements
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function translate(string $key, string $module, array $replacements = []): string
	{
		$languageCode = $this->locales->getLanguageCode();
		$translation = $this->findTranslation($key, $module, $languageCode);

		if (!is_string($translation))
			return '';

		return $replacements !== [] ? $this->doReplacements($translation, $replacements) : $translation;
	}

	/**
	 * Translates the key of an expected error. Its replacements are written as {name}
	 * in the translation, e.g. "Playlist {playlist_id} not found."
	 *
	 * @return string empty when there is no translation
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function translateException(TranslatableModuleException $exception): string
	{
		$replacements = [];
		foreach ($exception->getReplacements() as $name => $value)
		{
			$replacements['{' . $name . '}'] = (string) $value;
		}

		return $this->translate($exception->getTranslationKey(), $exception->getModuleName(), $replacements);
	}

	/**
	 * @return  array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function translateArrayForOptions(string $key, string $module): array
	{
		$language_code      = $this->locales->getLanguageCode();
		$translation_array  = $this->findTranslation($key, $module, $language_code);

		if (!is_array($translation_array))
			return [];

		return $translation_array;
	}

	/**
	 * @param array<string,mixed> $replacements
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function translateWithPlural(string $key, string $module, int $count, array $replacements = []): string
	{
		$languageCode = $this->locales->getLanguageCode();
		$translation  = $this->findTranslation($key, $module, $languageCode);

		// an empty pattern (missing translation) can not be formatted
		if (!is_string($translation) || $translation === '')
			return '';

		// add number of replacements
		$replacements['count'] = $count;

		return $this->formatWithMessageFormatter($translation, $replacements);
	}

	/**
	 * @param array<string,mixed> $replacements
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function translateArrayWithPlural(string $key, string $section, string $module, int $count, array $replacements = []): string
	{
		$languageCode = $this->locales->getLanguageCode();
		$section      = $this->findTranslation($section, $module, $languageCode);
		$translation  = is_array($section) ? ($section[$key] ?? '') : '';

		// an empty pattern (missing translation) can not be formatted
		if (!is_string($translation) || $translation === '')
			return '';

		// add number of replacements
		$replacements['count'] = $count;

		return $this->formatWithMessageFormatter($translation, $replacements);
	}

	/**
	 * @return string|array<string,mixed>
	 * @throws CoreException
	 */
	protected function findTranslation(string $key, string $module, string $languageCode): string|array
	{
		$cacheKey = $this->buildCacheKey($languageCode, $module);

		if (!isset($this->translationStack[$cacheKey]))
		{
			$data = $this->readFromCache($cacheKey);
			if ($data === null)
			{
				$data = $this->loader->load($languageCode, $module);
				$this->writeToCache($cacheKey, $data);
			}
			$this->translationStack[$cacheKey] = $data;
		}

		return $this->translationStack[$cacheKey][$key] ?? '';
	}

	/**
	 * @return array<string,mixed>|null null when not cached or the cache is not available
	 */
	protected function readFromCache(string $cacheKey): ?array
	{
		try
		{
			// one lookup instead of has() + get()
			$data = $this->cache->get($cacheKey);
			return is_array($data) ? $data : null;
		}
		catch (CacheException|PhpfastcacheExceptionInterface)
		{
			return null;
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	protected function writeToCache(string $cacheKey, array $data): void
	{
		try
		{
			$this->cache->set($cacheKey, $data);
		}
		catch (CacheException|PhpfastcacheExceptionInterface)
		{
			// translations are loaded again with the next request
		}
	}

	/**
	 * @param array<string,mixed> $replacements
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	protected function formatWithMessageFormatter(string $pattern, array $replacements): string
	{
		$languageCode = $this->locales->getLanguageCode();
		$formatter = $this->MessageFormatterFactory->create($languageCode, $pattern);

		$formatted = $formatter->format($replacements);

		if ($formatted === false)
			throw new FrameworkException('MessageFormatter error: ' . intl_get_error_message());

		return $formatted;
	}

	/**
	 * @param array<string,string> $replacements
	 */
	protected function doReplacements(string $input, array $replacements): string
	{
		return str_replace(array_keys($replacements), array_values($replacements), $input);
	}

	protected function buildCacheKey(string $languageCode, string $module): string
	{
		return sprintf('lang_%s_%s', $languageCode, $module);
	}
}

