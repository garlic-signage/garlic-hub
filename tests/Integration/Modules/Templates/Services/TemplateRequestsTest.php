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

namespace Tests\Integration\Modules\Templates\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Security\CsrfToken;
use App\Modules\Templates\Controller\ShowSettingsController;
use App\Modules\Templates\Controller\TemplatesController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The requests of a browser to the template pages and endpoints. Fixtures: template 1 belongs to
 * the admin, 2 to anna, 3 to bernd.
 */
#[Group('acl')]
class TemplateRequestsTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
	}

	public function testUserMayNotCreateATemplate(): void
	{
		$this->actingAs(2);

		$response = $this->store(['template_id' => 0, 'name' => 'Neu', 'visibility' => 'private', 'type' => 'canvas']);

		static::assertSame('/templates', $response->getHeaderLine('Location'));
		static::assertSame(3, $this->countTemplates());
	}

	public function testAdminMayCreateATemplate(): void
	{
		$this->actingAs(1);

		// the form fills the owner field with the logged-in user
		$response = $this->store(['template_id' => 0, 'UID' => 1, 'name' => 'Neu', 'visibility' => 'private', 'type' => 'canvas']);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame(4, $this->countTemplates());
		$created = $this->connection->fetchAssociative("SELECT UID, type, name FROM templates WHERE name = 'Neu'");
		static::assertIsArray($created);
		static::assertSame(1, (int) $created['UID']);
		static::assertSame('canvas', $created['type']);
	}

	public function testUserMayNotChangeAForeignTemplate(): void
	{
		$this->actingAs(2);

		$this->store(['template_id' => 3, 'name' => 'gehackt', 'visibility' => 'global', 'UID' => 2]);

		$template = $this->template(3);
		static::assertSame('Bernds Vorlage', $template['name']);
		static::assertSame('private', $template['visibility']);
		static::assertSame(3, (int) $template['UID']);
	}

	public function testUserMayNotChangeHerOwnTemplateEither(): void
	{
		$this->actingAs(2);

		$this->store(['template_id' => 2, 'name' => 'gehackt', 'visibility' => 'global']);

		static::assertSame('Annas Vorlage', $this->template(2)['name']);
	}

	public function testAdminMayChangeNameAndVisibilityButNotTheType(): void
	{
		$this->actingAs(1);

		$this->store(['template_id' => 3, 'name' => 'Umbenannt', 'visibility' => 'global', 'type' => 'html']);

		$template = $this->template(3);
		static::assertSame('Umbenannt', $template['name']);
		static::assertSame('global', $template['visibility']);
		static::assertSame('fabric.js', $template['type']);
	}

	public function testUserMayNotDeleteATemplateByRequest(): void
	{
		$this->actingAs(2);

		try
		{
			$this->templatesController()->delete($this->request('DELETE', ['template_id' => 3]), new Response());
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(3, $this->countTemplates());
		}
	}

	public function testAdminMayDeleteATemplateByRequest(): void
	{
		$this->actingAs(1);

		$response = $this->templatesController()->delete($this->request('DELETE', ['template_id' => 3]), new Response());

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(2, $this->countTemplates());
	}

	public function testDeleteWithAForgedCsrfTokenIsRejected(): void
	{
		$this->actingAs(1);
		$request = (new ServerRequestFactory())->createServerRequest('DELETE', '/templates')
			->withParsedBody(['template_id' => 3, 'csrf_token' => 'forged']);

		$response = $this->templatesController()->delete($request, new Response());

		static::assertNotSame(200, $response->getStatusCode());
		static::assertSame(3, $this->countTemplates());
	}

	public function testUserMayNotLoadTheContentOfATemplate(): void
	{
		$this->actingAs(2);

		$response = $this->templatesController()->load($this->request('GET', []), new Response(), ['template_id' => '3']);

		static::assertStringNotContainsString('objects', (string) $response->getBody());
		static::assertStringContainsString('No rights', (string) $response->getBody());
	}

	public function testAdminLoadsTheContentWithFreshMediaUrls(): void
	{
		$this->actingAs(1);

		$response = $this->templatesController()->load($this->request('GET', []), new Response(), ['template_id' => '1']);

		$body = json_decode((string) $response->getBody(), true);
		static::assertIsArray($body);
		$content = json_decode($body['data']['content'] ?? $body['content'] ?? '', true);
		static::assertIsArray($content);
		static::assertStringContainsString('/orig/abc123.jpg', $content['objects'][0]['src']);
		static::assertStringContainsString('garlic-hub.test', $content['objects'][0]['src']);
	}

	public function testUserMayNotSaveATemplate(): void
	{
		$this->actingAs(2);
		$before = $this->template(3);

		$response = $this->templatesController()->save(
			$this->request('PATCH', ['template_id' => 3, 'content' => '{"objects":[{"type":"rect"}]}', 'image' => 'data:image/png;base64,AAAA']),
			new Response()
		);

		static::assertStringContainsString('No rights', (string) $response->getBody());
		static::assertSame($before, $this->template(3));
	}

	public function testDetermineRightsOfAnAdminAndAUser(): void
	{
		$this->actingAs(1);
		$admin = json_decode((string) $this->templatesController()->determineRights($this->request('GET', []), new Response(), ['template_id' => '3'])->getBody(), true);
		$this->actingAs(2);
		$user  = json_decode((string) $this->templatesController()->determineRights($this->request('GET', []), new Response(), ['template_id' => '3'])->getBody(), true);

		static::assertIsArray($admin);
		static::assertIsArray($user);
		static::assertTrue($admin['data']['can_edit'] ?? $admin['can_edit']);
		static::assertFalse($user['data']['can_edit'] ?? $user['can_edit']);
		static::assertFalse($user['data']['can_delete'] ?? $user['can_delete']);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function store(array $post): ResponseInterface
	{
		$post['csrf_token'] = $this->container->get(CsrfToken::class)->getToken();
		$request = (new ServerRequestFactory())->createServerRequest('POST', '/templates/settings')->withParsedBody($post);

		return $this->container->get(ShowSettingsController::class)->store($request, new Response());
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function request(string $method, array $data): \Psr\Http\Message\ServerRequestInterface
	{
		$data['csrf_token'] = $this->container->get(CsrfToken::class)->getToken();

		return (new ServerRequestFactory())->createServerRequest($method, '/templates')->withParsedBody($data);
	}

	private function templatesController(): TemplatesController
	{
		return $this->container->get(TemplatesController::class);
	}

	private function countTemplates(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM templates');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function template(int $templateId): array
	{
		$row = $this->connection->fetchAssociative('SELECT * FROM templates WHERE template_id = ?', [$templateId]);
		self::assertIsArray($row);

		return $row;
	}
}
