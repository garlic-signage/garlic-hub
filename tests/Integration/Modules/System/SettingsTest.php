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

namespace Tests\Integration\Modules\System;

use App\Framework\Config\Config;
use App\Framework\Config\ConfigOverrides;
use App\Framework\Config\OverridingConfigLoader;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\System\Controller\ShowSettingsController;
use App\Shared\Settings\SettingsService;
use PHPUnit\Framework\Attributes\Group;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The settings page. Users: 1 admin (module admin of system), 2 anna without rights.
 * The defaults: everything which costs resources is off.
 */
#[Group('acl')]
class SettingsTest extends ContainerTestCase
{
	private SettingsService $service;
	private ConfigOverrides $overrides;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->service   = $this->container->get(SettingsService::class);
		$this->overrides = $this->container->get(ConfigOverrides::class);
	}

	/**
	 * @return array<string,array{value:string, default:string, changed:bool}>
	 */
	private function settings(int $UID, string $module = 'system'): array
	{
		$settings = [];
		foreach ($this->service->findSettings($UID, $module) as $setting)
		{
			$settings[$setting['field']->section . '.' . $setting['field']->key] = ['value' => $setting['value'], 'default' => $setting['default'], 'changed' => $setting['changed']];
		}

		return $settings;
	}

	public function testTheSystemIsLeanByDefault(): void
	{
		static::assertSame([
			'Scheduler.enabled'    => ['value' => '0', 'default' => '0', 'changed' => false],
			'Audit.enabled'        => ['value' => '0', 'default' => '0', 'changed' => false],
			'Audit.retention_days' => ['value' => '365', 'default' => '365', 'changed' => false],
			'Versioning.enabled'   => ['value' => '0', 'default' => '0', 'changed' => false],
			'Versioning.max_versions' => ['value' => '20', 'default' => '20', 'changed' => false],
		], $this->settings(1));
	}

	public function testOnlyTheModuleAdminGetsTheTabsOfTheModulesWithASchema(): void
	{
		static::assertSame(['mediapool', 'playlists', 'system'], $this->service->findEditableModules(1));
		static::assertSame([], $this->service->findEditableModules(2));
	}

	public function testTheAdminEnablesTheAuditLog(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '30']);

		// only the changes are written
		static::assertSame(['Scheduler' => ['enabled' => '1'], 'Audit' => ['enabled' => '1', 'retention_days' => '30']], $this->overrides->load('system'));
		static::assertSame(['value' => '1', 'default' => '0', 'changed' => true], $this->settings(1)['Audit.enabled']);
	}

	public function testASwitchWhichIsNotSentIsOff(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1']);

		$this->service->save(1, 'system', []);

		static::assertSame([], $this->overrides->load('system'), 'The value of the default is no change.');
	}

	public function testTheDaysKeepTheirValueWhileTheAuditLogIsOff(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '30']);

		// the greyed out field is not sent
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1']);

		static::assertSame(['Scheduler' => ['enabled' => '1'], 'Audit' => ['retention_days' => '30']], $this->overrides->load('system'));
	}

	public function testTheAuditLogKeepsItsValueWhileTheSchedulerIsOff(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '30']);

		$this->service->save(1, 'system', []);

		static::assertSame(['Audit' => ['enabled' => '1', 'retention_days' => '30']], $this->overrides->load('system'));
	}

	public function testTheDaysAreResetEvenWhileTheAuditLogIsOff(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '30']);

		$this->service->save(1, 'system', ['Scheduler.enabled' => '1']);

		$this->service->reset(1, 'system', 'Audit.retention_days');

		static::assertSame(['Scheduler' => ['enabled' => '1']], $this->overrides->load('system'));
	}

	public function testTheDaysAreGreyedOutWhileTheAuditLogIsOff(): void
	{
		$this->actingAs(1);

		$response = $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/system/settings'), new Response());

		static::assertInstanceOf(PageResponse::class, $response);
		$fields = $response->getTemplateData()['tabs'][2]['fields'];
		static::assertSame(['setting_system_Scheduler_enabled', 'disabled'], [$fields[1]['DEPENDS_ON'], $fields[1]['DISABLED']]);
		static::assertSame(['setting_system_Audit_enabled', 'disabled'], [$fields[2]['DEPENDS_ON'], $fields[2]['DISABLED']]);
	}

	public function testTheDaysAreGreyedOutWhileTheSchedulerIsOff(): void
	{
		// the audit log is on, but its switch is greyed out
		$this->overrides->save('system', ['Audit' => ['enabled' => '1']]);
		$this->actingAs(1);

		$response = $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/system/settings'), new Response());

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame('disabled', $response->getTemplateData()['tabs'][2]['fields'][2]['DISABLED']);
	}

	public function testASettingIsResetToItsDefault(): void
	{
		$values = ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '30'];
		$this->service->save(1, 'system', $values);

		$this->service->reset(1, 'system', 'Audit.retention_days');

		static::assertSame(['Scheduler' => ['enabled' => '1'], 'Audit' => ['enabled' => '1']], $this->overrides->load('system'));
	}

	public function testANumberOutsideItsLimitsChangesNothing(): void
	{
		try
		{
			$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '0']);
			static::fail('A retention of 0 days must be refused.');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('setting_invalid', $e->getTranslationKey());
			static::assertSame([], $this->overrides->load('system'));
		}
	}

	public function testEverySettingOfASchemaHasADefault(): void
	{
		foreach ($this->service->findEditableModules(1) as $module)
		{
			foreach ($this->settings(1, $module) as $name => $setting)
				static::assertNotSame('', $setting['default'], $module . ': ' . $name . ' is not in config_' . $module . '.ini');
		}
	}

	public function testTheAdminChangesTheDefaultsOfThePlaylists(): void
	{
		$this->service->save(1, 'playlists', ['Defaults.duration' => '20', 'Defaults.volume' => '100', 'Defaults.fit' => 'fill', 'Defaults.media_align' => 'center']);

		static::assertSame(['Defaults' => ['duration' => '20', 'fit' => 'fill']], $this->overrides->load('playlists'));
	}

	public function testTheFileSizesAreInMegabytesAndStoredInBytes(): void
	{
		static::assertSame(['value' => '20', 'default' => '20', 'changed' => false], $this->settings(1, 'mediapool')['max_file_sizes.images']);

		$this->service->save(1, 'mediapool', ['max_file_sizes.images' => '50', 'max_file_sizes.documents' => '20', 'max_file_sizes.audios' => '100',
			'max_file_sizes.videos' => '1024', 'max_file_sizes.downloads' => '1024', 'max_file_sizes.datasource' => '100']);

		static::assertSame(['max_file_sizes' => ['images' => '52428800']], $this->overrides->load('mediapool'));
		static::assertSame(['value' => '50', 'default' => '20', 'changed' => true], $this->settings(1, 'mediapool')['max_file_sizes.images']);
	}

	public function testASelectAcceptsOnlyItsOptions(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->service->save(1, 'playlists', ['Defaults.duration' => '15', 'Defaults.volume' => '100', 'Defaults.fit' => 'stretch', 'Defaults.media_align' => 'center']);
	}

	public function testTheSelectShowsTheStoredOption(): void
	{
		$this->service->save(1, 'playlists', ['Defaults.duration' => '15', 'Defaults.volume' => '100', 'Defaults.fit' => 'slice', 'Defaults.media_align' => 'center']);
		$this->actingAs(1);

		$response = $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/system/settings'), new Response());

		static::assertInstanceOf(PageResponse::class, $response);
		$fit = $response->getTemplateData()['tabs'][1]['fields'][2]['is_select']['options'];
		static::assertSame(['slice'], array_column(array_filter($fit, static fn(array $option): bool => $option['SELECTED'] === 'selected'), 'OPTION'));
	}

	public function testAUserWithoutRightsChangesNothing(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->save(2, 'system', ['Audit.enabled' => '1']);
	}

	public function testTheConfigReadsTheChangedValue(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '365']);

		// a new request: the Config of this one has the values of before in its memory
		$loader = new OverridingConfigLoader($this->container->get('ConfigDefaults'), $this->overrides);
		$config = new Config($loader, [], []);

		static::assertSame('1', $config->getConfigValue('enabled', 'system', 'Audit'));
		static::assertSame('1', $config->getConfigValue('enabled', 'system', 'Scheduler'));
	}

	public function testThePageHasATabPerModuleWithTheFields(): void
	{
		$this->actingAs(1);

		$response = $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/system/settings'), new Response());

		static::assertInstanceOf(PageResponse::class, $response);
		$data = $response->getTemplateData();
		static::assertSame(['mediapool', 'playlists', 'system'], array_column($data['tabs'], 'MODULE'));
		static::assertSame(['Scheduler.enabled', 'Audit.enabled', 'Audit.retention_days', 'Versioning.enabled', 'Versioning.max_versions'], array_column($data['tabs'][2]['fields'], 'NAME'));
	}

	public function testAUserWithoutRightsIsSentBack(): void
	{
		$this->actingAs(2);

		$response = $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/system/settings'), new Response());

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/', $response->getHeaderLine('Location'));
	}

	public function testTheFormIsStoredAndOpensTheTabAgain(): void
	{
		$this->actingAs(1);
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')
			->withParsedBody(['module' => 'system', 'settings' => ['Scheduler.enabled' => '1']]);

		$response = $this->controller()->store($request, new Response());

		static::assertSame('/system/settings#system', $response->getHeaderLine('Location'));
		static::assertSame(['Scheduler' => ['enabled' => '1']], $this->overrides->load('system'));
		static::assertNotEmpty($this->container->get(Messages::class)->getMessage('success') ?? $_SESSION['slimFlash']['success'] ?? []);
	}

	public function testAResetChangesNothingElse(): void
	{
		$this->service->save(1, 'system', ['Scheduler.enabled' => '1', 'Audit.enabled' => '1', 'Audit.retention_days' => '30']);
		$this->actingAs(1);
		// the reset form sends the module and the setting only
		$request = new ServerRequestFactory()->createServerRequest('POST', '/system/settings')
			->withParsedBody(['module' => 'system', 'reset' => 'Audit.retention_days']);

		$this->controller()->store($request, new Response());

		static::assertSame(['Scheduler' => ['enabled' => '1'], 'Audit' => ['enabled' => '1']], $this->overrides->load('system'));
	}

	private function controller(): ShowSettingsController
	{
		return $this->container->get(ShowSettingsController::class);
	}
}
