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

use App\Framework\Core\Config\Config;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Templates\Helper\Composer\BaseTemplateOrchestrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TestableTemplateOrchestrator extends BaseTemplateOrchestrator
{
	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	/**
	 * @param array $objects
	 * @return array
	 */
	public function callRestoreSrc(array $objects): array
	{
		$this->restoreSrc($objects);
		return $objects;
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
	private MediaUrlSigner&MockObject $signerMock;
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
		$this->signerMock = $this->createMock(MediaUrlSigner::class);
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;
		parent::tearDown();
	}

	#[Group('units')]
	public function testBaseUrlFromConfig(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());

		static::assertSame('https://cdn.example.com', $this->createOrchestrator('https://cdn.example.com/')->getBaseUrl());
	}

	#[Group('units')]
	public function testBaseUrlFromHttpsRequest(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());
		$_SERVER['HTTPS']     = 'on';
		$_SERVER['HTTP_HOST'] = 'hub.example.com';
		unset($_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);

		static::assertSame('https://hub.example.com', $this->createOrchestrator('')->getBaseUrl());
	}

	#[Group('units')]
	public function testBaseUrlBehindProxy(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_SSL']);
		$_SERVER['HTTP_HOST']              = 'internal:8080';
		$_SERVER['HTTP_X_FORWARDED_HOST']  = 'hub.example.com';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

		static::assertSame('https://hub.example.com', $this->createOrchestrator('')->getBaseUrl());
	}

	#[Group('units')]
	public function testBaseUrlFromHttpRequest(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());
		unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_SSL']);
		$_SERVER['HTTP_HOST'] = 'localhost';

		static::assertSame('http://localhost', $this->createOrchestrator('')->getBaseUrl());
	}

	#[Group('units')]
	public function testBuildMediaUrl(): void
	{
		$this->signerMock->expects($this->once())->method('originalUrl')->with('abc123', 'jpg')->willReturn('/media/abc123.jpg?sig=1');

		static::assertSame('https://cdn.example.com/media/abc123.jpg?sig=1', $this->createOrchestrator('https://cdn.example.com')->callBuildMediaUrl('abc123.jpg'));
	}

	#[Group('units')]
	public function testBuildMediaUrlWithoutExtension(): void
	{
		$this->signerMock->expects($this->never())->method('originalUrl');

		static::assertSame('', $this->createOrchestrator('https://cdn.example.com')->callBuildMediaUrl('abc123'));
	}

	#[Group('units')]
	public function testRestoreSrcInNestedGroups(): void
	{
		$this->signerMock->expects($this->exactly(2))->method('originalUrl')
			->willReturnCallback(fn(string $checksum, string $extension): string => '/m/' . $checksum . '.' . $extension);

		$objects = [
			['type' => 'image', 'fileName' => 'a.png'],
			['type' => 'text'],
			['type' => 'group', 'objects' => [['type' => 'image', 'fileName' => 'b.jpg']]],
		];

		static::assertSame([
			['type' => 'image', 'fileName' => 'a.png', 'src' => 'https://cdn/m/a.png'],
			['type' => 'text'],
			['type' => 'group', 'objects' => [['type' => 'image', 'fileName' => 'b.jpg', 'src' => 'https://cdn/m/b.jpg']]],
		], $this->createOrchestrator('https://cdn')->callRestoreSrc($objects));
	}

	#[Group('units')]
	public function testValidateRemovesSrcRecursively(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());
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

	#[Group('units')]
	public function testValidateRejectsInvalidContent(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());
		$orchestrator = $this->createOrchestrator('https://cdn');

		static::assertSame('', $orchestrator->callValidate('no json'));
		static::assertSame('', $orchestrator->callValidate('{"version":"6"}'));
		static::assertSame('', $orchestrator->callValidate('{"objects":"text"}'));
		static::assertSame('', $orchestrator->callValidate((string) json_encode(['objects' => array_fill(0, 1001, ['type' => 'text'])])));
	}

	#[Group('units')]
	public function testValidateAcceptsMaximumObjects(): void
	{
		$this->signerMock->expects($this->never())->method($this->anything());
		$content = (string) json_encode(['objects' => array_fill(0, 1000, ['type' => 'text'])]);

		static::assertSame($content, $this->createOrchestrator('https://cdn')->callValidate($content));
	}

	private function createOrchestrator(string $configuredUrl): TestableTemplateOrchestrator
	{
		$this->configStub->method('getConfigValue')->willReturnMap([['url', 'mediapool', 'content_server', $configuredUrl]]);

		return new TestableTemplateOrchestrator($this->configStub, $this->signerMock);
	}
}
