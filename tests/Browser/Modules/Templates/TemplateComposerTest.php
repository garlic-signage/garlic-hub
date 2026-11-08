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

namespace Tests\Browser\Modules\Templates;

use Facebook\WebDriver\WebDriverBy;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Browser\Support\BrowserTestCase;

/**
 * The canvas composer of a template, used like a user does: the mouse on the canvas, the menus, the dialogs and the
 * keyboard. The results are read from the saved content in the database, never from the objects of fabric.js, so
 * the tests stay the same when fabric.js changes (e.g. the update from 5 to 7).
 *
 * The bundled templates of resources/templates/fabric are the content of fabric.js 5.4.2 which must still load.
 * The admin uses the templates (core edition); the picture "Strand" (400 x 200) lies in the folder Fotos (8) of anna.
 */
#[Group('browser')]
class TemplateComposerTest extends BrowserTestCase
{
	private const string GROUP_CONTENT = '{"objects":[{"type":"group","left":10,"top":10,"objects":['
		. '{"type":"rect","left":0,"top":0,"width":100,"height":50,"fill":"#ff0000"},'
		. '{"type":"textbox","left":0,"top":60,"width":200,"text":"Hallo","fontFamily":"Arial","styles":[]}]}],'
		. '"viewport":{"width":1920,"height":1080,"scale":100}}';

	private const string MEDIA_ID = 'media-id-strand0';

	/** media.php serves only names of hexadecimal checksums */
	private string $checksum = '';

