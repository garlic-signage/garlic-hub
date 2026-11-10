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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\System\Controller\ShowSettingsController;
use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsService;
use App\Shared\Settings\SettingType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;

/**
 * The page data of the settings. The service is a stub, the translator gives module.key back.
 * The settings with the real schemas and files are tested in tests/Integration/Modules/System/SettingsTest.
 */
class ShowSettingsControllerTest extends TestCase
{
	private SettingsService&Stub $settingsServiceStub;
	private ShowSettingsController $controller;
	/** @var array<mixed>|\ArrayAccess<string,mixed>|null the session array of the flash messages */
	private array|\ArrayAccess|null $flashStorage = [];
	/** @var list<array{string,string,string}> the calls of save and reset: method, module, values or name */
	private array $calls = [];
	private ?\Exception $saveFails = null;

	protected function setUp(): void
	{
		parent::setUp();
		$session = new MemorySession();
		$session->set('user', ['UID' => 1, 'username' => 'admin', 'locale' => 'en_US']);

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(
			static fn(string $key, string $module, array $replacements = []): string => $module . '.' . $key . ($replacements === [] ? '' : json_encode($replacements))
		);
		$translator->method('translateException')->willReturnCallback(
			static fn(TranslatableModuleException $e): string => 'exception.' . $e->getTranslationKey()
		);

		$csrfTokenStub = $this->createStub(CsrfToken::class);
		$csrfTokenStub->method('getToken')->willReturn('the_token');

		$this->settingsServiceStub = $this->createStub(SettingsService::class);
		$this->settingsServiceStub->method('save')->willReturnCallback(function (int $UID, string $module, array $values): void {
			if ($this->saveFails !== null)
				throw $this->saveFails;
			$this->calls[] = ['save', $module, (string) json_encode($values)];
		});
		$this->settingsServiceStub->method('reset')->willReturnCallback(function (int $UID, string $module, string $name): void {
			$this->calls[] = ['reset', $module, $name];
		});

		$this->controller = new ShowSettingsController($this->settingsServiceStub, new UserSession($session), $translator, $csrfTokenStub, new Messages($this->flashStorage));
	}

	/**
	 * @return array<string,list<string>>
	 */
	private function flash(): array
	{
		return is_array($this->flashStorage) ? ($this->flashStorage['slimFlash'] ?? []) : [];
	}

	/**
	 * @param array<string,list<array{field:SettingField, value:string, default:string, changed:bool}>> $settingsPerModule
	 */
	private function show(array $settingsPerModule): ResponseInterface
	{
		$this->settingsServiceStub->method('findEditableModules')->willReturn(array_keys($settingsPerModule));
		$this->settingsServiceStub->method('findSettings')->willReturnCallback(static fn(int $UID, string $module): array => $settingsPerModule[$module]);

		return $this->controller->show(new ServerRequestFactory()->createServerRequest('GET', '/system/settings'), new Response());
	}

