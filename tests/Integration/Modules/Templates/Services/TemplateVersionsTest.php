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

namespace Tests\Integration\Modules\Templates\Services;

use App\Framework\Config\ConfigOverrides;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Templates\Controller\TemplateVersionsController;
use App\Modules\Templates\Services\TemplatesService;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The versions of the templates. Users: 1 admin, 2 anna, 3 bernd.
 * Templates: 1 of admin, 2 of anna with {"objects":[]}, 3 of bernd without content.
 */
#[Group('acl')]
class TemplateVersionsTest extends ContainerTestCase
{
	private const string CONTENT_A = '{"objects":[{"type":"rect"}]}';
	private const string CONTENT_B = '{"objects":[{"type":"circle"}]}';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
	}

	/**
	 * @param array<string,string> $versioning
	 */
	private function enable(array $versioning = []): void
	{
		// before the first service with the versions is created
		$this->container->get(ConfigOverrides::class)->save('system', [
			'Scheduler'  => ['enabled' => '1'],
			'Audit'      => ['enabled' => '1'],
			'Versioning' => ['enabled' => '1'] + $versioning
		]);
	}

	private function service(): TemplatesService
	{
		return $this->container->get(TemplatesService::class);
	}

	private function save(int $templateId, string $content): void
	{
		$this->service()->updateContent($this->service()->loadWithUserById($templateId), $content);
	}

	private function content(int $templateId): ?string
	{
		$content = $this->connection->fetchOne('SELECT content FROM templates WHERE template_id = ?', [$templateId]);

		return is_string($content) ? $content : null;
	}

	/**
	 * @return list<int>
	 */
	private function versions(int $templateId): array
	{
		return array_column($this->service()->findVersions($templateId), 'version');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function json(ResponseInterface $response): array
	{
		$json = json_decode((string) $response->getBody(), true);

		return is_array($json) ? $json : [];
	}

	public function testNothingIsRecordedByDefault(): void
	{
		$this->actingAs(1);

		$this->save(2, self::CONTENT_A);

		static::assertFalse($this->service()->isVersioningEnabled());
		static::assertSame([], $this->versions(2));
	}

	public function testTheReplacedContentBecomesAVersion(): void
	{
		$this->enable();
		$this->actingAs(1);

		$this->save(2, self::CONTENT_A);
		$this->save(2, self::CONTENT_B);

		static::assertSame([2, 1], $this->versions(2));
		static::assertSame('admin', $this->service()->findVersions(2)[0]['username']);
	}

	public function testAnUnchangedOrEmptyContentIsNoVersion(): void
	{
		$this->enable();
		$this->actingAs(1);

		$this->save(2, '{"objects":[]}');
		$this->save(3, self::CONTENT_A);

		static::assertSame([], $this->versions(2));
		static::assertSame([], $this->versions(3));
	}

	public function testATemplateKeepsItsNewestVersionsOnly(): void
	{
		$this->enable(['max_versions' => '2']);
		$this->actingAs(1);

		$this->save(2, self::CONTENT_A);
		$this->save(2, self::CONTENT_B);
		$this->save(2, self::CONTENT_A);

		static::assertSame([3, 2], $this->versions(2));
	}

	public function testARestoreCanBeUndone(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->save(2, self::CONTENT_A);
		$this->save(2, self::CONTENT_B);

		$this->service()->restoreVersion(2, 2);

		static::assertSame(self::CONTENT_A, $this->content(2));
		static::assertSame([3, 2, 1], $this->versions(2));
		$this->service()->restoreVersion(2, 3);
		static::assertSame(self::CONTENT_B, $this->content(2));
	}

	public function testARestoreIsInTheAuditLog(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->save(2, self::CONTENT_A);

		$this->service()->restoreVersion(2, 1);

		static::assertSame(['UID' => 1, 'module' => 'templates', 'action' => 'version_restored', 'unit_id' => '2', 'details' => '{"version":1}'],
			$this->connection->fetchAssociative('SELECT UID, module, action, unit_id, details FROM audit_log'));
	}

	public function testAnUnknownVersionIsNotFound(): void
	{
		$this->enable();
		$this->actingAs(1);

		$this->expectException(NotFoundException::class);
		$this->service()->restoreVersion(2, 7);
	}

	public function testTheVersionsGoWithTheTemplate(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->save(2, self::CONTENT_A);

		$this->service()->delete(2);

		static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(1) FROM unit_versions'));
	}

	public function testTheDialogGetsTheVersionsAndItsLabels(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->save(2, self::CONTENT_A);

		$json = $this->json($this->container->get(TemplateVersionsController::class)->list(new ServerRequestFactory()->createServerRequest('GET', '/async/templates/versions/2'), new Response(), ['template_id' => '2']));

		static::assertTrue($json['success']);
		static::assertSame([1], array_column($json['versions'], 'version'));
		static::assertSame(['title', 'empty', 'restore', 'restore_confirm', 'close'], array_keys($json['labels']));
	}

	public function testWhoMayNotEditTheTemplateGetsNoVersions(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->save(2, self::CONTENT_A);
		$this->actingAs(3);
		$controller = $this->container->get(TemplateVersionsController::class);

		$list    = $this->json($controller->list(new ServerRequestFactory()->createServerRequest('GET', '/async/templates/versions/2'), new Response(), ['template_id' => '2']));
		$restore = $this->json($controller->restore(new ServerRequestFactory()->createServerRequest('POST', '/async/templates/versions/2')->withParsedBody(['version' => '1']), new Response(), ['template_id' => '2']));

		static::assertFalse($list['success']);
		static::assertFalse($restore['success']);
		static::assertSame(self::CONTENT_A, $this->content(2));
	}

	public function testTheRestoreOpensTheComposer(): void
	{
		$this->enable();
		$this->actingAs(1);
		$this->save(2, self::CONTENT_A);

		$json = $this->json($this->container->get(TemplateVersionsController::class)->restore(
			new ServerRequestFactory()->createServerRequest('POST', '/async/templates/versions/2')->withParsedBody(['version' => '1']), new Response(), ['template_id' => '2']));

		static::assertSame(['success' => true, 'redirect' => '/templates/composer/2'], $json);
		static::assertSame('{"objects":[]}', $this->content(2));
	}
}
