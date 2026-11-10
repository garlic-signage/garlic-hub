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

namespace Tests\Unit\Shared\Settings;

use App\Framework\Config\ConfigOverrides;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Shared\Acl\AclHelper;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLog;
use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsSchema;
use App\Shared\Settings\SettingsService;
use App\Shared\Settings\SettingType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The settings with a schema of every type. The changes are kept in memory, admin is the user 1.
 * The settings with the real schemas and files are tested in tests/Integration/Modules/System/SettingsTest.
 */
class SettingsServiceTest extends TestCase
{
	private const int ADMIN = 1;
	private const int USER  = 2;

	/** @var array<string,array<string,array<string,string>>> module => the stored changes */
	private array $stored = [];
	/** @var list<array{int,string,AuditAction,array<string,mixed>}> */
	private array $recorded = [];
	private SettingsService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$overridesStub = $this->createStub(ConfigOverrides::class);
		$overridesStub->method('load')->willReturnCallback(fn(string $module): array => $this->stored[$module] ?? []);
		$overridesStub->method('save')->willReturnCallback(function (string $module, array $sections): void {
			$this->stored[$module] = array_filter($sections, static fn(array $values): bool => $values !== []);
		});

		$aclHelperStub = $this->createStub(AclHelper::class);
		$aclHelperStub->method('isModuleAdmin')->willReturnCallback(static fn(int $UID, string $module): bool => $UID === self::ADMIN && $module === 'system');

		$auditLogStub = $this->createStub(AuditLog::class);
		$auditLogStub->method('record')->willReturnCallback(function (int $UID, string $module, AuditAction $action, mixed $unitId = null, ?int $targetUID = null, array $details = []): void {
			$this->recorded[] = [$UID, $module, $action, $details];
		});

		$schema = new class implements SettingsSchema
		{
			public function fields(): array
			{
				return [
					new SettingField('General', 'enabled', SettingType::SWITCH, 'enabled'),
					new SettingField('General', 'extra', SettingType::TEXT, 'extra', dependsOn: 'General.enabled'),
					new SettingField('General', 'title', SettingType::TEXT, 'title'),
					new SettingField('General', 'count', SettingType::NUMBER, 'count', 5, 10),
					new SettingField('General', 'any', SettingType::NUMBER, 'any'),
					new SettingField('General', 'size', SettingType::NUMBER, 'size', factor: 1048576),
					new SettingField('General', 'fit', SettingType::SELECT, 'fit', options: ['meet', 'slice']),
				];
			}
		};
		$defaults = new RecordingConfigLoader(['demo' => ['General' => [
			'enabled' => '0', 'extra' => '', 'title' => 'Hub ; the name', 'count' => '5', 'any' => '0', 'size' => '1048576', 'fit' => 'meet'
		]]]);

		$this->service = new SettingsService($aclHelperStub, $defaults, $overridesStub, ['demo' => $schema], $auditLogStub);
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed> the values of the defaults with changes
	 */
	private static function values(array $changes = []): array
	{
		return array_merge(['General.enabled' => '0', 'General.extra' => '', 'General.title' => 'Hub', 'General.count' => '5', 'General.any' => '0', 'General.size' => '1', 'General.fit' => 'meet'], $changes);
	}

	/**
	 * @return array<string,string> "section.key" => value of the form
	 */
	private function formValues(): array
	{
		$values = [];
		foreach ($this->service->findSettings(self::ADMIN, 'demo') as $setting)
			$values[$setting['field']->section . '.' . $setting['field']->key] = $setting['value'];

		return $values;
	}

	/**
	 * @return array<string,array{\Closure}>
	 */
	public static function forbidden(): array
	{
		return [
			'find'  => [static fn(SettingsService $service, int $UID, string $module) => $service->findSettings($UID, $module)],
			'save'  => [static fn(SettingsService $service, int $UID, string $module) => $service->save($UID, $module, self::values())],
			'reset' => [static fn(SettingsService $service, int $UID, string $module) => $service->reset($UID, $module, 'General.title')],
		];
	}

	#[Group('units')]
	#[DataProvider('forbidden')]
	public function testOnlyTheModuleAdminOfSystemReadsAndChangesTheSettings(\Closure $call): void
	{
		$this->stored['demo'] = ['General' => ['title' => 'Mine']];

		foreach ([[self::USER, 'demo'], [self::ADMIN, 'unknown']] as [$UID, $module])
		{
			try
			{
				$call($this->service, $UID, $module);
				static::fail('Expected AccessDeniedException for ' . $UID . ' in ' . $module);
			}
			catch (AccessDeniedException $e)
			{
				static::assertSame('settings_not_changeable', $e->getTranslationKey());
			}
		}

		static::assertSame(['demo' => ['General' => ['title' => 'Mine']]], $this->stored);
		static::assertSame([], $this->recorded);
	}

	#[Group('units')]
	public function testTheModuleAdminHasTheTabs(): void
	{
		static::assertSame(['demo'], $this->service->findEditableModules(self::ADMIN));
		static::assertSame([], $this->service->findEditableModules(self::USER));
	}

	/**
	 * The defaults without comment, a size in the unit of the form.
	 */
	#[Group('units')]
	public function testTheFormShowsTheDefaults(): void
	{
		$settings = $this->service->findSettings(self::ADMIN, 'demo');

		static::assertSame(self::values(), $this->formValues());
		static::assertSame(['0', '', 'Hub', '5', '0', '1', 'meet'], array_column($settings, 'default'));
		static::assertSame([false, false, false, false, false, false, false], array_column($settings, 'changed'));
	}

