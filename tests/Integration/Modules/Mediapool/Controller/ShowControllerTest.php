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

namespace Tests\Integration\Modules\Mediapool\Controller;

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\MustacheAdapter;
use App\Framework\TemplateEngine\MustacheEngineFactory;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Controller\ShowController;
use App\Modules\Mediapool\Services\NodesService;
use PHPUnit\Framework\Attributes\Group;

/**
 * The page of the mediapool: the data of the controller and the template which shows them belong together.
 */
#[Group('acl')]
class ShowControllerTest extends ControllerTestCase
{
	private function show(?string $edition = null): PageResponse
	{
		$controller = $this->container->get(ShowController::class);
		if ($edition !== null)
		{
			$root       = (string) realpath(__DIR__ . '/../../../../..');
			$controller = new ShowController(
				$this->container->get(NodesService::class),
				$this->container->get(UserSession::class),
				$this->container->get(Translator::class),
				new Config(new IniConfigLoader($root . '/config/settings'), ['systemDir' => $root], ['APP_PLATFORM_EDITION' => $edition])
			);
		}
		$response = $controller->show($this->request('GET'), $this->newResponse());
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	private function html(PageResponse $page): string
	{
		$adapter = new MustacheAdapter(new MustacheEngineFactory()->create(__DIR__ . '/../../../../../templates'));

		return $adapter->render($page->getTemplate(), $page->getTemplateData());
	}

	public function testThePageHasItsTemplateTitleAndScripts(): void
	{
		$this->actingAs(2);

		$page = $this->show();

		static::assertSame('mediapool/overview', $page->getTemplate());
		$layout = $page->getMainLayoutData();
		static::assertSame($this->container->get(Translator::class)->translate('mediapool', 'menu'), $layout['LANG_PAGE_TITLE']);
		static::assertContains('/css/mediapool/overview.css', $layout['additional_css']);
		static::assertSame(['/js/mediapool/init.js'], $layout['footer_modules']);
		static::assertContains('/js/external/glightbox.min.js', $layout['footer_scripts']);
	}

	/**
	 * Every text of the page is translated: an empty text would be an empty button.
	 */
	public function testEveryTextOfThePageIsThere(): void
	{
		$this->actingAs(2);

		$texts = array_filter($this->show()->getTemplateData(), static fn(string $key): bool => str_starts_with($key, 'LANG_') && $key !== 'LANG_JS', ARRAY_FILTER_USE_KEY);

		static::assertGreaterThan(50, count($texts));
		foreach ($texts as $key => $text)
			static::assertNotSame('', $text, $key . ' is empty');
	}

	public function testOnlyTheAdminMayAddRootFolders(): void
	{
		$this->actingAs(2);
		$user = $this->show();
		$this->actingAs(1);
		$admin = $this->show();

		static::assertArrayNotHasKey('add_root_folder', $user->getTemplateData());
		static::assertArrayNotHasKey('LANG_ADD_ROOT_FOLDER', $user->getTemplateData());
		static::assertArrayHasKey('add_root_folder', $admin->getTemplateData());
		static::assertNotSame('', $admin->getTemplateData()['LANG_ADD_ROOT_FOLDER']);
		// the text is in the texts for the scripts for everybody, the link to add a root folder is only for the admin
		static::assertStringNotContainsString('id="addRootFolder"', $this->html($user));
		static::assertStringContainsString('id="addRootFolder"', $this->html($admin));
	}

	public function testTheTextsForTheScriptsAreValidJsonWhichCanNotLeaveTheScriptTag(): void
	{
		$this->actingAs(2);

		$json = $this->show()->getTemplateData()['LANG_JS'];

		static::assertIsArray(json_decode($json, true));
		static::assertEqualsCanonicalizing(['add_root_folder', 'add_sub_folder', 'edit_folder', 'start_record_video', 'stop_record_video'], array_keys(json_decode($json, true)));
		static::assertStringNotContainsString('<', $json);
		static::assertStringNotContainsString('>', $json);
	}

	public function testTheEditionEdgeHasNoChoiceOfTheVisibility(): void
	{
		$this->actingAs(2);

		static::assertArrayNotHasKey('folder_dialog_visibility', $this->show()->getTemplateData());
		static::assertArrayNotHasKey('folder_dialog_visibility', $this->show(Config::PLATFORM_EDITION_EDGE)->getTemplateData());
	}

	public function testTheEditionEnterpriseOffersTheVisibilityInTheFolderDialog(): void
	{
		$this->actingAs(2);

		$dialog = $this->show(Config::PLATFORM_EDITION_ENTERPRISE)->getTemplateData()['folder_dialog_visibility'];

		static::assertNotSame('', $dialog['LANG_FOLDER_VISIBILITY']);
		static::assertNotSame([], $dialog['visibility_options']);
	}

	public function testThePageIsRenderedWithTheTextsOfTheController(): void
	{
		$this->actingAs(2);
		$page = $this->show();

		$html = $this->html($page);

		static::assertStringContainsString($page->getTemplateData()['LANG_START_UPLOAD'], $html);
		static::assertStringContainsString($page->getTemplateData()['LANG_UPLOAD_LOCAL_FILES'], $html);
	}
}
