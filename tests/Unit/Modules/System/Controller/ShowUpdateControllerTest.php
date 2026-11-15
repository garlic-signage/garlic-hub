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

namespace Tests\Unit\Modules\System\Controller;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Framework\System\AppVersion;
use App\Framework\System\SelfUpdate;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\System\Controller\ShowUpdateController;
use App\Modules\System\Services\AclValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;

/**
 * The update page: who sees it, what it shows of the status of garlic-hub-update and the request of the button.
 * SelfUpdate works on a temporary directory, the translator gives module.key and the replacements back.
 */
class ShowUpdateControllerTest extends TestCase
{
	private string $dir;
	private string $updateDir;
	/** @var array<mixed>|\ArrayAccess<string,mixed>|null the session array of the flash messages */
	private array|\ArrayAccess|null $flashStorage = [];
	private bool $isModuleAdmin = true;
	private bool $enabled = true;
	private string $timeZone;

	protected function setUp(): void
	{
		parent::setUp();
		$this->timeZone = date_default_timezone_get();
		$this->dir       = sys_get_temp_dir() . '/garlic-update-page-' . bin2hex(random_bytes(4));
		$this->updateDir = $this->dir . '/update';
		mkdir($this->updateDir, 0777, true);
		file_put_contents($this->dir . '/VERSION', "1.2.0\n");
	}

	protected function tearDown(): void
	{
		foreach ((array) glob($this->updateDir . '/*') as $file)
			unlink((string) $file);
		rmdir($this->updateDir);
		unlink($this->dir . '/VERSION');
		rmdir($this->dir);
		date_default_timezone_set($this->timeZone);
		parent::tearDown();
	}

	private function controller(): ShowUpdateController
	{
		$session = new MemorySession();
		$session->set('user', ['UID' => 1, 'username' => 'admin', 'locale' => 'en_US']);

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(
			static fn(string $key, string $module, array $replacements = []): string => $module . '.' . $key . ($replacements === [] ? '' : json_encode($replacements))
		);
		$csrfTokenStub = $this->createStub(CsrfToken::class);
		$csrfTokenStub->method('getToken')->willReturn('the_token');
		$aclValidatorStub = $this->createStub(AclValidator::class);
		$aclValidatorStub->method('isModuleAdmin')->willReturn($this->isModuleAdmin);

		return new ShowUpdateController(
			new SelfUpdate($this->enabled, $this->updateDir),
			new AppVersion($this->dir),
			$aclValidatorStub,
			new UserSession($session),
			$translator,
			$csrfTokenStub,
			new Messages($this->flashStorage)
		);
	}

	private function show(): ResponseInterface
	{
		return $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/system/update'), new Response());
	}

	private function request(): ResponseInterface
	{
		return $this->controller()->request(new ServerRequestFactory()->createServerRequest('POST', '/system/update'), new Response());
	}

	/**
	 * @return array<string,list<string>>
	 */
	private function flash(): array
	{
		return is_array($this->flashStorage) ? ($this->flashStorage['slimFlash'] ?? []) : [];
	}

	private function writeStatus(string $state, string $step = ''): void
	{
		file_put_contents($this->updateDir . '/status.json', (string) json_encode(['state' => $state, 'step' => $step, 'from' => '1.2.0', 'to' => '1.3.0', 'time' => '2026-11-15T03:00:00Z']));
	}

	/**
	 * @return array<string,array{bool,bool}> enabled, module admin
	 */
	public static function notAllowed(): array
	{
		return [
			'no module admin'              => [true, false],
			'installation without updater' => [false, true],
		];
	}

	#[Group('units')]
	#[DataProvider('notAllowed')]
	public function testThePageIsOnlyForTheModuleAdminWithTheUpdater(bool $enabled, bool $isModuleAdmin): void
	{
		$this->enabled       = $enabled;
		$this->isModuleAdmin = $isModuleAdmin;

		$response = $this->show();

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
		static::assertSame(['error' => ['system.update_not_allowed']], $this->flash());
	}

	#[Group('units')]
	#[DataProvider('notAllowed')]
	public function testTheRequestIsOnlyForTheModuleAdminWithTheUpdater(bool $enabled, bool $isModuleAdmin): void
	{
		$this->enabled       = $enabled;
		$this->isModuleAdmin = $isModuleAdmin;

		$response = $this->request();

		static::assertSame('/', $response->getHeaderLine('Location'));
		static::assertFileDoesNotExist($this->updateDir . '/requested');
	}

