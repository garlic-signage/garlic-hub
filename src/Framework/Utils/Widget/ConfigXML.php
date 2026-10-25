<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Utils\Widget;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\BaseSimpleXml;
use SimpleXMLElement;

/**
 * Based on https://www.w3.org/TR/widgets/
 *
 * differences from standard reverse engineered from IAdea
 * xmlns:ia="http://schemas.iawidget.org/widgetconfig-1.0"
 */
class ConfigXML extends BaseSimpleXml
{
	public const string DEFAULT_LANGUAGE = 'en';
	public const string DEFAULT_DIRECTION = 'ltr';

	protected ?SimpleXMLElement $MyXML = null;
	protected string $defaultLanguage = self::DEFAULT_LANGUAGE;
	protected string $defaultDirection = self::DEFAULT_DIRECTION;
	protected string $id = '';
	protected string $version = '';
	protected string $icon = 'icon.png';
	protected string $content = 'index.html';
	/** @var array<string,mixed> */
	protected array $name = [];
	/** @var array<string,mixed> */
	protected array $description = [];
	/** @var array<string,mixed> */
	protected array $license = [];
	/** @var array<string,mixed> */
	protected array $preferences = [];
	/** @var array<string,mixed> */
	protected array $author = [];

	public function __construct()
	{
	}

	public function getDefaultLanguage(): string
	{
		return $this->defaultLanguage;
	}

