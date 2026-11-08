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

namespace Tests\Blackbox\Modules\Mediapool;

use App\Framework\Media\MediaUrlSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use ZipArchive;

/**
 * The preview of widgets (widget.php): the files come signed out of the archive, the widget runs
 * in a sandbox of its own and starts with the values of config.xml.
 */
#[Group('blackbox')]
class WidgetDeliveryTest extends MediapoolTestCase
{
	private const string SECRET = 'blackbox-secret-0123456789abcdef';
	private const string SCRIPT = 'document.title = "widget";';
	private const string CONFIG = <<<'XML'
		<?xml version="1.0" encoding="UTF-8"?>
		<widget xmlns="http://www.w3.org/ns/widgets" id="http://example.com/feed" version="1.0">
			<name>Feed</name>
			<icon src="icon.png"/>
			<content src="start.html"/>
			<preference name="feedUrl" value="https://example.com/rss"/>
			<preference name="font_size" value="30"/>
		</widget>
		XML;

	private function fetch(string $address, string $method = 'GET'): ResponseInterface
	{
		return $this->anna->request($method, $address, ['allow_redirects' => false]);
	}

	private static function widget(): string
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'wgt');
		$zip  = new ZipArchive();
		$zip->open($file, ZipArchive::OVERWRITE);
		$zip->addFromString('config.xml', self::CONFIG);
		$zip->addFromString('start.html', '<html><script src="js/app.js"></script></html>');
		$zip->addFromString('js/app.js', self::SCRIPT);
		$zip->addFromString('icon.png', self::png());
		$zip->close();

		$bytes = (string) file_get_contents($file);
		unlink($file);

		return $bytes;
	}

	/**
	 * @return array{string,string} the address of the preview and the checksum
	 */
	private function uploadedWidget(): array
	{
		$answer = $this->json($this->upload('feed.wgt', self::widget(), mime: 'application/octet-stream'));
		static::assertTrue($answer['success'] ?? false, json_encode($answer, JSON_THROW_ON_ERROR));
		foreach ($this->mediaOf() as $media)
		{
			if ($media['filename'] === 'feed.wgt')
				return [$media['urls']['preview'], $media['checksum']];
		}

		static::fail('The widget is not in the list.');
	}

	public function testThePreviewStartsWithTheStartFileAndTheValuesOfConfigXml(): void
	{
		[$address] = $this->uploadedWidget();

		$response = $this->fetch($address);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('start.html?feedUrl=https%3A%2F%2Fexample.com%2Frss&font_size=30', $response->getHeaderLine('Location'));
	}

	public function testValuesOfTheQueryWinOverConfigXml(): void
	{
		[$address] = $this->uploadedWidget();

		$response = $this->fetch($address . '?font_size=12');

		static::assertSame('start.html?font_size=12&feedUrl=https%3A%2F%2Fexample.com%2Frss', $response->getHeaderLine('Location'));
	}

	public function testAFileOfTheWidgetIsDeliveredSandboxed(): void
	{
		[$address] = $this->uploadedWidget();

		$response = $this->fetch($address . 'js/app.js');

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(self::SCRIPT, (string) $response->getBody());
		static::assertSame('text/javascript; charset=utf-8', $response->getHeaderLine('Content-Type'));
		static::assertSame("sandbox allow-scripts; frame-ancestors 'self'", $response->getHeaderLine('Content-Security-Policy'));
		static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
		static::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
	}

	public function testAMissingFileIsNotFound(): void
	{
		[$address] = $this->uploadedWidget();

		static::assertSame(404, $this->fetch($address . 'js/missing.js')->getStatusCode());
	}

	public function testAWrongSignatureDeliversNothing(): void
	{
		[$address] = $this->uploadedWidget();
		$changed = (string) preg_replace('#/[0-9a-f]{64}/#', '/' . str_repeat('0', 64) . '/', $address);

		$response = $this->fetch($changed . 'js/app.js');

		static::assertSame(403, $response->getStatusCode());
		static::assertStringNotContainsString(self::SCRIPT, (string) $response->getBody());
	}

	public function testTheSignatureOfAnotherVariantDeliversNothing(): void
	{
		[, $checksum] = $this->uploadedWidget();
		$signed = (new MediaUrlSigner(self::SECRET, 3600))->sign($checksum, 'wgt', MediaUrlSigner::VARIANT_ORIGINAL);

		$response = $this->fetch("/widget.php/$checksum/{$signed['exp']}/{$signed['sig']}/js/app.js");

		static::assertSame(403, $response->getStatusCode());
	}

	public function testAnExpiredSignatureDeliversNothing(): void
	{
		[, $checksum] = $this->uploadedWidget();
		$signed = (new MediaUrlSigner(self::SECRET, 3600))->sign($checksum, 'wgt', MediaUrlSigner::VARIANT_WIDGET, time() - 86400);

		$response = $this->fetch("/widget.php/$checksum/{$signed['exp']}/{$signed['sig']}/js/app.js");

		static::assertSame(403, $response->getStatusCode());
	}

	/**
	 * @return array<string,array{string}> paths inside the widget which try to leave it
	 */
	public static function pathTricks(): array
	{
		return [
			'parent directory'     => ['../../../../.env'],
			'a dot segment'        => ['./js/app.js'],
			'an empty segment'     => ['js//app.js'],
			'an absolute path'     => ['/etc/passwd'],
			'a backslash'          => ['js\\app.js'],
		];
	}

	#[DataProvider('pathTricks')]
	public function testAPathWhichLeavesTheWidgetDeliversNothing(string $path): void
	{
		[, $checksum] = $this->uploadedWidget();
		$signed = (new MediaUrlSigner(self::SECRET, 3600))->sign($checksum, 'wgt', MediaUrlSigner::VARIANT_WIDGET);

		$response = $this->rawGet("/widget.php/$checksum/{$signed['exp']}/{$signed['sig']}/" . $path);

		static::assertContains($response['status'], [400, 404], $path);
		static::assertStringNotContainsString('APP_SECRET', $response['body']);
	}
}