	/** the size of the template in the composer, read from its content */
	private int $templateWidth = 1920;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
	}

	// ---------------------------------------------------------------------------------------------------------------
	// the helpers

	/**
	 * The content of a bundled template, as the pack installs it. With $fabric5 the same template as fabric.js
	 * 5.4.2 saved it, as it is in the database of older installations.
	 */
	private static function packContent(string $key, bool $fabric5 = false): string
	{
		$directory = $fabric5 ? dirname(__DIR__, 3) . '/resources/templates/fabric5/' : dirname(__DIR__, 4) . '/resources/templates/fabric/';
		$pack      = json_decode((string) file_get_contents($directory . $key . '.json'), true);
		static::assertIsArray($pack);

		return (string) json_encode($pack['content']);
	}

	private function createTemplate(string $content, int $templateId = 4): void
	{
		$this->connection->insert('templates', ['template_id' => $templateId, 'UID' => 1, 'visibility' => 'private', 'type' => 'fabric.js', 'name' => 'Test', 'content' => $content]);
		$json = json_decode($content, true);
		static::assertIsArray($json);
		$this->templateWidth = (int) $json['viewport']['width'];
	}

	/**
	 * A real picture in the storage of the deployment, so that the composer can load it.
	 */
	private function createPicture(): void
	{
		$image = new Imagick();
		$image->newImage(400, 200, new ImagickPixel('#2a9d8f'));
		$image->setImageFormat('png');
		$this->checksum = hash('sha256', $image->getImageBlob());
		$storage = $this->deployment()->directory() . '/var/storage/mediapool';
		file_put_contents($storage . '/originals/' . $this->checksum . '.png', $image->getImageBlob());
		file_put_contents($storage . '/thumbs/' . $this->checksum . '.png', $image->getImageBlob());

		$this->connection->insert('mediapool_files', [
			'media_id' => self::MEDIA_ID, 'node_id' => 8, 'UID' => 2, 'checksum' => $this->checksum, 'mimetype' => 'image/png',
			'filename' => 'strand.png', 'extension' => 'png', 'thumb_extension' => 'png',
			'metadata' => '{"size":1000,"dimensions":{"width":400,"height":200}}'
		]);
	}

	private function openComposer(string $path = '/templates/composer/4'): void
	{
		$this->visit($path);
		$this->waitForVisible('#save');
		// the overlay stops when the template is loaded
		$this->waitForGone('#waitOverlay');
	}

	/**
	 * The offset of a point of the template from the middle of the canvas, as WebDriver counts it.
	 *
	 * @return array{int, int}
	 */
	private function canvasOffset(float $x, float $y): array
	{
		$size = $this->client()->executeScript('const r = document.querySelector(".upper-canvas").getBoundingClientRect(); return [r.width, r.height];');
		static::assertIsArray($size);
		$zoom = (float) $size[0] / $this->templateWidth;

		return [(int) round($x * $zoom - (float) $size[0] / 2), (int) round($y * $zoom - (float) $size[1] / 2)];
	}

	private function clickCanvas(float $x, float $y): void
	{
		[$dx, $dy] = $this->canvasOffset($x, $y);
		$this->actions()->moveToElement($this->upperCanvas(), $dx, $dy)->click()->perform();
	}

	private function contextClickCanvas(float $x, float $y): void
	{
		[$dx, $dy] = $this->canvasOffset($x, $y);
		$this->actions()->moveToElement($this->upperCanvas(), $dx, $dy)->contextClick()->perform();
	}

	private function upperCanvas(): \Facebook\WebDriver\WebDriverElement
	{
		return $this->client()->getWebDriver()->findElement(WebDriverBy::cssSelector('.upper-canvas'));
	}

	private function content(int $templateId = 4): string
	{
		return (string) $this->connection->fetchOne('SELECT content FROM templates WHERE template_id = ?', [$templateId]);
	}

	/**
	 * Saves and waits until the content is in the database: the content there is replaced by a mark before, so an
	 * unchanged template is seen as saved, too. The composer has its content already.
	 *
	 * @return array<string,mixed> the saved content
	 */
	private function save(): array
	{
		$this->connection->update('templates', ['content' => 'not saved'], ['template_id' => 4]);
		$this->click('#save');
		$this->waitForGone('#waitOverlay');
		$this->client()->getWebDriver()->wait(10)->until(fn() => $this->content() !== 'not saved');

		$json = json_decode($this->content(), true);
		static::assertIsArray($json);

		return $json;
	}

	/**
	 * @param array<string,mixed> $content
	 * @return list<array<string,mixed>>
	 */
	private static function objectsOfType(array $content, string $type): array
	{
		return array_values(array_filter($content['objects'], static fn(array $object): bool => $object['type'] === $type || strtolower((string) $object['type']) === $type));
	}

	/**
	 * Replaces the image at the point with the picture "Strand" by "replace image" of its context menu.
	 */
	private function replaceImageAt(float $x, float $y): void
	{
		$this->clickCanvas($x, $y);
		$this->contextClickCanvas($x, $y);
		$this->click('#replaceImage');
		$this->chooseStrandInTheMediaDialog();
		$this->click('#applyMedia');
		$this->waitForGone('#mediaSelectorDialog');
	}

	/**
	 * The picture "Strand" in the folder Fotos of anna, selected in the open media dialog.
	 */
	private function chooseStrandInTheMediaDialog(): void
	{
		$this->waitForVisible('#mediaSelectorDialog');
		$driver = $this->client()->getWebDriver();
		$folder = "//dialog[@id='mediaSelectorDialog']//span[contains(@class, 'wb-title') and text()='%s']";
		$this->client()->waitFor(sprintf($folder, 'anna'), 10);
		$driver->findElement(WebDriverBy::xpath(sprintf($folder, 'anna') . "/preceding-sibling::i[contains(@class, 'wb-expander')]"))->click();
		$this->client()->waitFor(sprintf($folder, 'Fotos'), 10);
		$driver->findElement(WebDriverBy::xpath(sprintf($folder, 'Fotos')))->click();
		$this->click('#mediaSelectorDialog .media-item');
	}

	/**
	 * The number of hints of the design check, after it ran a moment after the last change.
	 */
	private function designHints(): int
	{
		usleep(900000);
		$count = trim($this->text('#designCheckCount'));
		$shown = (bool) $this->client()->executeScript('return !document.getElementById("designCheckCount").hidden;');

		return $shown ? (int) $count : 0;
	}

	/**
	 * The characters of a text with the color: fabric.js 5 stores the styles per line and character, 6 and later as
	 * ranges.
	 *
	 * @param array<mixed> $styles
	 */
	private static function charactersWithFill(array $styles, string $fill): int
	{
		$count = 0;
		foreach ($styles as $key => $value)
		{
			if (!is_array($value))
				continue;
			if (isset($value['start'], $value['end']))
				$count += ($value['style']['fill'] ?? '') === $fill ? (int) $value['end'] - (int) $value['start'] : 0;
			else
				foreach ($value as $style)
					$count += is_array($style) && ($style['fill'] ?? '') === $fill ? 1 : 0;
		}

		return $count;
	}

	/**
	 * The size of an image on the template, whatever the format of fabric.js stores.
	 *
	 * @param array<string,mixed> $image
	 * @return array{float, float}
	 */
	private static function displaySize(array $image): array
	{
		return [(float) $image['width'] * (float) ($image['scaleX'] ?? 1), (float) $image['height'] * (float) ($image['scaleY'] ?? 1)];
	}

	// ---------------------------------------------------------------------------------------------------------------
	// the tests

	public function testATemplateWithAGroupIsLoadedAndSaved(): void
	{
		$this->createTemplate(self::GROUP_CONTENT);
		$this->login('admin');
		$this->openComposer();

		$content = $this->save();

		$this->assertNoErrors();
		$group = self::objectsOfType($content, 'group')[0];
		static::assertSame(['rect', 'textbox'], array_map(static fn(array $object): string => strtolower((string) $object['type']), $group['objects']));
		static::assertSame('Hallo', $group['objects'][1]['text']);
	}

	/**
	 * @return array<string, array{bool}>
	 */
	public static function formatProvider(): array
	{
		return ['fabric.js 5.4.2' => [true], 'the bundled one' => [false]];
	}

	/**
	 * A template keeps its objects, texts, fonts, colors and the marks of its placeholders, also one of fabric.js
	 * 5.4.2.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('formatProvider')]
	public function testABundledTemplateIsLoadedAndSavedWithoutLosses(bool $fabric5): void
	{
		$original = json_decode(self::packContent('menu_landscape', $fabric5), true);
		static::assertIsArray($original);
		$this->createTemplate(self::packContent('menu_landscape', $fabric5));
		$this->login('admin');
		$this->openComposer();

		$content = $this->save();

		$this->assertNoErrors();
		$summary = static fn(array $object): array => [
			strtolower((string) $object['type']), $object['text'] ?? null, $object['fontFamily'] ?? null, $object['fontSize'] ?? null,
			is_string($object['fill'] ?? null) ? $object['fill'] : null, round((float) $object['left']), round((float) $object['top'])
		];
		static::assertSame(array_map($summary, $original['objects']), array_map($summary, $content['objects']));
		$image = self::objectsOfType($content, 'image')[0];
		static::assertTrue($image['placeholder']);
		// the server stores no address of a picture, it builds it on every load
		static::assertArrayNotHasKey('src', $image);
	}

	/**
	 * The colors of single characters of a text of fabric.js 5 stay.
	 */
	public function testTheStylesOfCharactersAreKept(): void
	{
		$this->createTemplate('{"objects":[{"type":"textbox","left":100,"top":100,"width":600,"text":"Rot und blau","fontFamily":"Arial","fontSize":60,'
			. '"fill":"#0000ff","styles":{"0":{"0":{"fill":"#ff0000"},"1":{"fill":"#ff0000"},"2":{"fill":"#ff0000"}}}}],'
			. '"viewport":{"width":1920,"height":1080,"scale":100}}');
		$this->login('admin');
		$this->openComposer();

		$content = $this->save();

		$this->assertNoErrors();
		$text = self::objectsOfType($content, 'textbox')[0];
		static::assertSame('Rot und blau', $text['text']);
		static::assertSame(3, self::charactersWithFill($text['styles'], '#ff0000'), 'The red characters are lost: ' . json_encode($text['styles']));
	}

	public function testAnInsertedTextIsSaved(): void
	{
		$this->createTemplate(self::GROUP_CONTENT);
		$this->login('admin');
		$this->openComposer();

		$this->click('#insertText');
		$content = $this->save();

		$this->assertNoErrors();
		static::assertCount(1, self::objectsOfType($content, 'textbox'));
	}

	/**
	 * The picture fills the area of the placeholder, cut in the middle; the design check has one hint less.
	 * Menu landscape: the placeholder is 600 x 840 at 1200, 120.
	 */
	public function testAPlaceholderIsReplacedAndFilled(): void
	{
		$this->createTemplate(self::packContent('menu_landscape'));
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();
		$hintsBefore = $this->designHints();
		static::assertGreaterThanOrEqual(1, $hintsBefore);

		$this->replaceImageAt(1500, 540);
		static::assertSame($hintsBefore - 1, $this->designHints());
		$content = $this->save();

		$this->assertNoErrors();
		$image = self::objectsOfType($content, 'image')[0];
		static::assertSame(self::MEDIA_ID, $image['mediaId']);
		static::assertSame($this->checksum . '.png', $image['fileName']);
		static::assertFalse($image['placeholder']);
		[$width, $height] = self::displaySize($image);
		static::assertEqualsWithDelta(600, $width, 1);
		static::assertEqualsWithDelta(840, $height, 1);
		static::assertEqualsWithDelta(1200, (float) $image['left'], 1);
		static::assertEqualsWithDelta(120, (float) $image['top'], 1);
		// 400 x 200 covers 600 x 840 with the factor 4.2: 143 x 200 of the middle
		static::assertEqualsWithDelta(4.2, (float) $image['scaleX'], 0.01);
		static::assertEqualsWithDelta((400 - 600 / 4.2) / 2, (float) $image['cropX'], 1);
		static::assertEqualsWithDelta(0, (float) $image['cropY'], 1);
	}

	/**
	 * The round placeholder of "New colleague" keeps its circle of 280 radius at 820, 540.
	 */
	public function testARoundPlaceholderKeepsItsCircle(): void
	{
		// the circle of fabric.js 5 is loaded, too
		$this->createTemplate(self::packContent('new_colleague_landscape', true));
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();

		$this->replaceImageAt(820, 540);
		$content = $this->save();

		$this->assertNoErrors();
		$image = self::objectsOfType($content, 'image')[0];
		[$width, $height] = self::displaySize($image);
		static::assertEqualsWithDelta(560, $width, 1);
		static::assertEqualsWithDelta(560, $height, 1);
		$clip   = $image['clipPath'];
		$radius = (float) $clip['radius'] * (float) ($clip['scaleX'] ?? 1) * (float) $image['scaleX'];
		static::assertEqualsWithDelta(280, $radius, 1);
	}

	/**
	 * Undo brings the placeholder back, redo the picture; the cut stays after the template is opened again.
	 */
	public function testAReplacementIsOneStepOfTheHistoryAndStays(): void
	{
		$this->createTemplate(self::packContent('menu_landscape'));
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();

		$this->replaceImageAt(1500, 540);
		$this->click('#undo');
		static::assertTrue(self::objectsOfType($this->save(), 'image')[0]['placeholder']);

		$this->click('#redo');
		$replaced = self::objectsOfType($this->save(), 'image')[0];
		static::assertSame(self::MEDIA_ID, $replaced['mediaId']);

		// opened again: the picture with its cut, from the signed address of the server
		$this->openComposer();
		$again = self::objectsOfType($this->save(), 'image')[0];
		static::assertSame(self::MEDIA_ID, $again['mediaId']);
		static::assertEqualsWithDelta((float) $replaced['cropX'], (float) $again['cropX'], 0.5);
		static::assertEqualsWithDelta((float) $replaced['scaleX'], (float) $again['scaleX'], 0.001);
		$this->assertNoErrors();
	}

	public function testAnImageIsInsertedFromTheMediapool(): void
	{
		$this->createTemplate(self::GROUP_CONTENT);
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();

		$this->click('#insertImage');
		$this->chooseStrandInTheMediaDialog();
		$this->click('#addMedia');
		$this->waitForGone('#mediaSelectorDialog');
		$content = $this->save();

		$this->assertNoErrors();
		$images = self::objectsOfType($content, 'image');
		static::assertCount(1, $images);
		static::assertSame(self::MEDIA_ID, $images[0]['mediaId']);
		static::assertSame($this->checksum . '.png', $images[0]['fileName']);
		static::assertArrayNotHasKey('src', $images[0]);
	}

	public function testHoveringAPictureOfTheMediaDialogShowsItsThumbnailAboveTheDialog(): void
	{
		$this->createTemplate(self::GROUP_CONTENT);
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();
		$driver = $this->client()->getWebDriver();

		$this->click('#insertImage');
		$this->chooseStrandInTheMediaDialog();
		// the click on the picture hid the preview: the mouse leaves it and comes back
		$this->actions()->moveToElement($driver->findElement(WebDriverBy::cssSelector('#mediaSelectorDialog .dialog-name')))->perform();
		$this->actions()->moveToElement($driver->findElement(WebDriverBy::cssSelector('#mediaSelectorDialog .media-thumbnail')))->perform();

		// inside the modal dialog, otherwise it would lie below it
		$this->waitForVisible('#mediaSelectorDialog .thumbnail-preview.visible');
		static::assertStringContainsString('/media.php/thumb/' . $this->checksum, (string) $driver->findElement(WebDriverBy::cssSelector('.thumbnail-preview.visible'))->getAttribute('src'));

		$this->actions()->moveToElement($driver->findElement(WebDriverBy::cssSelector('#mediaSelectorDialog .dialog-name')))->perform();
		$this->waitForGone('.thumbnail-preview.visible');
		$this->assertNoErrors();
	}

	/**
	 * The template of a playlist item: its content is saved at the item and its picture exported for the players.
	 * Item 5 of the playlist 1 of anna.
	 */
	public function testAPlaylistItemIsComposedAndExported(): void
	{
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/items.sql');
		$this->connection->update('playlists_items', ['content_data' => self::packContent('welcome_landscape')], ['item_id' => 5]);
		$exports = $this->deployment()->directory() . '/var/storage/playlists/items/originals';
		$this->login('admin');
		$this->openComposer('/playlist-item/composer/5');

		$this->connection->update('playlists_items', ['content_data' => 'not saved'], ['item_id' => 5]);
		$this->click('#save');
		$this->waitForGone('#waitOverlay');
		$this->client()->getWebDriver()->wait(10)->until(fn() => $this->connection->fetchOne('SELECT content_data FROM playlists_items WHERE item_id = 5') !== 'not saved');

		$this->assertNoErrors();
		$content = json_decode((string) $this->connection->fetchOne('SELECT content_data FROM playlists_items WHERE item_id = 5'), true);
		static::assertIsArray($content);
		static::assertSame(['Welcome', 'to Company Name'], array_slice(array_column(self::objectsOfType($content, 'textbox'), 'text'), 0, 2));
		static::assertNotSame([], glob($exports . '/5*'), 'No picture was exported for the players.');
	}

	/**
	 * Two rectangles: red 100..500 x 100..400, blue 300..700 x 200..500 above it.
	 */
	private const string TWO_RECTS = '{"objects":['
		. '{"type":"rect","left":100,"top":100,"width":400,"height":300,"fill":"#ff0000","scaleX":1,"scaleY":1},'
		. '{"type":"rect","left":300,"top":200,"width":400,"height":300,"fill":"#0000ff","scaleX":1,"scaleY":1}],'
		. '"viewport":{"width":1920,"height":1080,"scale":100}}';

	/**
	 * A copy of an image keeps its file, otherwise it would have no picture after the next load.
	 */
	public function testADuplicatedImageKeepsItsFile(): void
	{
		$this->createTemplate(self::packContent('menu_landscape'));
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();
		$this->replaceImageAt(1500, 540);

		$this->contextClickCanvas(1500, 540);
		$this->click('#duplicate');
		$images = self::objectsOfType($this->save(), 'image');

		$this->assertNoErrors();
		static::assertCount(2, $images);
		static::assertSame([self::MEDIA_ID, self::MEDIA_ID], array_column($images, 'mediaId'));
		static::assertSame([$this->checksum . '.png', $this->checksum . '.png'], array_column($images, 'fileName'));
		static::assertEqualsWithDelta((float) $images[0]['left'] + 20, (float) $images[1]['left'], 1);
	}

	public function testAnObjectIsMovedToTheBackground(): void
	{
		$this->createTemplate(self::TWO_RECTS);
		$this->login('admin');
		$this->openComposer();

		$this->clickCanvas(600, 450);
		$this->contextClickCanvas(600, 450);
		$this->click('#moveBackground');
		$content = $this->save();

		$this->assertNoErrors();
		static::assertSame(['#0000ff', '#ff0000'], array_column($content['objects'], 'fill'));
	}

	/**
	 * Two objects selected with the shift key become a group, the group becomes the two objects again at their
	 * places.
	 */
	public function testObjectsAreGroupedAndUngrouped(): void
	{
		$this->createTemplate(self::TWO_RECTS);
		$this->login('admin');
		$this->openComposer();

		$this->clickCanvas(150, 150);
		[$dx, $dy] = $this->canvasOffset(650, 450);
		$this->actions()->keyDown(null, \Facebook\WebDriver\WebDriverKeys::SHIFT)->moveToElement($this->upperCanvas(), $dx, $dy)->click()->keyUp(null, \Facebook\WebDriver\WebDriverKeys::SHIFT)->perform();
		$this->click('#object_group');
		$grouped = $this->save();

		static::assertCount(1, $grouped['objects']);
		static::assertSame('group', strtolower((string) $grouped['objects'][0]['type']));
		static::assertCount(2, $grouped['objects'][0]['objects']);

		$this->clickCanvas(400, 300);
		$this->click('#object_group');
		$ungrouped = $this->save();

		$this->assertNoErrors();
		static::assertSame(['#ff0000', '#0000ff'], array_column($ungrouped['objects'], 'fill'));
		static::assertEqualsWithDelta(100, (float) $ungrouped['objects'][0]['left'], 1);
		static::assertEqualsWithDelta(200, (float) $ungrouped['objects'][1]['top'], 1);
	}

	/**
	 * The contrast is measured on what is drawn behind the text: grey on white is too weak, on black it is enough.
	 */
	public function testTheContrastIsMeasuredBehindTheText(): void
	{
		$this->createTemplate('{"objects":['
			. '{"type":"rect","left":1000,"top":0,"width":920,"height":1080,"fill":"#000000","scaleX":1,"scaleY":1},'
			. '{"type":"textbox","left":100,"top":100,"width":600,"text":"Grau","fontFamily":"Arial","fontSize":80,"fill":"#bbbbbb","scaleX":1,"scaleY":1,"styles":[]}],'
			. '"viewport":{"width":1920,"height":1080,"scale":100}}');
		$this->login('admin');
		$this->openComposer();
		static::assertSame(1, $this->designHints());

		// the text onto the black area
		[$fromX, $fromY] = $this->canvasOffset(150, 140);
		[$toX, $toY]     = $this->canvasOffset(1250, 140);
		$this->actions()->moveToElement($this->upperCanvas(), $fromX, $fromY)->clickAndHold()->moveByOffset(($toX - $fromX) / 2, 0)->moveByOffset(($toX - $fromX) / 2, 0)->release()->perform();

		static::assertSame(0, $this->designHints());
		$this->assertNoErrors();
	}

	/**
	 * The picture "Strand" in the placeholder of the menu (600 x 840 at 1200, 120): 400 x 200 scaled by 4.2, the
	 * middle 142.86 x 200 is shown from cropX 128.57.
	 */
	private function openMenuWithPicture(): void
	{
		$this->createTemplate(self::packContent('menu_landscape'));
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();
		$this->replaceImageAt(1500, 540);
	}

	/**
	 * Drags with the left mouse button on the canvas, in units of the template.
	 */
	private function dragCanvas(float $fromX, float $fromY, float $toX, float $toY): void
	{
		[$x1, $y1] = $this->canvasOffset($fromX, $fromY);
		[$x2, $y2] = $this->canvasOffset($toX, $toY);
		$this->actions()->moveToElement($this->upperCanvas(), $x1, $y1)->clickAndHold()
			->moveByOffset(intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2))
			->moveByOffset($x2 - $x1 - intdiv($x2 - $x1, 2), $y2 - $y1 - intdiv($y2 - $y1, 2))
			->release()->perform();
	}

	private function pressKey(string $key): void
	{
		$this->actions()->sendKeys(null, $key)->perform();
	}

	/**
	 * In the crop mode the picture moves in its frame: the frame stays, the cut changes.
	 */
	public function testAPictureIsMovedInItsFrame(): void
	{
		$this->openMenuWithPicture();

		$this->contextClickCanvas(1500, 540);
		$this->click('#cropImage');
		// to the left: more of the right part of the picture
		$this->dragCanvas(1500, 540, 1440, 540);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ENTER);
		$image = self::objectsOfType($this->save(), 'image')[0];

		$this->assertNoErrors();
		static::assertEqualsWithDelta(1200, (float) $image['left'], 1);
		static::assertEqualsWithDelta(120, (float) $image['top'], 1);
		[$width, $height] = self::displaySize($image);
		static::assertEqualsWithDelta(600, $width, 1);
		static::assertEqualsWithDelta(840, $height, 1);
		static::assertEqualsWithDelta(128.57 + 60 / 4.2, (float) $image['cropX'], 2);
		static::assertEqualsWithDelta(0, (float) $image['cropY'], 0.5);
	}

	/**
	 * The picture can not leave its frame empty: dragged far, it stops at its edge.
	 */
	public function testAPictureStopsAtItsEdge(): void
	{
		$this->openMenuWithPicture();

		$this->contextClickCanvas(1500, 540);
		$this->click('#cropImage');
		// 600 on the template are 143 of the picture, only 128 are left
		$this->dragCanvas(1500, 540, 900, 540);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ENTER);
		$image = self::objectsOfType($this->save(), 'image')[0];

		static::assertEqualsWithDelta(400 - 600 / 4.2, (float) $image['cropX'], 1);
		static::assertEqualsWithDelta(1200, (float) $image['left'], 1);
	}

	/**
	 * A corner of the whole picture zooms it in the frame; the round frame of "New colleague" keeps its size.
	 * After the replace the picture is 1120 x 560 from 260, 260 behind the circle of 560 at 540, 260.
	 */
	public function testACornerZoomsThePictureAndTheCircleStays(): void
	{
		$this->createTemplate(self::packContent('new_colleague_landscape'));
		$this->createPicture();
		$this->login('admin');
		$this->openComposer();
		$this->replaceImageAt(820, 540);

		$this->contextClickCanvas(820, 540);
		$this->click('#cropImage');
		// the anchor is the opposite corner 260, 260: (1520 - 260) / 400 = (890 - 260) / 200 = 3.15
		$this->dragCanvas(1380, 820, 1520, 890);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ENTER);
		$image = self::objectsOfType($this->save(), 'image')[0];

		$this->assertNoErrors();
		static::assertEqualsWithDelta(3.15, (float) $image['scaleX'], 0.05);
		[$width, $height] = self::displaySize($image);
		static::assertEqualsWithDelta(560, $width, 2);
		static::assertEqualsWithDelta(560, $height, 2);
		$clip = $image['clipPath'];
		static::assertEqualsWithDelta(280, (float) $clip['radius'] * (float) $clip['scaleX'] * (float) $image['scaleX'], 1);
	}

	/**
	 * Moved near the left edge of the template, the rectangle snaps to it.
	 */
	public function testAnObjectSnapsToTheEdgeOfTheTemplate(): void
	{
		$this->createTemplate(self::TWO_RECTS);
		$this->login('admin');
		$this->openComposer();

		// the red one 95 to the left: 5 from the edge
		$this->clickCanvas(150, 150);
		$this->dragCanvas(150, 150, 55, 150);
		$red = $this->save()['objects'][0];

		$this->assertNoErrors();
		static::assertSame(0.0, (float) $red['left']);
		static::assertEqualsWithDelta(100, (float) $red['top'], 2);
	}

	/**
	 * @return array<string, array{float, float, float, float, float, float, float, float}>
	 */
	public static function edgeProvider(): array
	{
		// the frame 600 x 840 at 1200, 120: the edge from - to, then left, top, width, height
		return [
			'right'  => [1800, 540, 1600, 540, 1200, 120, 400, 840],
			'left'   => [1200, 540, 1400, 540, 1400, 120, 400, 840],
			'bottom' => [1500, 960, 1500, 760, 1200, 120, 600, 640],
			'top'    => [1500, 120, 1500, 320, 1200, 320, 600, 640],
		];
	}

	/**
	 * An edge of an image changes its frame, the opposite edge stays: the picture fills it, cut in the middle, not
	 * distorted.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('edgeProvider')]
	public function testTheEdgeOfAnImageChangesItsFrameWithoutDistortion(float $fromX, float $fromY, float $toX, float $toY, float $left, float $top, float $width, float $height): void
	{
		$this->openMenuWithPicture();

		$this->clickCanvas(1500, 540);
		$this->dragCanvas($fromX, $fromY, $toX, $toY);
		$image = self::objectsOfType($this->save(), 'image')[0];

		$this->assertNoErrors();
		[$savedWidth, $savedHeight] = self::displaySize($image);
		static::assertEqualsWithDelta($width, $savedWidth, 3);
		static::assertEqualsWithDelta($height, $savedHeight, 3);
		static::assertEqualsWithDelta($left, (float) $image['left'], 3);
		static::assertEqualsWithDelta($top, (float) $image['top'], 3);
		static::assertEqualsWithDelta((float) $image['scaleX'], (float) $image['scaleY'], 0.0001, 'The picture is distorted.');
	}

	/**
	 * In the crop mode the left and the top edge cut the frame, too, the picture stays where it is.
	 */
	public function testTheLeftAndTopEdgeCutTheFrameInTheCropMode(): void
	{
		$this->openMenuWithPicture();

		$this->contextClickCanvas(1500, 540);
		$this->click('#cropImage');
		$this->dragCanvas(1200, 540, 1300, 540);
		// the middle of the top edge of the frame 1300 to 1800
		$this->dragCanvas(1550, 120, 1550, 220);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ENTER);
		$image = self::objectsOfType($this->save(), 'image')[0];

		$this->assertNoErrors();
		[$width, $height] = self::displaySize($image);
		static::assertEqualsWithDelta(500, $width, 3);
		static::assertEqualsWithDelta(740, $height, 3);
		static::assertEqualsWithDelta(1300, (float) $image['left'], 3);
		static::assertEqualsWithDelta(220, (float) $image['top'], 3);
		static::assertEqualsWithDelta(4.2, (float) $image['scaleX'], 0.01);
		// the picture did not move: 100 more of the left are cut away
		static::assertEqualsWithDelta(128.57 + 100 / 4.2, (float) $image['cropX'], 2);
	}

	/**
	 * A click on a hint of the design check selects the element and shows its properties.
	 */
	public function testAHintOfTheDesignCheckShowsThePropertiesOfTheElement(): void
	{
		$this->createTemplate('{"objects":[{"type":"textbox","left":100,"top":100,"width":800,"text":"Klein","fontFamily":"Arial","fontSize":30,"fill":"#000000","styles":{}}],'
			. '"viewport":{"width":1920,"height":1080,"scale":100}}');
		$this->login('admin');
		$this->openComposer();
		static::assertSame(1, $this->designHints());

		$this->click('#designCheckButton');
		$this->click('#designCheckHints .design-check-element');

		$this->waitForVisible('#fontSize');
		static::assertSame('30', $this->client()->getWebDriver()->findElement(\Facebook\WebDriver\WebDriverBy::id('fontSize'))->getAttribute('value'));
		$this->assertNoErrors();
	}

	/**
	 * Ctrl+C and twice Ctrl+V: two copies with the file of the image, each a little further.
	 */
	public function testAnImageIsCopiedAndPastedTwice(): void
	{
		$this->openMenuWithPicture();

		$this->clickCanvas(1500, 540);
		$this->actions()->keyDown(null, \Facebook\WebDriver\WebDriverKeys::CONTROL)->sendKeys(null, 'c')->keyUp(null, \Facebook\WebDriver\WebDriverKeys::CONTROL)->perform();
		usleep(200000);
		for ($i = 0; $i < 2; $i++)
		{
			$this->actions()->keyDown(null, \Facebook\WebDriver\WebDriverKeys::CONTROL)->sendKeys(null, 'v')->keyUp(null, \Facebook\WebDriver\WebDriverKeys::CONTROL)->perform();
			usleep(200000);
		}
		$images = self::objectsOfType($this->save(), 'image');

		$this->assertNoErrors();
		static::assertCount(3, $images);
		static::assertSame(array_fill(0, 3, self::MEDIA_ID), array_column($images, 'mediaId'));
		static::assertEqualsWithDelta([1200, 1220, 1240], array_map(static fn(array $image): float => (float) $image['left'], $images), 1);
	}

	/**
	 * While a text is edited, Delete removes the selected word, not the text box.
	 */
	public function testTheKeysBelongToAnEditedText(): void
	{
		$this->createTemplate('{"objects":[{"type":"textbox","left":100,"top":100,"width":800,"text":"Hallo Welt","fontFamily":"Arial","fontSize":80,"fill":"#000000","scaleX":1,"scaleY":1,"styles":[]}],'
			. '"viewport":{"width":1920,"height":1080,"scale":100}}');
		$this->login('admin');
		$this->openComposer();

		// the first click selects, the double click edits and selects the word "Hallo"
		$this->clickCanvas(150, 140);
		[$dx, $dy] = $this->canvasOffset(150, 140);
		$this->actions()->moveToElement($this->upperCanvas(), $dx, $dy)->doubleClick()->perform();
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::DELETE);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ESCAPE);
		$texts = self::objectsOfType($this->save(), 'textbox');

		$this->assertNoErrors();
		static::assertCount(1, $texts, 'The text box was deleted instead of the word.');
		static::assertSame(' Welt', $texts[0]['text']);
	}

	public function testEscapeCancelsTheCrop(): void
	{
		$this->openMenuWithPicture();

		$this->contextClickCanvas(1500, 540);
		$this->click('#cropImage');
		$this->dragCanvas(1500, 540, 1440, 540);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ESCAPE);
		$image = self::objectsOfType($this->save(), 'image')[0];

		$this->assertNoErrors();
		static::assertEqualsWithDelta(128.57, (float) $image['cropX'], 0.5);
		static::assertEqualsWithDelta(1200, (float) $image['left'], 1);
	}

	/**
	 * A double click starts the crop mode, the edge of the frame cuts it without changing the picture; undo takes
	 * back the whole crop in one step.
	 */
	public function testAFrameIsCutAndTheCropIsOneStepOfTheHistory(): void
	{
		$this->openMenuWithPicture();

		[$dx, $dy] = $this->canvasOffset(1500, 540);
		$this->actions()->moveToElement($this->upperCanvas(), $dx, $dy)->doubleClick()->perform();
		// the right edge 100 to the left, and the picture a little to the left
		$this->dragCanvas(1800, 540, 1700, 540);
		$this->dragCanvas(1450, 540, 1420, 540);
		$this->pressKey(\Facebook\WebDriver\WebDriverKeys::ENTER);
		$cut = self::objectsOfType($this->save(), 'image')[0];

		[$width, $height] = self::displaySize($cut);
		static::assertEqualsWithDelta(500, $width, 3);
		static::assertEqualsWithDelta(840, $height, 1);
		static::assertEqualsWithDelta(1200, (float) $cut['left'], 1);
		static::assertEqualsWithDelta(4.2, (float) $cut['scaleX'], 0.01);

		$this->click('#undo');
		$undone = self::objectsOfType($this->save(), 'image')[0];
		$this->assertNoErrors();
		static::assertSame(self::MEDIA_ID, $undone['mediaId'], 'Undo went back further than the crop.');
		[$width] = self::displaySize($undone);
		static::assertEqualsWithDelta(600, $width, 1);
		static::assertEqualsWithDelta(128.57, (float) $undone['cropX'], 0.5);
	}

	/**
	 * A font size set without a selection belongs to the text itself: the design check sees it at once.
	 * The text has no scale, like a content which was not saved by fabric.js.
	 */
	public function testAFontSizeOfTheTextClearsTheHintOfTheDesignCheck(): void
	{
		$this->createTemplate('{"objects":[{"type":"textbox","left":100,"top":100,"width":800,"text":"Klein","fontFamily":"Arial","fontSize":30,"fill":"#000000","styles":{}}],'
			. '"viewport":{"width":1920,"height":1080,"scale":100}}');
		$this->login('admin');
		$this->openComposer();
		static::assertSame(1, $this->designHints());

		// the middle of the first line
		$this->clickCanvas(130, 117);
		$this->waitForVisible('#fontSize');
		$input = $this->client()->getWebDriver()->findElement(WebDriverBy::id('fontSize'));
		$input->clear();
		$input->sendKeys('40');
		static::assertSame(0, $this->designHints());

		$text = self::objectsOfType($this->save(), 'textbox')[0];
		static::assertSame(40, (int) $text['fontSize']);
		static::assertStringNotContainsString('fontSize', (string) json_encode($text['styles']));
		$this->assertNoErrors();
	}
}