	public function getDefaultDirection(): string
	{
		return $this->defaultDirection;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getVersion(): string
	{
		return $this->version;
	}

	public function getContent(): string
	{
		return $this->content;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getName(): array
	{
		return $this->name;
	}

	public function getNameByLanguage(string $lang = self::DEFAULT_LANGUAGE): string
	{
		return $this->checkLanguageKeyOfArray($this->name, $lang);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getDescription(): array
	{
		return $this->description;
	}

	public function getDescriptionByLanguage(string $lang = self::DEFAULT_LANGUAGE): string
	{
		return $this->checkLanguageKeyOfArray($this->description, $lang);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getLicense(): array
	{
		return $this->license;
	}

	public function getLicenseByLanguage(string $lang = self::DEFAULT_LANGUAGE): string
	{
		return $this->checkLanguageKeyOfArray($this->license, $lang);
	}

	public function getIcon(): string
	{
		return $this->icon;
	}


	/**
	 * @return array<string,mixed>
	 */
	public function getAuthor(): array
	{
		return $this->author;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getPreferences(): array
	{
		return $this->preferences;
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function load(string $string): static
	{
		$this->MyXML = $this->loadXmlFromString($string)->getXmlObj();
		if ($this->MyXML->getName() != 'widget')
		{
			throw new FrameworkException('This string is not a config.xml for widgets (root element: ' . $this->MyXML->getName() . ').');
		}
		return $this;
	}

	public function parseBasic(): static
	{
		if ($this->MyXML === null)
			return $this;

		$this->parseDefaultLanguage()
			 ->parseDefaultDirection()
			 ->parseId()
			 ->parseVersion()
			 ->parseAuthor();

		// language depend
		$this->name        = $this->parseName();
		$this->description = $this->parseDescription();
		$this->license     = $this->parseLicense();

		if (isset($this->MyXML->icon['src']))
		{
			$this->icon = (string)$this->MyXML->icon['src'];
		}
		if (isset($this->MyXML->content['src']))
		{
			$this->content = (string)$this->MyXML->content['src'];
		}
		return $this;
	}

	/**
	 * This is the IAdea stuff for adding command-parameters to a widget
	 */
	public function parsePreferences(): static
	{
		if (!isset($this->MyXML->preference))
			return $this;

		$this->preferences = [];

		foreach($this->MyXML->preference as $pref)
		{
			// readonly is only interesting for player not for an authoring tool or CMS
			if (isset($pref['readonly']) && strtolower((string) $pref['readonly']) == 'true')
				continue;
			if (!isset($pref['name']) || empty($pref['name']))
				continue;

			$this->preferences[(string) $pref['name']] = $this->parsePreference($pref);
		}

		return $this;
	}

	public function hasEditablePreferences(): bool
	{
		if ($this->MyXML === null)
			return false;

		// this bullshit must be done before due to xpath need to register the default namespace
		// https://www.php.net/manual/de/simplexmlelement.xpath.php#116622
		$this->MyXML->registerXPathNamespace('f', 'http://www.w3.org/ns/widgets');

		$preferences = $this->MyXML->xpath('//f:preference');
		if (empty($preferences))
			return false;

		$countPreferences = count($preferences);

		$readOnlyPreferences = $this->MyXML->xpath('//f:preference[contains(@readonly,\'true\')]');
		if (empty($readOnlyPreferences))
			$countReadonly = 0;
		else
			$countReadonly = count($readOnlyPreferences);

		return ($countPreferences - $countReadonly > 0);
	}

	private function parseDefaultLanguage(): static
	{
		/** @phpstan-ignore-next-line */ // calling method parseBasic already checks for $this->>MyXml === null
		$attr =  $this->MyXML->attributes('xml', true);
		if ($attr === null)
			return $this;

		$attributesAsArray = (array)$attr;
		if (array_key_exists('@attributes', $attributesAsArray) && array_key_exists('lang', $attributesAsArray['@attributes']))
			$this->defaultLanguage = strtolower(substr($attributesAsArray['@attributes']['lang'], 0, 2));

		return $this;
	}

	private function parseDefaultDirection(): static
	{
		if (isset($this->MyXML['dir']))
			$this->defaultDirection = (string) $this->MyXML['dir'];

		return $this;
	}

	protected function parseId(): static
	{
		// W3C Style
		if (isset($this->MyXML['id']))
			$this->id =  (string) $this->MyXML['id'];
		else if (isset($this->MyXML->id)) // IAdea style
			$this->id =  str_replace(['{', '}'], '', (string)$this->MyXML->id);

		return $this;
	}

	protected function parseVersion(): static
	{
		// W3C Style
		if (isset($this->MyXML['version']))
			$this->version = (string) $this->MyXML['version'];
		elseif (isset($this->MyXML->version)) // IAdea style
			$this->version = (string) $this->MyXML->version;

		return $this;
	}

	private function parseAuthor(): void
	{
		if (!isset($this->MyXML->author))
		{
			$this->author = [];
			return;
		}

		if (isset($this->MyXML->author['href']))
			$this->author['href'] = (string) $this->MyXML->author['href'];
		if (isset($this->MyXML->author['email']))
			$this->author['email'] = (string) $this->MyXML->author['email'];

		$this->author['data'] = (string) $this->MyXML->author;
		// Can have children but that is YAGNI
	}

	/**
	 * @return array<string,string>
	 */
	protected function parseName(): array
	{
		if (!isset($this->MyXML->name))
			return [];

		return $this->parseLanguages($this->MyXML->name);
	}

	/**
	 * @return array<string,string>
	 */
	protected function parseDescription(): array
	{
		if (!isset($this->MyXML->description))
			return [];

		return $this->parseLanguages($this->MyXML->description);
	}

	/**
	 * @return array<string,string>
	 */
	protected function parseLicense(): array
	{
		if (!isset($this->MyXML->license))
			return [];

		return $this->parseLanguages($this->MyXML->license);
	}

	/**
	 * @return array<string,string>
	 */
	protected function parseLanguages(SimpleXMLElement $element): array
	{
		$ret = [];
		foreach($element as $value)
		{
			$attr =  $value->attributes('xml', true);
			$lang = self::DEFAULT_LANGUAGE;
			if (isset($attr['lang']))
				$lang = strtolower(substr((string)$attr['lang'], 0, 2));

			$ret[$lang] = trim((string)$value);
		}

		// make sure that the default language will return a value
		if (!array_key_exists(self::DEFAULT_LANGUAGE, $ret) && count($ret) > 0)
			$ret[self::DEFAULT_LANGUAGE] = array_values($ret)[0];

		return $ret;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function parsePreference(SimpleXMLElement $pref): array
	{
		$ret = [];

		if (isset($pref['value']))
			$ret['value'] = (string) $pref['value'];

		// IAdea stuff
		foreach($pref->attributes('ia', true) as $key => $value)
		{
			$ret[$key] = (string) $value;
		}

		$type = $ret['types'] ?? '';

		foreach ($pref->children('ia', true) as $child)
		{
			$tag_name = $child->getName();
			switch ($tag_name)
			{
				case 'note':
					/** @var array<string,mixed> $ret */
					if (array_key_exists('notes', $ret))
						$ret['notes'] = array_merge($ret['notes'], $this->generateNotesArray($child));
					else
						$ret['notes'] = $this->generateNotesArray($child);
					continue 2;

				case 'options':
					/** @var array<string,mixed> $ret */
					if ($type === 'radio' || $type === 'list')
					{
						if (array_key_exists('options', $ret))
							$ret['options'] = array_merge($ret['options'], $this->generateRadioOptionArray($child));
						else
							$ret['options'] = $this->generateRadioOptionArray($child);
					}
					else if ($type === 'combo')
					{
						if (array_key_exists('options', $ret))
							$ret['options'] = array_merge($ret['options'], [(string) $child => (string) $child]);
						else
							$ret['options'] = [(string) $child => (string) $child];
					}

					continue 2;
			}

		}
		return $ret;
	}

	/**
	 * @return string
	 */
	protected function getModuleName(): string
	{
		return 'widget_config_xml';
	}

	/**
	 * @return array<string,string>
	 */
	private function generateNotesArray(SimpleXMLElement $note): array
	{
		$attr = $note->attributes('xml', true);
		$lang = self::DEFAULT_LANGUAGE;
		if (isset($attr['lang']))
			$lang = strtolower(substr((string)$attr['lang'], 0, 2));

		return [$lang => trim((string)$note)];

	}

	/**
	 * @return array<string,mixed>
	 */
	private function generateRadioOptionArray(SimpleXMLElement $option): array
	{
		$key = '';
		$value = '';
		foreach($option->attributes() as $k => $v)
		{
			if ($k == 'resourceKey')
				$key = (string) $v;
			else if ($k == 'value')
				$value = (string) $v;
		}

		return [$key => $value];
	}

	/**
	 * @param array<string,mixed> $ar
	 */
	private function checkLanguageKeyOfArray(array $ar, string $lang): string
	{
		if ($ar === [])
			return '';

		$lang = substr(strtolower($lang), 0, 2);
		if (!array_key_exists($lang, $ar))
			return $ar[self::DEFAULT_LANGUAGE];

		return $ar[$lang];
	}

}