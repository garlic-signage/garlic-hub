<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every place in the own JavaScript which puts a string into the page as HTML (innerHTML, outerHTML,
 * insertAdjacentHTML, document.write, HTML in a string) is on a list. The browser tests only cover a few pages,
 * so this test makes a new place visible: whoever adds one has to say here, why it is safe.
 *
 * The places which build HTML from data of users are listed as known, until they are changed to textContent
 * and createElement.
 */
class JavaScriptSinksTest extends TestCase
{
	private const string SINK = '/innerHTML|outerHTML|insertAdjacentHTML|document\.write|[\'"`]<[a-zA-Z]/';

	/**
	 * Safe: nothing is put in, numbers and times, a copy of a part of the page, fixed data.
	 *
	 * @var array<string,list<string>> file => the lines (trimmed, the first 100 characters)
	 */
	private const array SAFE = [
		'mediapool/selector/MediaSelectorView.js'                         => ['this.#mediaListElement.innerHTML = "";'],
		'core/Autocomplete.js'                                            => ["this.#autocompleteView.datalistElement.innerHTML = '';", "this.#autocompleteView.datalistElement.innerHTML = ''; // Clear existing options"],
		'playlists/compose/standard/editors/ConditionalPlaySlider.js'     => [
			'this.#rangeFrom.innerHTML = this.#convertMinutesToTime(Number(positions[0]) * 15)',
			'this.#rangeUntil.innerHTML = this.#convertMinutesToTime(Number(positions[1]) * 15)',
			'this.#rangeFrom.innerHTML = ""',
			'this.#rangeUntil.innerHTML = ""',
			'that.#rangeFrom.innerHTML = that.#convertMinutesToTime(values[0] * 15);',
			'that.#rangeUntil.innerHTML = that.#convertMinutesToTime(values[1] * 15);',
		],
		'mediapool/media/MediaList.js'                                    => ['this.#mediaListElement.innerHTML = ""; // Clear previous content'],
		// the content is the form of a widget (WidgetForm, see the list SAFE) or of a trigger and a conditional play (fixed)
		'playlists/compose/standard/editors/EditDialog.js'                => ['this.#itemContentElement.innerHTML = content;', 'this.#itemContentElement.innerHTML = "";'],
		'mediapool/uploads/StockPlatform/StockPlatformUploader.js'        => ['this.domElements.searchResultsArea.innerHTML = "";'],
		'playlists/compose/standard/playlists/PlaylistsProperties.js'     => [
			'this.#playlistDuration.innerHTML = Utils.formatSecondsToTime(playlistMetrics.duration);',
			'this.#totalItems.innerHTML       = playlistMetrics.count_items;',
			'this.#totalFilesize.innerHTML    = Utils.formatBytes(playlistMetrics.filesize);',
			'this.#shufflePicking.innerHTML = "";',
		],
		'templates/selectors/TemplatesSelectorView.js'                    => ['this.#templatesListElement.innerHTML = "";'],
		'playlists/compose/standard/selectors/PlaylistsSelectorView.js'   => ['this.#playlistsListElement.innerHTML = "";'],
		// the list of the fonts is a fixed file of the application
		'templates/canvas-composer/Controller/Properties/TextPropertiesController.js' => ['div.innerHTML = this.#fontLoader.fontsList[i].preview'],
		// the labels, options and values of the widget and of the user are escaped by WidgetForm.#escape()
		'playlists/compose/standard/editors/WidgetForm.js'                => [
			'this.html_form = \'<ul class="form_edit">\';',
			'this.html_form += "<li><label>" + WidgetForm.#escape(label) + "<br />";',
			'return \'<input type="text"\' + \' id="\' + this.#id(key) + \'"\' + \' name="\' + WidgetForm.#escape(key) + ',
			'html += \'<input type="radio"\' + \' id="\' + this.#id(key + "_" + options_key) + \'"\' + \' name="\' + Widg',
			'return \'<input type="number"\' + \' id="\' + this.#id(key) + \'"\' + \' name="\' + WidgetForm.#escape(key) ',
			'return \'<input type="color"\' + \' id="\' + this.#id(key) + \'"\' + \' name="\' + WidgetForm.#escape(key) +',
			'let html = \'<select id="\' + this.#id(key) + \'" name="\' + WidgetForm.#escape(key) + \'">\';',
			'html += \'<option value="\'+ WidgetForm.#escape(options_key) +\'"\' + selected + \'>\'  + WidgetForm.#esca',
		],
	];

	/**
	 * Not safe: data of users or of other servers (names, labels and values of widgets, fonts of a template) is put in
	 * as HTML. The CSP stops scripts and inline handlers, not the injection of elements.
	 *
	 * @var array<string,list<string>> file => the lines
	 */
	private const array KNOWN = [];

	/**
	 * @return array<string,list<string>> the places in the own JavaScript, file => lines (trimmed, 100 characters)
	 */
	private function places(): array
	{
		$root   = (string) realpath(__DIR__ . '/../../public/js');
		$places = [];
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file)
		{
			$relative = substr($file->getPathname(), strlen($root) + 1);
			// libraries are not ours, the generated list of the fonts is a fixed file, the WASM glue is built by garlic-player
			if (!str_ends_with($relative, '.js') || str_ends_with($relative, '.min.js') || str_starts_with($relative, 'external/')
				|| str_contains($relative, 'canvas-composer/Fonts/') || $relative === 'player/preview/garlic-core.js')
				continue;

			foreach ((array) file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $line)
			{
				if (preg_match(self::SINK, (string) $line) === 1)
					$places[$relative][] = substr(trim((string) $line), 0, 100);
			}
		}
		ksort($places);

		return $places;
	}

	/**
	 * @param array<string,list<string>> $places
	 * @return list<string>
	 */
	private static function flat(array $places): array
	{
		$flat = [];
		foreach ($places as $file => $lines)
		{
			foreach ($lines as $line)
				$flat[] = $file . ': ' . substr($line, 0, 100);
		}

		return $flat;
	}

	public function testEveryPlaceWhichBuildsHtmlIsOnTheList(): void
	{
		$unknown = array_diff(self::flat($this->places()), self::flat(self::SAFE), self::flat(self::KNOWN));

		static::assertSame([], array_values($unknown), "These places put a string into the page as HTML.\nIf the string comes from a user or from another server, use textContent and createElement. If it is safe, put the line on the list SAFE of this test with the reason.");
	}

	public function testTheListHasNoPlaceWhichIsGone(): void
	{
		$gone = array_diff(array_merge(self::flat(self::SAFE), self::flat(self::KNOWN)), self::flat($this->places()));

		static::assertSame([], array_values($gone), 'These lines are on the list, but not in the JavaScript anymore. Take them from the list.');
	}

	public function testNoPlaceBuildsHtmlFromDataOfUsers(): void
	{
		static::assertSame([], self::flat(self::KNOWN));
	}
}