	#[Group('units')]
	public function testThePageBeforeTheFirstUpdate(): void
	{
		$response = $this->show();

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame('system/update', $response->getTemplate());
		static::assertSame(['LANG_PAGE_TITLE' => 'system.update', 'additional_css' => [], 'footer_modules' => []], $response->getMainLayoutData());
		static::assertSame([
			'LANG_PAGE_HEADER' => 'system.update',
			'CSRF_TOKEN'       => 'the_token',
			'LANG_INSTALLED'   => 'system.update_installed{"{version}":"1.2.0"}',
			'LANG_STATUS'      => 'system.update_state_none',
			'LANG_EXPLANATION' => 'system.update_explanation',
			'LANG_BUTTON'      => 'system.update_start',
			'BUTTON_DISABLED'  => '',
			'is_busy'          => []
		], $response->getTemplateData());
	}

	#[Group('units')]
	public function testAFailedUpdateNamesItsStep(): void
	{
		$this->writeStatus('failed', 'migrate');
		date_default_timezone_set('UTC');

		$response = $this->show();

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame(
			'system.update_state_failed{"{from}":"1.2.0","{to}":"1.3.0","{step}":"system.update_step_migrate","{time}":"2026-11-15 03:00"}',
			$response->getTemplateData()['LANG_STATUS']
		);
		static::assertSame('', $response->getTemplateData()['BUTTON_DISABLED']);
	}

	#[Group('units')]
	public function testTheTimeIsShownInTheTimeZoneOfTheInstallation(): void
	{
		$this->writeStatus('done');
		date_default_timezone_set('Europe/Berlin');

		$response = $this->show();

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertStringContainsString('"{step}":"","{time}":"2026-11-15 04:00"', $response->getTemplateData()['LANG_STATUS']);
	}

	#[Group('units')]
	public function testAnUnreadableTimeIsShownAsItIs(): void
	{
		file_put_contents($this->updateDir . '/status.json', '{"state":"current","to":"1.2.0","time":"yesterday-ish"}');

		$response = $this->show();

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertStringContainsString('"{time}":"yesterday-ish"', $response->getTemplateData()['LANG_STATUS']);
	}

	/**
	 * @return array<string,array{string|null,bool,non-empty-string}> state of status.json, requested, status text
	 */
	public static function busy(): array
	{
		return [
			'requested'      => [null, true, 'system.update_state_requested'],
			'running'        => ['running', false, 'system.update_state_running'],
			'requested again' => ['done', true, 'system.update_state_requested'],
		];
	}

	/**
	 * @param non-empty-string $text
	 */
	#[Group('units')]
	#[DataProvider('busy')]
	public function testWhileItIsBusyTheButtonIsDisabled(?string $state, bool $requested, string $text): void
	{
		if ($state !== null)
			$this->writeStatus($state, 'download');
		if ($requested)
			touch($this->updateDir . '/requested');

		$response = $this->show();

		static::assertInstanceOf(PageResponse::class, $response);
		$data = $response->getTemplateData();
		static::assertStringStartsWith($text, $data['LANG_STATUS']);
		static::assertSame('disabled', $data['BUTTON_DISABLED']);
		static::assertSame([['LANG_RELOAD' => 'system.update_reload']], $data['is_busy']);
	}

	#[Group('units')]
	public function testTheButtonRequestsTheUpdate(): void
	{
		$this->writeStatus('done');

		$response = $this->request();

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/system/update', $response->getHeaderLine('Location'));
		static::assertFileExists($this->updateDir . '/requested');
		static::assertSame(['success' => ['system.update_requested']], $this->flash());
	}

	#[Group('units')]
	#[DataProvider('busy')]
	public function testNoSecondRequestWhileItIsBusy(?string $state, bool $requested, string $text): void
	{
		if ($state !== null)
			$this->writeStatus($state);
		if ($requested)
			touch($this->updateDir . '/requested');

		$response = $this->request();

		static::assertSame('/system/update', $response->getHeaderLine('Location'));
		static::assertSame(['error' => ['system.update_busy']], $this->flash());
		static::assertSame($requested, is_file($this->updateDir . '/requested'));
	}

	#[Group('units')]
	public function testARequestWhichCannotBeWrittenIsReported(): void
	{
		chmod($this->updateDir, 0500);
		if (is_writable($this->updateDir))
		{
			chmod($this->updateDir, 0700);
			static::markTestSkipped('root writes into every directory');
		}

		try
		{
			$response = $this->request();
		}
		finally
		{
			chmod($this->updateDir, 0700);
		}

		static::assertSame('/system/update', $response->getHeaderLine('Location'));
		$errors = $this->flash()['error'] ?? [];
		static::assertCount(1, $errors);
		static::assertStringStartsWith('system.update_not_requested{"{reason}":"The update could not be requested', $errors[0]);
	}
}
