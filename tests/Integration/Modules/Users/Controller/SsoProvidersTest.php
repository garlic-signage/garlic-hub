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

namespace Tests\Integration\Modules\Users\Controller;

use App\Framework\Security\Crypt;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Users\Controller\SsoProvidersController;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\Services\SsoProvidersService;
use App\Shared\Audit\AuditLogRepository;
use App\Shared\Audit\DatabaseAuditLog;
use App\Shared\User\Repositories\Core\SsoProvidersRepository;
use App\Shared\User\Repositories\Core\UserSsoRepository;
use App\Shared\User\UsersService;
use Defuse\Crypto\Crypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

/**
 * The page of the SSO providers. Users: 1 admin (module admin of users), 2 anna and 3 bernd without rights.
 */
#[Group('acl')]
class SsoProvidersTest extends UsersControllerTestCase
{
	private function controller(): SsoProvidersController
	{
		return $this->container->get(SsoProvidersController::class);
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	private function store(array $changes = []): ResponseInterface
	{
		$post = array_merge(['provider_id' => '0', 'name' => 'Google', 'issuer' => 'https://accounts.google.com/', 'client_id' => 'the-client',
			'client_secret' => 'very-secret', 'allowed_domains' => 'Example.com,  example.org', 'enabled' => '1'], $changes);
		$post = array_filter($post, static fn(mixed $value): bool => $value !== null);

		return $this->controller()->store(new ServerRequestFactory()->createServerRequest('POST', '/users/sso/edit')->withParsedBody($post), new Response());
	}

	/**
	 * @return array<string,mixed>
	 */
	private function stored(int $providerId = 1): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM sso_providers WHERE provider_id = ?', [$providerId]);
		static::assertIsArray($row);

		return $row;
	}

	private function secretOf(int $providerId = 1): string
	{
		return Crypto::decrypt((string) $this->stored($providerId)['client_secret'], $this->container->get(Crypt::class)->getEncryptionKey());
	}

	public function testTheAdminAddsAProvider(): void
	{
		$this->actingAs(1);

		$response = $this->store();

		static::assertSame('/users/sso', $response->getHeaderLine('Location'));
		$row = $this->stored();
		static::assertSame(['company_id' => 1, 'name' => 'Google', 'issuer' => 'https://accounts.google.com', 'client_id' => 'the-client', 'allowed_domains' => 'example.com, example.org', 'enabled' => 1],
			array_intersect_key($row, array_flip(['company_id', 'name', 'issuer', 'client_id', 'allowed_domains', 'enabled'])));
		static::assertStringNotContainsString('very-secret', (string) $row['client_secret']);
		static::assertSame('very-secret', $this->secretOf());
	}

	public function testTheListShowsTheProvidersAndTheRedirectUri(): void
	{
		$this->actingAs(1);
		$this->store();

		$page = $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', 'https://hub.example.test/users/sso'), new Response());

		static::assertInstanceOf(PageResponse::class, $page);
		$data = $page->getTemplateData();
		static::assertSame('https://hub.example.test/login/sso/callback', $data['REDIRECT_URI']);
		static::assertSame(['Google'], array_column($data['has_providers'][0]['providers'], 'NAME'));
	}

