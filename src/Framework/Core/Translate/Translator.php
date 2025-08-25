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

namespace App\Framework\Core\Translate;

use App\Framework\Core\Locales\Locales;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function intl_get_error_message;

/**
 * Class Translator
 *
 * This class provides functionality for managing translations within an application.
 * It supports translation lookups, pluralization handling, and replacement of placeholders
 * within translation strings. Translations can be loaded, cached, and retrieved using a
 * dynamic key and module approach.
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
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
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
	 * @return  array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
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
	 * @throws InvalidArgumentException
	 * @throws FrameworkException|PhpfastcacheSimpleCacheException
	 */
	public function translateWithPlural(string $key, string $module, int $count, array $replacements = []): string
	{
		$languageCode = $this->locales->getLanguageCode();
		$translation  = $this->findTranslation($key, $module, $languageCode);

		if (!is_string($translation))
			return '';

		// add number of replacements
		$replacements['count'] = $count;

		return $this->formatWithMessageFormatter($translation, $replacements);
	}

	/**
	 * @param array<string,mixed> $replacements
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function translateArrayWithPlural(string $key, string $section, string $module, int $count, array $replacements = []): string
	{
		$languageCode = $this->locales->getLanguageCode();
		$section      = $this->findTranslation($section, $module, $languageCode);
		$translation  = $section[$key] ?? '';

		// add number of replacements
		$replacements['count'] = $count;

		return $this->formatWithMessageFormatter($translation, $replacements);
	}

	/**
	 * @return string|array<string,mixed>
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	protected function findTranslation(string $key, string $module, string $languageCode): string|array
	{
		$cacheKey = $this->buildCacheKey($languageCode, $module);

		if (!isset($this->translationStack[$cacheKey]))
		{
			if ($this->cache->has($cacheKey))
				$this->translationStack[$cacheKey] = $this->cache->get($cacheKey);
			else
			{
				$data = $this->loader->load($languageCode, $module);
				$this->translationStack[$cacheKey] = $data;
				$this->cache->set($cacheKey, $data);
			}
		}

		return $this->translationStack[$cacheKey][$key] ?? '';
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

