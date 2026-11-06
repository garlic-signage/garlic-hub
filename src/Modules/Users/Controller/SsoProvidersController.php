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

namespace App\Modules\Users\Controller;

use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Auth\SsoLogin;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Helper\SsoProviders\FormElementsCreator;
use App\Modules\Users\Services\SsoProvidersService;
use App\Shared\User\Sso\SsoProvider;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;
use Throwable;

/**
 * The page of the SSO providers for the module admin of users: the list, the form of one provider, store and delete.
 */
readonly class SsoProvidersController
{
	private const string MODULE_NAME = 'users';
	private const string LIST = '/users/sso';

	public function __construct(
		private SsoProvidersService $service,
		private UserSession $userSession,
		private FormElementsCreator $formElementsCreator,
		private FormTemplatePreparer $formTemplatePreparer,
		private Translator $translator,
		private Messages $flash
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$UID = $this->userSession->getUID();
		if (!$this->service->mayManage($UID))
			return $this->noRights($response);

		$providers = array_map(fn(SsoProvider $provider): array => [
			'ID'      => $provider->id,
			'NAME'    => $provider->name,
			'ISSUER'  => $provider->issuer,
			'ENABLED' => $this->translator->translate($provider->enabled ? 'sso_on' : 'sso_off', self::MODULE_NAME)
		], $this->service->findAll($UID));

		$title = $this->translate('sso_providers');

		return new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => $title, 'additional_css' => [], 'footer_modules' => []],
			'this_layout' => [
				'template' => 'users/sso_providers',
				'data'     => [
					'LANG_PAGE_HEADER' => $title,
					'LANG_REDIRECT_URI' => $this->translate('sso_redirect_uri'),
					'REDIRECT_URI'      => SsoLogin::redirectUri($request),
					'LANG_NAME'         => $this->translate('sso_name'),
					'LANG_ISSUER'       => $this->translate('sso_issuer'),
					'LANG_ENABLED'      => $this->translate('sso_enabled'),
					'LANG_EDIT'         => $this->translator->translate('edit', 'main'),
					'LANG_ADD'          => $this->translator->translate('add', 'main'),
					'LANG_NO_PROVIDERS' => $this->translate('sso_no_providers'),
					'has_providers'     => $providers === [] ? [] : [['providers' => $providers]]
				]
			]
		]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function newForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->service->mayManage($this->userSession->getUID()))
			return $this->noRights($response);

		return $this->form($request, null, ['name' => '', 'issuer' => '', 'client_id' => '', 'allowed_domains' => '', 'enabled' => true]);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function editForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$UID = $this->userSession->getUID();
		if (!$this->service->mayManage($UID))
			return $this->noRights($response);

		$provider = $this->service->find($UID, (int) ($args['id'] ?? 0));
		if ($provider === null)
			return $this->backToList($response, 'error', 'sso_provider_not_found');

		return $this->form($request, $provider, $this->valuesOf($provider));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws Throwable
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$UID = $this->userSession->getUID();
		if (!$this->service->mayManage($UID))
			return $this->noRights($response);

		$input    = RequestInput::of($request->getParsedBody());
		$provider = null;
		$id       = $input->id('provider_id');
		if ($id > 0)
		{
			$provider = $this->service->find($UID, $id);
			if ($provider === null)
				return $this->backToList($response, 'error', 'sso_provider_not_found');
		}

		if ($provider !== null && isset($input->all()['delete']))
		{
			$this->service->delete($UID, $provider);
			return $this->backToList($response, 'success', 'sso_provider_deleted');
		}

		$values = $input->texts(['name', 'issuer', 'client_id', 'client_secret', 'allowed_domains']);
		$post   = [
			'name'            => $values['name'] ?? '',
			'issuer'          => $values['issuer'] ?? '',
			'client_id'       => $values['client_id'] ?? '',
			'client_secret'   => $values['client_secret'] ?? '',
			'allowed_domains' => $values['allowed_domains'] ?? '',
			'enabled'         => isset($input->all()['enabled'])
		];

		$errors = $this->service->save($UID, $provider, $post);
		if ($errors === [])
			return $this->backToList($response, 'success', 'sso_provider_saved');

		foreach ($errors as $error)
			$this->flash->addMessageNow('error', $this->translate($error));

		// the form again with what was entered, without the secret
		unset($post['client_secret']);

		return $this->form($request, $provider, $post);
	}

	/**
	 * @param array{name:string, issuer:string, client_id:string, allowed_domains:string, enabled:bool} $values
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function form(ServerRequestInterface $request, ?SsoProvider $provider, array $values): ResponseInterface
	{
		$form = $this->formElementsCreator->buildForm($values + ['provider_id' => $provider->id ?? 0, 'has_secret' => $provider !== null && $provider->encryptedSecret !== '']);

		return new PageResponse($this->formTemplatePreparer->prepareUITemplate($form + [
			'title'              => $provider !== null ? $this->translate('sso_provider') . ': ' . $provider->name : $this->translate('sso_provider_new'),
			'additional_css'     => [],
			'footer_modules'     => ['/js/users/sso/init.js'],
			'template_name'      => 'users/edit',
			'form_action'        => '/users/sso/edit',
			'explanations'       => $this->translate('sso_redirect_uri') . ' ' . SsoLogin::redirectUri($request),
			'save_button_label'  => $this->translator->translate('save', 'main'),
			'additional_buttons' => $this->formElementsCreator->buildButtons($provider !== null)
		]));
	}

	/**
	 * @return array{name:string, issuer:string, client_id:string, allowed_domains:string, enabled:bool}
	 */
	private function valuesOf(SsoProvider $provider): array
	{
		return ['name' => $provider->name, 'issuer' => $provider->issuer, 'client_id' => $provider->clientId, 'allowed_domains' => implode(', ', $provider->allowedDomains), 'enabled' => $provider->enabled];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function backToList(ResponseInterface $response, string $type, string $message): ResponseInterface
	{
		$this->flash->addMessage($type, $this->translate($message));

		return $response->withHeader('Location', self::LIST)->withStatus(302);
	}

	private function noRights(ResponseInterface $response): ResponseInterface
	{
		$this->flash->addMessage('error', 'No rights');

		return $response->withHeader('Location', '/')->withStatus(302);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function translate(string $key): string
	{
		return $this->translator->translate($key, self::MODULE_NAME);
	}
}
