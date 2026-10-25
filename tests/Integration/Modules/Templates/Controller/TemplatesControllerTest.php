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

namespace Tests\Integration\Modules\Templates\Controller;

use App\Framework\Config\Config;
use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\MediaUrlSigner;
use App\Framework\Security\CsrfToken;
use App\Modules\Templates\Controller\TemplatesController;
use App\Modules\Templates\Helper\Composer\ExportImage;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Helper\Composer\TemplatePreparer;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\TemplatesDatatableService;
use App\Modules\Templates\Services\TemplatesService;
use App\Modules\Templates\Services\TemplatesUsageService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\MediaWorkspace;
use Throwable;

/**
 * The template endpoints /async/templates: delete, rights, load, find and save. The images of save go into a temporary directory.
 */
#[Group('acl')]
class TemplatesControllerTest extends TemplatesControllerTestCase
{
	private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private MediaWorkspace $workspace;
	private TemplatesController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		foreach (['originals', 'thumbs'] as $sub)
			mkdir($this->workspace->dir . '/var/storage/templates/' . $sub, 0775, true);

		// like config/services/templates.php, but the images go into the workspace
		$this->controller = new TemplatesController(
			new Orchestrator(
				$this->container->get(TemplatePreparer::class),
				$this->container->get(TemplatesUsageService::class),
				new ExportImage($this->container->get(DataUrlDecoder::class), $this->workspace->handlerFactory()),
				$this->container->get(TemplatesService::class),
				$this->container->get(Config::class),
				$this->container->get(MediaUrlSigner::class)
			),
			$this->container->get(TemplatesDatatableService::class),
			$this->container->get(Parameters::class),
			$this->container->get(JsonResponseHandler::class),
			$this->container->get(CsrfToken::class),
			$this->container->get(MediaUrlSigner::class)
		);
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * A template is used when an item of a playlist shows it.
	 */
	private function useTemplate(int $templateId): void
	{
		$this->connection->executeStatement("UPDATE playlists_items SET item_type = 'template', file_resource = ? WHERE item_id = 1", [(string) $templateId]);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function save(array $body, bool $withToken = true): \Psr\Http\Message\ResponseInterface
	{
		return $this->controller->save($this->request('PATCH', body: $withToken ? $this->withToken($body) : $body), $this->newResponse());
	}

	public function testAnUnusedTemplateIsDeleted(): void
	{
		$this->actingAs(1);

		$this->assertSuccess($this->controller->delete($this->request('DELETE', body: $this->withToken(['template_id' => '3'])), $this->newResponse()));

		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM templates WHERE template_id = 3'));
		static::assertSame(2, $this->countTemplates());
	}

	public function testATemplateInUseIsNotDeleted(): void
	{
		$this->actingAs(1);
		$this->useTemplate(3);

		try
		{
			$this->controller->delete($this->request('DELETE', body: $this->withToken(['template_id' => '3'])), $this->newResponse());
			static::fail('Expected TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('template_in_use', $e->getTranslationKey());
			static::assertSame(3, $this->countTemplates());
		}
	}

	public function testDeleteNeedsTheTokenAndATemplate(): void
	{
		$this->actingAs(1);

		$this->assertError($this->controller->delete($this->request('DELETE', body: ['template_id' => '3']), $this->newResponse()), 'CSRF token mismatch.');
		$this->assertError($this->controller->delete($this->request('DELETE', body: ['csrf_token' => 'wrong', 'template_id' => '3']), $this->newResponse()), 'CSRF token mismatch.');
		$this->assertError($this->controller->delete($this->request('DELETE', body: $this->withToken([])), $this->newResponse()), 'No template Id');
		static::assertSame(3, $this->countTemplates());
	}

	public function testAnUnknownTemplateIsNotFoundAndAForeignOneIsDenied(): void
	{
		$this->actingAs(1);
		try
		{
			$this->controller->delete($this->request('DELETE', body: $this->withToken(['template_id' => '99'])), $this->newResponse());
			static::fail('Expected NotFoundException');
		}
		catch (NotFoundException)
		{
			static::assertSame(3, $this->countTemplates());
		}

		$this->actingAs(2);
		$this->expectException(AccessDeniedException::class);
		$this->controller->delete($this->request('DELETE', body: $this->withToken(['template_id' => '2'])), $this->newResponse());
	}

	public function testTheRightsOfAnAdminForAnUnusedTemplate(): void
	{
		$this->actingAs(1);

		$rights = $this->json($this->controller->determineRights($this->request('GET'), $this->newResponse(), ['template_id' => '2']));

		static::assertSame(['success' => true, 'can_edit' => true, 'can_delete' => true, 'template_id' => 2], $rights);
	}

	public function testATemplateInUseCanBeEditedButNotDeleted(): void
	{
		$this->actingAs(1);
		$this->useTemplate(2);

		$rights = $this->json($this->controller->determineRights($this->request('GET'), $this->newResponse(), ['template_id' => '2']));

		static::assertSame(['success' => true, 'can_edit' => true, 'can_delete' => false, 'template_id' => 2], $rights);
	}

	/**
	 * Asking for the rights is no error: the answer is "nothing".
	 *
	 * @return array<string,array{int,array<string,string>}>
	 */
	public static function nothingAllowed(): array
	{
		return [
			'a user'              => [2, ['template_id' => '2']],
			'an unknown template' => [1, ['template_id' => '99']],
			'no template'         => [1, []],
			'text as template'    => [1, ['template_id' => 'abc']],
		];
	}

	/**
	 * @param array<string,string> $args
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('nothingAllowed')]
	public function testWhoMayNotEditGetsNothingAllowedAndNoError(int $user, array $args): void
	{
		$this->actingAs($user);

		$this->assertSuccess($this->controller->determineRights($this->request('GET'), $this->newResponse(), $args));

		static::assertSame(['success' => true, 'can_edit' => false, 'can_delete' => false], $this->json($this->controller->determineRights($this->request('GET'), $this->newResponse(), $args)));
	}

	public function testTheContentIsLoadedWithFreshSignedUrls(): void
	{
		$this->actingAs(1);

		$content = json_decode($this->json($this->controller->load($this->request('GET'), $this->newResponse(), ['template_id' => '1']))['content'], true);

		static::assertSame('abc123.jpg', $content['objects'][0]['fileName']);
		static::assertMatchesRegularExpression('#^http://garlic-hub\.test/media\.php/orig/abc123\.jpg\?exp=\d+&sig=[0-9a-f]{64}$#', $content['objects'][0]['src']);
	}

	public function testATemplateWithoutAUsableContentLoadsAsEmpty(): void
	{
		$this->actingAs(1);

		foreach ([null, '', 'not json', '{"foo":1}', '{"objects":"x"}'] as $content)
		{
			$this->connection->executeStatement('UPDATE templates SET content = ? WHERE template_id = 3', [$content]);

			static::assertSame('', $this->json($this->controller->load($this->request('GET'), $this->newResponse(), ['template_id' => '3']))['content'], (string) $content);
		}
	}

	public function testTheContentIsOnlyLoadedForWhoMayEditTheTemplate(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), ['template_id' => '1']), 'No rights');
		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), ['template_id' => '99']), 'No rights');
		$this->assertError($this->controller->load($this->request('GET'), $this->newResponse(), []), 'No rights');
	}

	public function testTheAdminFindsAllTemplatesWithTheirThumbnails(): void
	{
		$this->actingAs(1);

		$templates = $this->json($this->controller->find($this->request('GET'), $this->newResponse()))['templates'];

		static::assertSame([[1, 'Admin Vorlage'], [2, 'Annas Vorlage'], [3, 'Bernds Vorlage']], array_map(static fn(array $t): array => [$t['id'], $t['name']], $templates));
		static::assertMatchesRegularExpression('#^/media\.php/tpl-thumb/1\.jpg\?exp=\d+&sig=[0-9a-f]{64}$#', $templates[0]['src']);
	}

	public function testAUserFindsOnlyHisOwnTemplates(): void
	{
		$this->actingAs(2);

		$templates = $this->json($this->controller->find($this->request('GET'), $this->newResponse()))['templates'];

		static::assertSame([2], array_column($templates, 'id'));
	}

	public function testTheNumberOfTheFoundTemplatesCanBeLimited(): void
	{
		$this->actingAs(1);

		$request = $this->request('GET')->withQueryParams(['elements_per_page' => '2']);

		static::assertCount(2, $this->json($this->controller->find($request, $this->newResponse()))['templates']);
	}

	public function testTheContentAndTheImageAreSavedWithoutTheSourcesOfTheImages(): void
	{
		$this->actingAs(1);
		$content = '{"objects":[{"type":"image","fileName":"abc123.jpg","src":"http://evil.example/x.jpg"}]}';

		$this->assertSuccess($this->save(['template_id' => '3', 'content' => $content, 'image' => self::PNG]));

		static::assertSame(['objects' => [['type' => 'image', 'fileName' => 'abc123.jpg']]], json_decode((string) $this->databaseValue('SELECT content FROM templates WHERE template_id = 3'), true));
		static::assertSame(['3.png'], array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/templates/originals/*')));
	}

	/**
	 * The editor of the templates saves jpg: the lists look for <id>.jpg.
	 */
	public function testTheThumbnailOfAJpgHasTheNameWhichTheListsUse(): void
	{
		$this->actingAs(1);
		$image = new \Imagick();
		$image->newImage(2, 2, 'white');
		$image->setImageFormat('jpeg');

		$this->assertSuccess($this->save(['template_id' => '3', 'content' => '{"objects":[]}', 'image' => 'data:image/jpeg;base64,' . base64_encode($image->getImageBlob())]));

		static::assertSame(['3.jpg'], array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/templates/thumbs/*')));
		static::assertSame(['3.jpg'], array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/templates/originals/*')));
	}

	public function testSaveNeedsTheTokenATemplateAndRights(): void
	{
		$body = ['template_id' => '3', 'content' => '{"objects":[]}', 'image' => self::PNG];

		$this->actingAs(1);
		$this->assertError($this->save($body, withToken: false), 'CSRF token mismatch.');
		$this->assertError($this->save(['template_id' => '0'] + $body), 'No template Id');
		$this->assertError($this->save(['template_id' => '99'] + $body), 'No rights');
		$this->actingAs(2);
		$this->assertError($this->save($body), 'No rights');
		static::assertNull($this->databaseValue('SELECT content FROM templates WHERE template_id = 3'));
	}

	public function testAContentWithoutObjectsOrWhichIsToBigOrAFileWhichIsNoImageIsNotSaved(): void
	{
		$this->actingAs(1);
		$tooMany = json_encode(['objects' => array_fill(0, 1001, ['type' => 'rect'])], JSON_THROW_ON_ERROR);

		$this->assertError($this->save(['template_id' => '3', 'content' => '{"foo":1}', 'image' => self::PNG]), 'Save failed');
		$this->assertError($this->save(['template_id' => '3', 'content' => 'no json', 'image' => self::PNG]), 'Save failed');
		$this->assertError($this->save(['template_id' => '3', 'content' => $tooMany, 'image' => self::PNG]), 'Save failed');
		$this->assertError($this->save(['template_id' => '3', 'content' => '{"objects":[]}', 'image' => 'data:image/png;base64,' . base64_encode('this is not an image')]), 'Save failed');
		static::assertNull($this->databaseValue('SELECT content FROM templates WHERE template_id = 3'));
		static::assertSame([], (array) glob($this->workspace->dir . '/var/storage/templates/originals/*'));
	}

	public function testAnImageWhichIsNoDataUrlIsAnError(): void
	{
		static::markTestSkipped('Known bug: an image which is no valid data URL throws a FrameworkException instead of the answer "Save failed". Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->actingAs(1);

		$this->assertError($this->save(['template_id' => '3', 'content' => '{"objects":[]}', 'image' => 'no image']), 'Save failed');
		$this->assertError($this->save(['template_id' => '3', 'content' => '{"objects":[]}', 'image' => ['x']]), 'Save failed');
	}

	/**
	 * What the browser can not send but a script can: values of the wrong type are no valid values.
	 */
	public function testValuesOfTheWrongTypeAreRejectedLikeMissingValues(): void
	{
		$this->actingAs(1);
		$cases = [
			[fn() => $this->controller->delete($this->request('DELETE', body: $this->withToken(['template_id' => ['3']])), $this->newResponse()), 'No template Id'],
			[fn() => $this->controller->delete($this->request('DELETE', body: $this->withToken(['template_id' => '3abc'])), $this->newResponse()), 'No template Id'],
			[fn() => $this->controller->delete($this->request('DELETE', body: ['csrf_token' => ['x'], 'template_id' => '3']), $this->newResponse()), 'CSRF token mismatch.'],
			[fn() => $this->controller->delete($this->request('DELETE')->withParsedBody(null), $this->newResponse()), 'CSRF token mismatch.'],
			[fn() => $this->save(['template_id' => ['3'], 'content' => '{"objects":[]}', 'image' => self::PNG]), 'No template Id'],
			[fn() => $this->save(['csrf_token' => ['x'], 'template_id' => '3'], withToken: false), 'CSRF token mismatch.'],
			[fn() => $this->save(['template_id' => '3', 'content' => ['x'], 'image' => self::PNG]), 'Save failed'],
		];
		foreach ($cases as $n => [$call, $message])
		{
			try
			{
				$this->assertError($call(), $message);
			}
			catch (Throwable $e)
			{
				static::fail('case ' . $n . ': ' . $e::class . ': ' . $e->getMessage());
			}
		}
		static::assertSame(3, $this->countTemplates());
		static::assertNull($this->databaseValue('SELECT content FROM templates WHERE template_id = 3'));
	}
}