	#[Group('units')]
	public function testOnlyTheChangesAreStoredAndRecorded(): void
	{
		$this->service->save(self::ADMIN, 'demo', self::values(['General.enabled' => 'on', 'General.title' => '  Garlic  ', 'General.size' => '20']));

		static::assertSame(['demo' => ['General' => ['enabled' => '1', 'title' => 'Garlic', 'size' => '20971520']]], $this->stored);
		static::assertSame([[self::ADMIN, 'demo', AuditAction::SETTINGS_CHANGED, ['changed' => ['General.enabled' => '1', 'General.title' => 'Garlic', 'General.size' => '20971520']]]], $this->recorded);
		static::assertSame(self::values(['General.enabled' => '1', 'General.title' => 'Garlic', 'General.size' => '20']), $this->formValues());
	}

	#[Group('units')]
	public function testAResetGoesBackToTheDefaultAndIsRecorded(): void
	{
		$this->service->save(self::ADMIN, 'demo', self::values(['General.title' => 'Garlic', 'General.count' => '7']));
		$this->recorded = [];

		$this->service->reset(self::ADMIN, 'demo', 'General.title');

		static::assertSame(['demo' => ['General' => ['count' => '7']]], $this->stored);
		static::assertSame([[self::ADMIN, 'demo', AuditAction::SETTINGS_CHANGED, ['changed' => ['General.title' => 'Hub']]]], $this->recorded);
	}

	/**
	 * A setting whose switch is off is not sent: it keeps its value, the settings after it are stored.
	 */
	#[Group('units')]
	public function testASettingOfASwitchWhichIsOffKeepsItsValue(): void
	{
		$this->stored['demo'] = ['General' => ['extra' => 'kept']];
		$values = self::values(['General.count' => '7']);
		unset($values['General.extra']);

		$this->service->save(self::ADMIN, 'demo', $values);

		static::assertSame(['demo' => ['General' => ['extra' => 'kept', 'count' => '7']]], $this->stored);
	}

	#[Group('units')]
	public function testSavingTheSameValuesRecordsNothing(): void
	{
		$this->stored['demo'] = ['General' => ['count' => '7']];

		$this->service->save(self::ADMIN, 'demo', self::values(['General.count' => '7']));

		static::assertSame([], $this->recorded);
	}

	/**
	 * @return array<string,array{mixed,string}>
	 */
	public static function switches(): array
	{
		return [
			'1 as text' => ['1', '1'], '1 as number' => [1, '1'], 'true' => [true, '1'], 'on' => ['on', '1'],
			'0 as text' => ['0', '0'], '0 as number' => [0, '0'], 'false' => [false, '0'], 'yes' => ['yes', '0'], 'two' => [2, '0'],
		];
	}

	#[Group('units')]
	#[DataProvider('switches')]
	public function testWhatASwitchTakesAsOn(mixed $value, string $expected): void
	{
		$this->service->save(self::ADMIN, 'demo', self::values(['General.enabled' => $value]));

		static::assertSame($expected, $this->formValues()['General.enabled']);
	}

	/**
	 * @return array<string,array{string,mixed}>
	 */
	public static function invalidValues(): array
	{
		return [
			'text with a quote'      => ['General.title', 'Say "hi"'],
			'text with a line break' => ['General.title', "two\nlines"],
			'text which is no text'  => ['General.title', ['Hub']],
			'number below the min'   => ['General.count', '4'],
			'number above the max'   => ['General.count', 11],
			'number as float'        => ['General.count', 6.0],
			'number with a dot'      => ['General.count', '6.5'],
			'number as array'        => ['General.count', ['6']],
			'select of nothing'      => ['General.fit', 'stretch'],
		];
	}

	#[Group('units')]
	#[DataProvider('invalidValues')]
	public function testAnInvalidValueChangesNothing(string $name, mixed $value): void
	{
		try
		{
			$this->service->save(self::ADMIN, 'demo', self::values([$name => $value]));
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('setting_invalid', $e->getTranslationKey());
			static::assertSame(['setting' => $name], $e->getReplacements());
		}
		static::assertSame([], $this->stored);
	}

	/**
	 * @return array<string,array{string,mixed,string}>
	 */
	public static function validNumbers(): array
	{
		return [
			'the min'             => ['General.count', '5', '5'],
			'the max'             => ['General.count', 10, '10'],
			'without a max'       => ['General.any', '99999999', '99999999'],
			'below 0 without min' => ['General.any', '-3', '-3'],
		];
	}

	#[Group('units')]
	#[DataProvider('validNumbers')]
	public function testTheLimitsOfANumber(string $name, mixed $value, string $expected): void
	{
		$this->service->save(self::ADMIN, 'demo', self::values([$name => $value]));

		static::assertSame($expected, $this->formValues()[$name]);
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function storedSizes(): array
	{
		return ['rounded down' => ['1468006', '1'], 'rounded up' => ['1677722', '2'], 'no number' => ['big', 'big']];
	}

	/**
	 * A size which was written by hand is shown in whole units of the form.
	 */
	#[Group('units')]
	#[DataProvider('storedSizes')]
	public function testAStoredSizeIsShownInTheUnitOfTheForm(string $stored, string $expected): void
	{
		$this->stored['demo'] = ['General' => ['size' => $stored]];

		static::assertSame($expected, $this->formValues()['General.size']);
	}

	#[Group('units')]
	public function testANumberWithoutFactorIsShownAsItIsStored(): void
	{
		$this->stored['demo'] = ['General' => ['any' => '1.5']];

		static::assertSame('1.5', $this->formValues()['General.any']);
	}
}
