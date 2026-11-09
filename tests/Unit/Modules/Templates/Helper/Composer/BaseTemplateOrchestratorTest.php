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

namespace Tests\Unit\Modules\Templates\Helper\Composer;

use App\Framework\Config\Config;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Mediapool\Services\MediaService;
use App\Modules\Templates\Helper\Composer\BaseTemplateOrchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TestableTemplateOrchestrator extends BaseTemplateOrchestrator
{
	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	/**
	 * @param list<array<string,mixed>> $objects
	 * @return array<mixed> the objects of the restored content
	 */
	public function callRestoreSrc(array $objects): array
	{
		$restored = json_decode($this->restoreContent((string) json_encode(['objects' => $objects])), true);
		return is_array($restored) ? $restored['objects'] : [];
	}

	public function callRestoreContent(string $content): string
	{
		return $this->restoreContent($content);
	}

	public function callValidate(string $content): string
	{
		return $this->validate($content);
	}

	public function callBuildMediaUrl(string $fileName): string
	{
		return $this->buildMediaUrl($fileName);
	}
}

class BaseTemplateOrchestratorTest extends TestCase
{
	private Config&Stub $configStub;
	private MediaUrlSigner $signer;
	/** @var array<string,mixed> */
	private array $server;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->server     = $_SERVER;
		$this->configStub = $this->createStub(Config::class);
		$this->signer     = new MediaUrlSigner('unit-test-secret');
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
		parent::tearDown();
	}

	#[Group('units')]
	public function testBaseUrlFromConfig(): void
	{

		static::assertSame('https://cdn.example.com', $this->createOrchestrator('https://cdn.example.com/')->getBaseUrl());
	}

	#[Group('units')]
	public function testBaseUrlFromHttpsRequest(): void
	{
		$_SERVER['HTTPS']     = 'on';
		$_SERVER['HTTP_HOST'] = 'hub.example.com';
		unset($_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);

		static::assertSame('https://hub.example.com', $this->createOrchestrator('')->getBaseUrl());
	}

	#[Group('units')]
	public function testBaseUrlBehindProxy(): void
	{
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_SSL']);
		$_SERVER['HTTP_HOST']              = 'internal:8080';
		$_SERVER['HTTP_X_FORWARDED_HOST']  = 'hub.example.com';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

		static::assertSame('https://hub.example.com', $this->createOrchestrator('')->getBaseUrl());
	}

	#[Group('units')]
	public function testBaseUrlFromHttpRequest(): void
	{
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);
		$_SERVER['HTTP_HOST'] = 'localhost';

		static::assertSame('http://localhost', $this->createOrchestrator('')->getBaseUrl());
	}

	#[Group('units')]
	public function testBuildMediaUrl(): void
	{
		$url = $this->createOrchestrator('https://cdn.example.com')->callBuildMediaUrl('abc123.jpg');

		// the address of the content server and the signed path of the original
		static::assertMatchesRegularExpression('#^https://cdn\.example\.com/media\.php/orig/abc123\.jpg\?exp=\d+&sig=[0-9a-f]{64}$#', $url);
	}

	#[Group('units')]
	public function testBuildMediaUrlWithoutExtension(): void
	{

		static::assertSame('', $this->createOrchestrator('https://cdn.example.com')->callBuildMediaUrl('abc123'));
	}

	#[Group('units')]
	public function testRestoreSrcInNestedGroups(): void
	{
		$objects = [
			['type' => 'image', 'fileName' => 'a.png'],
			['type' => 'text'],
			['type' => 'group', 'objects' => [['type' => 'image', 'fileName' => 'b.jpg']]],
		];

		$restored = $this->createOrchestrator('https://cdn')->callRestoreSrc($objects);

		static::assertMatchesRegularExpression('#^https://cdn/media\.php/orig/a\.png\?exp=\d+&sig=[0-9a-f]{64}$#', $restored[0]['src']);
		static::assertArrayNotHasKey('src', $restored[1]);
		static::assertMatchesRegularExpression('#^https://cdn/media\.php/orig/b\.jpg\?exp=\d+&sig=[0-9a-f]{64}$#', $restored[2]['objects'][0]['src']);
		static::assertSame('a.png', $restored[0]['fileName']);
	}

	/**
	 * An image placeholder has no file of the mediapool, its picture comes from the hub. A replaced placeholder
	 * has a file name and gets the file.
	 */
	#[Group('units')]
	public function testRestoreSrcOfPlaceholders(): void
	{
		$objects = [
			['type' => 'image', 'placeholder' => true],
			['type' => 'group', 'objects' => [['type' => 'image', 'placeholder' => true]]],
			['type' => 'image', 'placeholder' => true, 'fileName' => 'a.png'],
			['type' => 'image', 'placeholder' => 'true'],
		];

		$restored = $this->createOrchestrator('https://cdn')->callRestoreSrc($objects);

		static::assertSame('/images/templates/placeholder.png', $restored[0]['src']);
		static::assertSame('/images/templates/placeholder.png', $restored[1]['objects'][0]['src']);
		static::assertStringStartsWith('https://cdn/media.php/orig/a.png', $restored[2]['src']);
		static::assertArrayNotHasKey('src', $restored[3]);
	}

	#[Group('units')]
	public function testValidateRemovesSrcRecursively(): void
	{
		$content = (string) json_encode([
			'version' => '6',
			'objects' => [
				['type' => 'image', 'fileName' => 'a.png', 'src' => 'javascript:alert(1)'],
				['type' => 'group', 'objects' => [['type' => 'image', 'src' => 'https://evil']]],
			]
		]);

		static::assertSame(
			(string) json_encode(['version' => '6', 'objects' => [
				['type' => 'image', 'fileName' => 'a.png'],
				['type' => 'group', 'objects' => [['type' => 'image']]],
			]]),
			$this->createOrchestrator('https://cdn')->callValidate($content)
		);
	}

	/**
	 * The styles of a text of fabric.js 5 are an object of lines and characters. With sequential keys it would become a
	 * list when decoded as an array, which fabric.js reads as ranges: the text lost its size and colors.
	 */
	#[Group('units')]
	public function testTheObjectsOfTheContentStayObjects(): void
	{
		$content = '{"objects":[{"type":"textbox","text":"Rot","styles":{"0":{"0":{"fill":"#ff0000"},"1":{"fill":"#ff0000"}}}},'
			. '{"type":"textbox","text":"x","styles":{}},{"type":"textbox","text":"y","styles":[]}],"viewport":{"width":1920,"height":1080,"scale":100}}';
		$orchestrator = $this->createOrchestrator('https://cdn');

		static::assertSame($content, $orchestrator->callValidate($content));
		static::assertSame($content, $orchestrator->callRestoreContent($content));
	}

	#[Group('units')]
	public function testRestoreContentRejectsInvalidContent(): void
	{
		$orchestrator = $this->createOrchestrator('https://cdn');

		static::assertSame('', $orchestrator->callRestoreContent('no json'));
		static::assertSame('', $orchestrator->callRestoreContent('[]'));
		static::assertSame('', $orchestrator->callRestoreContent('{"objects":{"a":1}}'));
	}

	#[Group('units')]
	public function testValidateRejectsInvalidContent(): void
	{
		$orchestrator = $this->createOrchestrator('https://cdn');

		static::assertSame('', $orchestrator->callValidate('no json'));
		static::assertSame('', $orchestrator->callValidate('{"version":"6"}'));
		static::assertSame('', $orchestrator->callValidate('{"objects":"text"}'));
		static::assertSame('', $orchestrator->callValidate((string) json_encode(['objects' => array_fill(0, 1001, ['type' => 'text'])])));
	}

	#[Group('units')]
	public function testValidateAcceptsMaximumObjects(): void
	{
		$content = (string) json_encode(['objects' => array_fill(0, 1000, ['type' => 'text'])]);

		static::assertSame($content, $this->createOrchestrator('https://cdn')->callValidate($content));
	}

	private function createOrchestrator(string $configuredUrl): TestableTemplateOrchestrator
	{
		$this->configStub->method('getConfigValue')->willReturnMap([['url', 'mediapool', 'content_server', $configuredUrl]]);

		return new TestableTemplateOrchestrator($this->configStub, $this->signer, $this->createStub(MediaService::class));
	}
}
