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

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Controller\RequestInput;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Framework\Llm\LlmException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\UserSession;
use App\Modules\Users\Services\AclValidator;
use App\Shared\Llm\CompanyLlmService;
use App\Shared\Llm\LlmFormBuilder;
use App\Shared\User\UsersService;
use Defuse\Crypto\Exception\CryptoException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * The LLM of the company for the module admin of users, like the SSO providers: while it is there, the users of the
 * company work with it instead of their own ones. In Core it is the LLM of the installation.
 */
readonly class CompanyLlmController
{
	private const string MODULE_NAME = 'users';
	private const string PAGE        = '/users/llm';

	public function __construct(
		private CompanyLlmService $companyLlmService,
		private LlmFormBuilder $formBuilder,
		private AclValidator $aclValidator,
		private UsersService $usersService,
		private UserSession $userSession,
		private Translator $translator,
		private Messages $flash,
		private JsonResponseHandler $responseHandler
	) {}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$companyId = $this->companyOfManager();
		if ($companyId === null)
			return $this->noRights($response);

		$title = $this->translator->translate('company_llm', self::MODULE_NAME);
		$form  = $this->formBuilder->build(
			$this->companyLlmService->findSettings($companyId),
			self::PAGE,
			$this->companyLlmService->findReleasedFor($companyId) ?? CompanyLlmService::RELEASED_FOR_ALL
		);

		return new PageResponse([
			'main_layout' => ['LANG_PAGE_TITLE' => $title, 'additional_css' => ['/css/llm_form.css'], 'footer_modules' => ['/js/users/llm/init.js']],
			'this_layout' => [
				'template' => 'users/llm',
				'data'     => $form + [
					'LANG_PAGE_HEADER' => $title,
					'LANG_EXPLANATION' => $this->translator->translate('company_llm_explanation', self::MODULE_NAME)
				]
			]
		]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$companyId = $this->companyOfManager();
		if ($companyId === null)
			return $this->noRights($response);

		$input  = RequestInput::of($request->getParsedBody());
		$errors = $this->companyLlmService->saveWithRelease($companyId, [
			'api_type' => $input->text('api_type'),
			'base_url' => $input->text('base_url'),
			'model'    => $input->text('model'),
			'api_key'  => $input->text('api_key')
		], $input->text('released_for'));

		if ($errors === [])
			$this->flash->addMessage('success', $this->translator->translate('company_llm_saved', self::MODULE_NAME));

		foreach ($errors as $error)
			$this->flash->addMessage('error', $this->translator->translate($error, 'main'));

		return $response->withHeader('Location', self::PAGE)->withStatus(302);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$companyId = $this->companyOfManager();
		if ($companyId === null)
			return $this->noRights($response);

		$this->companyLlmService->delete($companyId);
		$this->flash->addMessage('success', $this->translator->translate('company_llm_deleted', self::MODULE_NAME));

		return $response->withHeader('Location', self::PAGE)->withStatus(302);
	}

	/**
	 * The models for the selection in the form, as JSON.
	 *
	 * @throws CoreException
	 * @throws CryptoException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function models(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$companyId = $this->companyOfManager();
		if ($companyId === null)
			return $this->responseHandler->jsonError($response, 'No rights', 403);

		$input = RequestInput::of($request->getParsedBody());
		try
		{
			$models = $this->companyLlmService->findModels($companyId, [
				'api_type' => $input->text('api_type'),
				'base_url' => $input->text('base_url'),
				'api_key'  => $input->text('api_key')
			]);
		}
		catch (LlmException $e)
		{
			return $this->responseHandler->jsonError($response, $this->translator->translateException($e));
		}

		return $this->responseHandler->jsonSuccess($response, ['models' => $models]);
	}

	/**
	 * The LLM belongs to the company, only its module admin of users manages it, like the SSO providers.
	 *
	 * @return int|null the company, null without the right
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	private function companyOfManager(): ?int
	{
		$UID = $this->userSession->getUID();
		if (!$this->aclValidator->isModuleAdmin($UID))
			return null;

		$companyId = $this->usersService->getUserById($UID)->getMain()['company_id'] ?? 1;

		return is_numeric($companyId) ? (int) $companyId : 1;
	}

	private function noRights(ResponseInterface $response): ResponseInterface
	{
		$this->flash->addMessage('error', 'No rights');

		return $response->withHeader('Location', '/')->withStatus(302);
	}
}