	/**
	 * @return array{field:SettingField, value:string, default:string, changed:bool}
	 */
	private static function setting(SettingField $field, string $value, string $default, bool $changed = false): array
	{
		return ['field' => $field, 'value' => $value, 'default' => $default, 'changed' => $changed];
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed>
	 */
	private static function field(string $name, string $id, string $value, string $default, array $changes = []): array
	{
		return array_merge([
			'NAME'         => $name,
			'ID'           => $id,
			'LANG_LABEL'   => '',
			'VALUE'        => $value,
			'DEPENDS_ON'   => '',
			'DISABLED'     => '',
			'is_switch'    => [],
			'is_number'    => [],
			'is_text'      => [],
			'is_select'    => [],
			'LANG_DEFAULT' => 'system.default_value: ' . $default,
			'is_changed'   => []
		], $changes);
	}

	#[Group('units')]
	public function testAUserWithoutModulesIsSentBackWithAMessage(): void
	{
		$response = $this->show([]);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
		static::assertSame(['error' => ['system.settings_not_changeable']], $this->flash());
	}

	#[Group('units')]
	public function testThePageHasTheLayoutAndAFormPerModule(): void
	{
		$response = $this->show([
			'system'    => [self::setting(new SettingField('Scheduler', 'enabled', SettingType::SWITCH, 'scheduler_enabled'), '1', '0', true)],
			'playlists' => [self::setting(new SettingField('Defaults', 'name', SettingType::TEXT, 'default_name'), 'Neu', 'New')]
		]);

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame('system/settings', $response->getTemplate());
		static::assertSame([
			'LANG_PAGE_TITLE' => 'system.settings',
			'additional_css'  => ['/css/system/settings.css'],
			'footer_modules'  => ['/js/system/settings/init.js']
		], $response->getMainLayoutData());
		static::assertSame([
			'LANG_PAGE_HEADER' => 'system.settings',
			'CSRF_TOKEN'       => 'the_token',
			'LANG_SAVE'        => 'main.save',
			'LANG_RESET'       => 'system.reset_to_default',
			'tabs'             => [
				[
					'MODULE'      => 'system',
					'LANG_MODULE' => 'menu.system',
					'fields'      => [self::field('Scheduler.enabled', 'setting_system_Scheduler_enabled', '1', 'system.value_off', [
						'LANG_LABEL' => 'system.scheduler_enabled',
						'is_switch'  => ['CHECKED' => 'checked'],
						'is_changed' => [['LANG_CHANGED' => 'system.changed']]
					])]
				],
				[
					'MODULE'      => 'playlists',
					'LANG_MODULE' => 'menu.playlists',
					'fields'      => [self::field('Defaults.name', 'setting_playlists_Defaults_name', 'Neu', 'New', [
						'LANG_LABEL' => 'playlists.default_name',
						'is_text'    => [[]]
					])]
				]
			]
		], $response->getTemplateData());
	}

	#[Group('units')]
	public function testEveryTypeGetsItsOwnBlock(): void
	{
		$response = $this->show(['playlists' => [
			self::setting(new SettingField('Defaults', 'muted', SettingType::SWITCH, 'muted'), '0', '1'),
			self::setting(new SettingField('Defaults', 'volume', SettingType::NUMBER, 'volume', 0, 100), '80', '100'),
			self::setting(new SettingField('Defaults', 'duration', SettingType::NUMBER, 'duration'), '15', '15'),
			self::setting(new SettingField('Defaults', 'fit', SettingType::SELECT, 'fit', options: ['meet', 'slice', 'fill']), 'slice', 'meet'),
		]]);

		static::assertInstanceOf(PageResponse::class, $response);
		$fields = $response->getTemplateData()['tabs'][0]['fields'];
		static::assertSame([
			['is_switch' => ['CHECKED' => ''], 'is_number' => [], 'is_text' => [], 'is_select' => [], 'LANG_DEFAULT' => 'system.default_value: system.value_on'],
			['is_switch' => [], 'is_number' => ['MIN' => 0, 'MAX' => 100], 'is_text' => [], 'is_select' => [], 'LANG_DEFAULT' => 'system.default_value: 100'],
			['is_switch' => [], 'is_number' => ['MIN' => '', 'MAX' => ''], 'is_text' => [], 'is_select' => [], 'LANG_DEFAULT' => 'system.default_value: 15'],
			['is_switch' => [], 'is_number' => [], 'is_text' => [], 'is_select' => ['options' => [
				['OPTION' => 'meet', 'SELECTED' => ''],
				['OPTION' => 'slice', 'SELECTED' => 'selected'],
				['OPTION' => 'fill', 'SELECTED' => '']
			]], 'LANG_DEFAULT' => 'system.default_value: meet'],
		], array_map(static fn(array $field): array => array_intersect_key($field, array_flip(['is_switch', 'is_number', 'is_text', 'is_select', 'LANG_DEFAULT'])), $fields));
	}

	/**
	 * @param array{string,string,string} $values of Scheduler.enabled, Audit.enabled and Audit.retention_days
	 * @return array<int,array{mixed,mixed}> DEPENDS_ON and DISABLED per field
	 */
	private function dependencies(array $values): array
	{
		$response = $this->show(['system' => [
			self::setting(new SettingField('Scheduler', 'enabled', SettingType::SWITCH, 'scheduler'), $values[0], '0'),
			self::setting(new SettingField('Audit', 'enabled', SettingType::SWITCH, 'audit', dependsOn: 'Scheduler.enabled'), $values[1], '0'),
			self::setting(new SettingField('Audit', 'retention_days', SettingType::NUMBER, 'days', 1, 3650, dependsOn: 'Audit.enabled'), $values[2], '365'),
		]]);

		static::assertInstanceOf(PageResponse::class, $response);

		return array_map(static fn(array $field): array => [$field['DEPENDS_ON'], $field['DISABLED']], $response->getTemplateData()['tabs'][0]['fields']);
	}

	#[Group('units')]
	public function testTheSettingsOfASwitchWhichIsOnAreEnabled(): void
	{
		static::assertSame([
			['', ''],
			['setting_system_Scheduler_enabled', ''],
			['setting_system_Audit_enabled', '']
		], $this->dependencies(['1', '1', '30']));
	}

	#[Group('units')]
	public function testTheSettingOfASwitchWhichIsOffIsDisabled(): void
	{
		static::assertSame([
			['', ''],
			['setting_system_Scheduler_enabled', ''],
			['setting_system_Audit_enabled', 'disabled']
		], $this->dependencies(['1', '0', '30']));
	}

	#[Group('units')]
	public function testTheSettingOfASwitchWhichIsDisabledIsDisabledToo(): void
	{
		static::assertSame([
			['', ''],
			['setting_system_Scheduler_enabled', 'disabled'],
			['setting_system_Audit_enabled', 'disabled']
		], $this->dependencies(['0', '1', '30']));
	}

	/**
	 * Only the value of a switch which comes later in the schema counts: it is not greyed out yet.
	 */
	#[Group('units')]
	public function testASwitchLaterInTheSchemaCountsWithItsValue(): void
	{
		$response = $this->show(['system' => [
			self::setting(new SettingField('Audit', 'retention_days', SettingType::NUMBER, 'days', dependsOn: 'Audit.enabled'), '30', '365'),
			self::setting(new SettingField('Audit', 'enabled', SettingType::SWITCH, 'audit'), '1', '0'),
		]]);

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame('', $response->getTemplateData()['tabs'][0]['fields'][0]['DISABLED']);
	}

	#[Group('units')]
	public function testTheFormIsSavedAndOpensItsTabAgain(): void
	{
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')
			->withParsedBody(['module' => 'play lists', 'settings' => ['Defaults.duration' => '20']]);

		$response = $this->controller->store($request, new Response());

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/system/settings#play%20lists', $response->getHeaderLine('Location'));
		static::assertSame([['save', 'play lists', '{"Defaults.duration":"20"}']], $this->calls);
		static::assertSame(['success' => ['system.settings_saved']], $this->flash());
	}

	#[Group('units')]
	public function testAFormWithoutSettingsSavesNoValues(): void
	{
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')
			->withParsedBody(['module' => 'system', 'settings' => 'no_array']);

		$this->controller->store($request, new Response());

		static::assertSame([['save', 'system', '[]']], $this->calls);
	}

	#[Group('units')]
	public function testAResetOnlyResets(): void
	{
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')
			->withParsedBody(['module' => 'system', 'reset' => 'Audit.retention_days', 'settings' => ['Audit.enabled' => '1']]);

		$this->controller->store($request, new Response());

		static::assertSame([['reset', 'system', 'Audit.retention_days']], $this->calls);
		static::assertSame(['success' => ['system.settings_saved']], $this->flash());
	}

	#[Group('units')]
	public function testAnInvalidValueIsATranslatedMessage(): void
	{
		$this->saveFails = new TranslatableModuleException('system', 'setting_invalid');
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')->withParsedBody(['module' => 'system']);

		$response = $this->controller->store($request, new Response());

		static::assertSame('/system/settings#system', $response->getHeaderLine('Location'));
		static::assertSame(['error' => ['exception.setting_invalid']], $this->flash());
	}

	#[Group('units')]
	public function testAFileWhichCanNotBeWrittenIsAMessageWithTheReason(): void
	{
		$this->saveFails = new CoreException('not writable');
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')->withParsedBody(['module' => 'system']);

		$response = $this->controller->store($request, new Response());

		static::assertSame(302, $response->getStatusCode());
		static::assertSame(['error' => ['system.settings_not_saved{"{reason}":"not writable"}']], $this->flash());
	}
}