	public function testTheFormNeverShowsTheSecretAndAnEmptyOneKeepsIt(): void
	{
		$this->actingAs(1);
		$this->store();

		$page = $this->controller()->editForm(new ServerRequestFactory()->createServerRequest('GET', '/users/sso/edit/1'), new Response(), ['id' => '1']);
		static::assertInstanceOf(PageResponse::class, $page);
		$html = implode('', array_column($page->getTemplateData()['form_element'], 'HTML_ELEMENT'));
		static::assertStringContainsString('name="client_secret"', $html);
		static::assertStringNotContainsString('very-secret', $html);
		static::assertStringNotContainsString((string) $this->stored()['client_secret'], $html);

		$this->store(['provider_id' => '1', 'name' => 'Google Workspace', 'client_secret' => '']);
		static::assertSame('Google Workspace', $this->stored()['name']);
		static::assertSame('very-secret', $this->secretOf());

		$this->store(['provider_id' => '1', 'client_secret' => 'new-secret', 'enabled' => null]);
		static::assertSame('new-secret', $this->secretOf());
		static::assertSame(0, $this->stored()['enabled']);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongInput(): array
	{
		return [
			'no name'          => [['name' => '']],
			'issuer not https' => [['issuer' => 'http://accounts.google.com']],
			'issuer no url'    => [['issuer' => 'accounts']],
			'no client id'     => [['client_id' => '']],
			'no secret'        => [['client_secret' => '']],
			'wrong domain'     => [['allowed_domains' => 'example.com, not a domain']],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	#[DataProvider('wrongInput')]
	public function testWrongInputShowsTheFormAgainAndStoresNothing(array $changes): void
	{
		$this->actingAs(1);

		$response = $this->store($changes);

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
		$html = implode('', array_column($response->getTemplateData()['form_element'], 'HTML_ELEMENT'));
		static::assertStringNotContainsString('very-secret', $html, 'the secret is not sent back');
	}

	public function testAnIssuerOnlyOncePerCompany(): void
	{
		$this->actingAs(1);
		$this->store();

		static::assertInstanceOf(PageResponse::class, $this->store(['name' => 'Again']));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
	}

	public function testDeletingAProviderKeepsItsUsers(): void
	{
		$this->actingAs(1);
		$this->store();
		$this->connection->insert('user_sso', ['UID' => 2, 'provider_id' => 1, 'subject' => 'anna']);

		$response = $this->store(['provider_id' => '1', 'delete' => '1']);

		static::assertSame('/users/sso', $response->getHeaderLine('Location'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_sso'));
		static::assertSame('anna', $this->connection->fetchOne('SELECT username FROM user_main WHERE UID = 2'));
	}

	public function testAProviderOfAnotherCompanyIsNotFound(): void
	{
		$this->connection->insert('sso_providers', ['provider_id' => 7, 'company_id' => 2, 'name' => 'Other', 'issuer' => 'https://other.example.test', 'client_id' => 'x', 'client_secret' => '']);
		$this->actingAs(1);

		$this->assertSame('/users/sso', $this->controller()->editForm(new ServerRequestFactory()->createServerRequest('GET', '/users/sso/edit/7'), new Response(), ['id' => '7'])->getHeaderLine('Location'));
		$this->store(['provider_id' => '7', 'delete' => '1']);
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
	}

	public function testAUserWithoutRightsGetsNothing(): void
	{
		$this->actingAs(2);

		static::assertSame('/', $this->controller()->show(new ServerRequestFactory()->createServerRequest('GET', '/users/sso'), new Response())->getHeaderLine('Location'));
		static::assertSame('/', $this->controller()->newForm(new ServerRequestFactory()->createServerRequest('GET', '/users/sso/new'), new Response())->getHeaderLine('Location'));
		static::assertSame('/', $this->store()->getHeaderLine('Location'));
		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM sso_providers'));
	}

	public function testTheAuditLogDoesNotShowTheSecret(): void
	{
		// the audit log is off by default
		$service = new SsoProvidersService(new SsoProvidersRepository($this->connection), new UserSsoRepository($this->connection), $this->container->get(AclValidator::class),
			$this->container->get(UsersService::class), $this->container->get(Crypt::class), $this->container->get(Transactions::class), new DatabaseAuditLog($this->container->get(AuditLogRepository::class)));

		$errors = $service->save(1, null, ['name' => 'Google', 'issuer' => 'https://accounts.google.com', 'client_id' => 'the-client', 'client_secret' => 'very-secret', 'allowed_domains' => '', 'enabled' => true]);

		static::assertSame([], $errors);
		$details = (string) $this->connection->fetchOne("SELECT details FROM audit_log WHERE action = 'sso_provider_saved'");
		static::assertStringContainsString('accounts.google.com', $details);
		static::assertStringNotContainsString('very-secret', $details);
		static::assertStringNotContainsString((string) $this->stored()['client_secret'], $details);
	}
	private function get(string $path): ServerRequestInterface
	{
		return new ServerRequestFactory()->createServerRequest('GET', 'https://hub.example.test' . $path);
	}

	/**
	 * The list with all its texts: an active and an inactive provider.
	 */
	public function testTheListHasAllItsTextsAndTheProviders(): void
	{
		$this->actingAs(1);
		$this->store();
		$this->store(['name' => 'Microsoft', 'issuer' => 'https://login.microsoftonline.com', 'enabled' => null]);
		$_SESSION['slimFlash'] = [];

		$page = $this->page($this->controller()->show($this->get('/users/sso'), new Response()));

		static::assertSame('users/sso_providers', $page->getTemplate());
		static::assertSame(['LANG_PAGE_TITLE' => $this->text('sso_providers'), 'additional_css' => [], 'footer_modules' => []], $page->getMainLayoutData());
		static::assertSame([
			'LANG_PAGE_HEADER'  => $this->text('sso_providers'),
			'LANG_REDIRECT_URI' => $this->text('sso_redirect_uri'),
			'REDIRECT_URI'      => 'https://hub.example.test/login/sso/callback',
			'LANG_NAME'         => $this->text('sso_name'),
			'LANG_ISSUER'       => $this->text('sso_issuer'),
			'LANG_ENABLED'      => $this->text('sso_enabled'),
			'LANG_EDIT'         => $this->text('edit', 'main'),
			'LANG_ADD'          => $this->text('add', 'main'),
			'LANG_NO_PROVIDERS' => $this->text('sso_no_providers'),
			'has_providers'     => [['providers' => [
				['ID' => 1, 'NAME' => 'Google', 'ISSUER' => 'https://accounts.google.com', 'ENABLED' => $this->text('sso_on')],
				['ID' => 2, 'NAME' => 'Microsoft', 'ISSUER' => 'https://login.microsoftonline.com', 'ENABLED' => $this->text('sso_off')],
			]]]
		], $page->getTemplateData());
	}

	public function testAnEmptyListHasNoProviders(): void
	{
		$this->actingAs(1);

		static::assertSame([], $this->page($this->controller()->show($this->get('/users/sso'), new Response()))->getTemplateData()['has_providers']);
	}

	/**
	 * A new provider: its own title, no delete button, id 0 and the secret is needed.
	 */
	public function testTheFormOfANewProvider(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->controller()->newForm($this->get('/users/sso/new'), new Response()));

		static::assertSame('users/edit', $page->getTemplate());
		static::assertSame(['LANG_PAGE_TITLE' => $this->text('sso_provider_new'), 'additional_css' => [], 'footer_modules' => ['/js/users/sso/init.js']], $page->getMainLayoutData());
		$data = $page->getTemplateData();
		static::assertSame($this->text('sso_provider_new'), $data['LANG_PAGE_HEADER']);
		static::assertSame('/users/sso/edit', $data['FORM_ACTION']);
		static::assertSame($this->text('sso_redirect_uri') . ' https://hub.example.test/login/sso/callback', $data['LANG_FORM_EXPLANATION']);
		static::assertSame($this->text('save', 'main'), $data['form_button'][0]['LANG_ELEMENT_BUTTON']);
		static::assertSame([], $data['additional_buttons']);
		$fields = $this->fields($page);
		static::assertSame('0', $fields['provider_id']['value']);
		static::assertArrayHasKey('required', $fields['client_secret']);
		static::assertSame('', $fields['client_secret']['placeholder'] ?? '');
	}

	/**
	 * A stored provider: its name in the title, the delete button and the stored secret is not needed again.
	 */
	public function testTheFormOfAStoredProvider(): void
	{
		$this->actingAs(1);
		$this->store();

		$page = $this->page($this->controller()->editForm($this->get('/users/sso/edit/1'), new Response(), ['id' => '1']));

		static::assertSame($this->text('sso_provider') . ': Google', $page->getMainLayoutData()['LANG_PAGE_TITLE']);
		$data = $page->getTemplateData();
		static::assertSame($this->text('sso_provider') . ': Google', $data['LANG_PAGE_HEADER']);
		static::assertSame(['ADDITIONAL_BUTTON_TYPE' => 'submit', 'ADDITIONAL_BUTTON_NAME' => 'delete', 'LANG_ADDITIONAL_BUTTON' => $this->text('delete', 'main'),
			'ADDITIONAL_BUTTON_CONFIRM' => $this->text('sso_delete_confirm')], $data['additional_buttons'][0] ?? null);
		$fields = $this->fields($page);
		static::assertSame('1', $fields['provider_id']['value']);
		static::assertArrayNotHasKey('required', $fields['client_secret']);
		static::assertSame($this->text('sso_secret_stored'), $fields['client_secret']['placeholder']);
	}

	/**
	 * A stored provider without a secret (left over from an old version) needs one.
	 */
	public function testAStoredProviderWithoutSecretNeedsOne(): void
	{
		$this->connection->insert('sso_providers', ['provider_id' => 5, 'company_id' => 1, 'name' => 'Old', 'issuer' => 'https://old.example.test', 'client_id' => 'x', 'client_secret' => '']);
		$this->actingAs(1);

		$fields = $this->fields($this->page($this->controller()->editForm($this->get('/users/sso/edit/5'), new Response(), ['id' => '5'])));

		static::assertArrayHasKey('required', $fields['client_secret']);
	}

	public function testSavingRedirectsToTheListWithAMessage(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(), '/users/sso');
		static::assertSame([$this->text('sso_provider_saved')], $this->flash('success'));
	}

	public function testDeletingRedirectsToTheListWithAMessage(): void
	{
		$this->actingAs(1);
		$this->store();
		$_SESSION['slimFlash'] = [];

		$this->assertRedirect($this->store(['provider_id' => '1', 'delete' => '1']), '/users/sso');
		static::assertSame([$this->text('sso_provider_deleted')], $this->flash('success'));
	}

	public function testAnUnknownProviderRedirectsWithAnError(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->controller()->editForm($this->get('/users/sso/edit/9'), new Response(), ['id' => '9']), '/users/sso', $this->text('sso_provider_not_found'));
	}

	public function testAnUnknownProviderIsNotStored(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['provider_id' => '9']), '/users/sso', $this->text('sso_provider_not_found'));
	}

	public function testWithoutRightsTheStartPageWithAnError(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller()->show($this->get('/users/sso'), new Response()), '/', 'No rights');
	}

	public function testWithoutRightsNoFormOfAStoredProvider(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller()->editForm($this->get('/users/sso/edit/1'), new Response(), ['id' => '1']), '/', 'No rights');
	}

	/**
	 * Every error of the input once, for this request: the form is shown again.
	 */
	public function testEveryErrorIsShownOnce(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['name' => '', 'client_id' => '']));

		static::assertSame([$this->text('sso_name_invalid'), $this->text('sso_client_id_invalid')], $this->flash('error'));
		static::assertSame('', $this->fields($page)['name']['value'] ?? '');
	}
}
