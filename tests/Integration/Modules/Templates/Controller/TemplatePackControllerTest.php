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

namespace Tests\Integration\Modules\Templates\Controller;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Media\DataUrlDecoder;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Controller\TemplatePackController;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Repositories\TemplatesRepository;
use App\Modules\Templates\Services\AclValidator;
use App\Modules\Templates\Services\TemplatePackService;
use PHPUnit\Framework\Attributes\Group;
use Slim\Flash\Messages;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The button of the template overview which installs the bundled templates /async/templates/pack.
 * The pack of a test has two templates of the real one, the images are stored in a temporary directory.
 */
#[Group('acl')]
#[Group('io')]
class TemplatePackControllerTest extends TemplatesControllerTestCase
{
	private const string BUNDLED_PACK = __DIR__ . '/../../../../../resources/templates';

	private MediaWorkspace $workspace;
	private string $packDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		foreach (['originals', 'thumbs'] as $sub)
			mkdir($this->workspace->dir . '/var/storage/templates/' . $sub, 0775, true);

		$this->packDir = $this->workspace->dir . '/pack';
		mkdir($this->packDir . '/fabric', 0775, true);
		$this->addToPack('quote_landscape');
		$this->addToPack('wifi_portrait');
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	private function addToPack(string $name): void
	{
		foreach (['json', 'jpg'] as $extension)
			copy(self::BUNDLED_PACK . '/fabric/' . $name . '.' . $extension, $this->packDir . '/fabric/' . $name . '.' . $extension);
	}

	private function controller(?string $packDir = null): TemplatePackController
	{
		$service = new TemplatePackService(
			$this->container->get(TemplatesRepository::class),
			$this->container->get(AclValidator::class),
			$this->container->get(UserSession::class),
			new ExportImage($this->container->get(DataUrlDecoder::class), $this->workspace->handlerFactory()),
			$this->container->get(Transactions::class),
			$packDir ?? $this->packDir
		);

		return new TemplatePackController(
			$service,
			$this->container->get(JsonResponseHandler::class),
			$this->container->get(Translator::class),
			$this->container->get(Messages::class)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function install(?string $packDir = null): array
	{
		$response = $this->controller($packDir)->install($this->request('POST', '/async/templates/pack'), $this->newResponse());
		$this->assertSuccess($response);

		return $this->json($response);
	}

	/**
	 * @return list<string>
	 */
	private function storedImages(string $sub): array
	{
		$files = array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/templates/' . $sub . '/*'));
		sort($files);

		return $files;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function packTemplates(): array
	{
		return $this->connection->fetchAllAssociative('SELECT * FROM templates WHERE seed_key IS NOT NULL ORDER BY template_id');
	}

	public function testTheModuleAdminInstallsThePackAsHisGlobalTemplates(): void
	{
		$this->actingAs(1);

		$result = $this->install();

		static::assertSame(2, $result['installed']);
		static::assertSame([$result['message']], $this->flash('success'));
		static::assertStringContainsString('2', (string) $result['message']);

		$templates = $this->packTemplates();
		static::assertSame(['fabric/quote_landscape', 'fabric/wifi_portrait'], array_column($templates, 'seed_key'));
		static::assertSame(['Quote (landscape)', 'Wi-Fi Access (portrait)'], array_column($templates, 'name'));
		foreach ($templates as $template)
		{
			static::assertSame(1, (int) $template['UID']);
			static::assertSame('global', $template['visibility']);
			static::assertSame('canvas', $template['type']);
			static::assertNotSame('', $template['description']);
		}

		$content = json_decode((string) $templates[1]['content'], true);
		static::assertIsArray($content);
		static::assertSame(['width' => 1080, 'height' => 1920, 'scale' => 100], $content['viewport']);

		$expected = array_map(static fn(array $template): string => $template['template_id'] . '.jpg', $templates);
		sort($expected);
		static::assertSame($expected, $this->storedImages('originals'));
		static::assertSame($expected, $this->storedImages('thumbs'));
	}

	public function testASecondInstallationAddsNothing(): void
	{
		$this->actingAs(1);
		$this->install();
		$_SESSION['slimFlash'] = [];
		$before = $this->countTemplates();

		$result = $this->install();

		static::assertSame(0, $result['installed']);
		static::assertSame($this->text('template_pack_up_to_date'), $result['message']);
		static::assertSame([], $this->flash('success'));
		static::assertSame($before, $this->countTemplates());
	}

	public function testADeletedTemplateComesBackAndANewOneOfAnUpdateIsAdded(): void
	{
		$this->actingAs(1);
		$this->install();
		$this->connection->executeStatement("DELETE FROM templates WHERE seed_key = 'fabric/quote_landscape'");
		$this->addToPack('menu_landscape');

		$result = $this->install();

		static::assertSame(2, $result['installed']);
		static::assertSame(['fabric/wifi_portrait', 'fabric/menu_landscape', 'fabric/quote_landscape'], array_column($this->packTemplates(), 'seed_key'));
	}

	public function testOnlyAModuleAdminInstallsThePack(): void
	{
		$this->actingAs(2);
		$before = $this->countTemplates();

		$response = $this->controller()->install($this->request('POST', '/async/templates/pack'), $this->newResponse());

		$this->assertError($response, $this->text('template_pack_not_installable'));
		static::assertSame($before, $this->countTemplates());
		static::assertSame([], $this->storedImages('originals'));
	}

	public function testATemplateWhoseImageCanNotBeStoredIsNotInstalled(): void
	{
		$this->actingAs(1);
		file_put_contents($this->packDir . '/fabric/wifi_portrait.jpg', 'this is no image');

		try
		{
			$this->controller()->install($this->request('POST', '/async/templates/pack'), $this->newResponse());
			static::fail('A broken image must stop the installation.');
		}
		catch (ModuleException $e)
		{
			static::assertStringContainsString('fabric/wifi_portrait', $e->getMessage());
		}

		// the one before stays, the broken one is gone with its row
		static::assertSame(['fabric/quote_landscape'], array_column($this->packTemplates(), 'seed_key'));
	}

	public function testATemplateWithoutImageIsNotInstalled(): void
	{
		$this->actingAs(1);
		unlink($this->packDir . '/fabric/quote_landscape.jpg');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('fabric/quote_landscape');

		try
		{
			$this->controller()->install($this->request('POST', '/async/templates/pack'), $this->newResponse());
		}
		finally
		{
			static::assertSame([], $this->packTemplates());
		}
	}

	public function testTheBundledPackInstallsCompletely(): void
	{
		$this->actingAs(1);

		$result = $this->install(self::BUNDLED_PACK);

		$templates = $this->packTemplates();
		static::assertSame(24, $result['installed']);
		static::assertCount(24, $templates);
		static::assertCount(24, array_unique(array_column($templates, 'name')));
		static::assertCount(24, $this->storedImages('thumbs'));
	}
}
