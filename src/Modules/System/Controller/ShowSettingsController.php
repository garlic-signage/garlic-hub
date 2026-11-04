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

namespace App\Modules\System\Controller;

use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsService;
use App\Shared\Settings\SettingType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Throwable;

/**
 * The settings page: a tab per module in which the user is module admin, a form per tab.
 */
readonly class ShowSettingsController
{
	private const string MODULE_NAME = 'system';

	public function __construct(
		private SettingsService $settingsService,
		private UserSession $userSession,
		private Translator $translator,
		private CsrfToken $csrfToken,
		private Messages $flash
	) {}

	/**
	 * @throws Throwable
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$UID     = $this->userSession->getUID();
		$modules = $this->settingsService->findEditableModules($UID);
		if ($modules === [])
		{
			$this->flash->addMessage('error', $this->translator->translate('settings_not_changeable', self::MODULE_NAME));
			return $response->withHeader('Location', '/')->withStatus(302);
		}

		$tabs = [];
		foreach ($modules as $module)
		{
			$tabs[] = $this->prepareTab($module, $this->settingsService->findSettings($UID, $module));
		}

		$title = $this->translator->translate('settings', self::MODULE_NAME);

		return new PageResponse([
			'main_layout' => [
				'LANG_PAGE_TITLE' => $title,
				'additional_css'  => ['/css/system/settings.css'],
				'footer_modules'  => ['/js/system/settings/init.js']
			],
			'this_layout' => [
				'template' => 'system/settings',
				'data'     => [
					'LANG_PAGE_HEADER' => $title,
					'CSRF_TOKEN'       => $this->csrfToken->getToken(),
					'LANG_SAVE'        => $this->translator->translate('save', 'main'),
					'LANG_RESET'       => $this->translator->translate('reset_to_default', self::MODULE_NAME),
					'tabs'             => $tabs
				]
			]
		]);
	}

	/**
	 * @throws Throwable
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input  = RequestInput::of($request->getParsedBody());
		$module = $input->text('module');
		$all    = $input->all();
		$values = is_array($all['settings'] ?? null) ? $all['settings'] : [];
		$reset  = $input->text('reset');

		try
		{
			$this->settingsService->save($this->userSession->getUID(), $module, $values, $reset === '' ? [] : [$reset]);
			$this->flash->addMessage('success', $this->translator->translate('settings_saved', self::MODULE_NAME));
		}
		catch (TranslatableModuleException $e)
		{
			$this->flash->addMessage('error', $this->translator->translateException($e));
		}

		return $response->withHeader('Location', '/system/settings#' . rawurlencode($module))->withStatus(302);
	}

	/**
	 * @param list<array{field:SettingField, value:string, default:string, changed:bool}> $settings
	 * @return array<string,mixed>
	 */
	private function prepareTab(string $module, array $settings): array
	{
		$fields = [];
		foreach ($settings as $setting)
		{
			$field  = $setting['field'];
			$name   = $field->section . '.' . $field->key;
			$fields[] = [
				'NAME'          => $name,
				'ID'            => 'setting_' . $module . '_' . $field->section . '_' . $field->key,
				'LANG_LABEL'    => $this->translator->translate($field->label, $module),
				'VALUE'         => $setting['value'],
				'is_switch'     => $field->type === SettingType::SWITCH ? ['CHECKED' => $setting['value'] === '1' ? 'checked' : ''] : [],
				'is_number'     => $field->type === SettingType::NUMBER ? ['MIN' => $field->min ?? '', 'MAX' => $field->max ?? ''] : [],
				'is_text'       => $field->type === SettingType::TEXT ? [[]] : [],
				'LANG_DEFAULT'  => $this->translator->translate('default_value', self::MODULE_NAME) . ': ' . $this->formatDefault($field->type, $setting['default']),
				'is_changed'    => $setting['changed'] ? [['LANG_CHANGED' => $this->translator->translate('changed', self::MODULE_NAME)]] : []
			];
		}

		return [
			'MODULE'      => $module,
			'LANG_MODULE' => $this->translator->translate($module, 'menu'),
			'fields'      => $fields
		];
	}

	private function formatDefault(SettingType $type, string $default): string
	{
		if ($type !== SettingType::SWITCH)
			return $default;

		return $this->translator->translate($default === '1' ? 'value_on' : 'value_off', self::MODULE_NAME);
	}
}
