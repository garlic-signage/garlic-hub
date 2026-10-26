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

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Templates\Controller\ShowSettingsController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\HtmlFields;

/**
 * The settings of a template /templates/settings: the form for a new and for an existing template and storing it.
 * Only the admin may create and change templates. The controller keeps the input of the request, so every test
 * makes one request.
 */
#[Group('acl')]
class ShowSettingsControllerTest extends TemplatesControllerTestCase
{
	private ShowSettingsController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ShowSettingsController::class);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function store(array $body): ResponseInterface
	{
		return $this->controller->store($this->request(body: $body), $this->newResponse());
	}

	private function page(ResponseInterface $response): PageResponse
	{
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @return array<string,array<string,string>> the attributes of the fields of the form by their names
	 */
	private function fields(PageResponse $page): array
	{
		$data = $page->getTemplateData();

		return HtmlFields::fromPrepared(['visible' => $data['form_element'], 'hidden' => $data['element_hidden']]);
	}

	public function testTheAdminGetsTheFormOfANewCanvasTemplate(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->controller->create($this->request('GET'), $this->newResponse()));

		static::assertSame('templates/edit', $page->getTemplate());
		$fields = $this->fields($page);
		static::assertSame(['name', 'UID_search', 'visibility', 'type', 'csrf_token'], array_keys($fields));
		static::assertSame('canvas', $fields['type']['value']);
		static::assertSame('', $fields['name']['value']);
	}

	public function testAUserGetsNoFormOfANewTemplate(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->controller->create($this->request('GET'), $this->newResponse()), '/templates', 'No rights');
	}

	public function testTheFormOfATemplateHasItsValues(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->controller->edit($this->request('GET'), $this->newResponse(), ['template_id' => '2']));

		$fields = $this->fields($page);
		static::assertSame(['name', 'UID_search', 'visibility', 'template_id', 'csrf_token'], array_keys($fields));
		static::assertSame(['Annas Vorlage', '2'], [$fields['name']['value'], $fields['template_id']['value']]);
		static::assertStringContainsString('anna', json_encode($page->getTemplateData(), JSON_THROW_ON_ERROR));
	}

	public function testTheFormOfATemplateIsOnlyGivenToTheAdmin(): void
	{
		$this->actingAs(2);
		$this->assertRedirect($this->controller->edit($this->request('GET'), $this->newResponse(), ['template_id' => '2']), '/templates', 'No rights');
	}

	public function testAnUnknownTemplateOrNoTemplateHasNoForm(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->controller->edit($this->request('GET'), $this->newResponse(), ['template_id' => '99']), '/templates', 'No rights');
	}

	public function testANewTemplateBelongsToTheOwnerOfTheForm(): void
	{
		$this->actingAs(1);

		$response = $this->store(['name' => 'Neu', 'visibility' => 'global', 'type' => 'canvas', 'UID' => '3']);

		$this->assertRedirect($response, '/templates');
		static::assertSame(['Templated saved successfully'], $this->flash('success'));
		$row = $this->connection->fetchAssociative("SELECT UID, visibility, type FROM templates WHERE name = 'Neu'");
		static::assertIsArray($row);
		static::assertSame([3, 'global', 'canvas'], [(int) $row['UID'], $row['visibility'], $row['type']]);
	}

	public function testANewTemplateWithoutATypeIsACanvasTemplate(): void
	{
		$this->actingAs(1);

		$this->store(['name' => 'Neu']);

		static::assertSame('canvas', $this->databaseValue("SELECT type FROM templates WHERE name = 'Neu'"));
		static::assertSame('private', $this->databaseValue("SELECT visibility FROM templates WHERE name = 'Neu'"));
	}

	public function testATypeWhichIsNotInTheListIsRejected(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['name' => 'Neu', 'type' => 'evil']));

		static::assertSame('templates/edit', $page->getTemplate());
		static::assertSame([$this->text('template_type_noexists')], $this->flash('error'));
		static::assertSame(3, $this->countTemplates());
	}

	public function testAUserCanNotCreateATemplate(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->store(['name' => 'Neu']), '/templates', 'No rights');
		static::assertSame(3, $this->countTemplates());
	}

	public function testATemplateNeedsAName(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['name' => '', 'visibility' => 'global']));

		static::assertSame('templates/edit', $page->getTemplate());
		static::assertSame([$this->text('name_noexists')], $this->flash('error'));
		static::assertSame(3, $this->countTemplates());
	}

	public function testTheSettingsOfATemplateAreChanged(): void
	{
		$this->actingAs(1);

		$response = $this->store(['template_id' => '2', 'name' => 'Umbenannt', 'visibility' => 'global', 'UID' => '3', 'type' => 'svg']);

		$this->assertRedirect($response, '/templates');
		$row = $this->connection->fetchAssociative('SELECT UID, visibility, type, name FROM templates WHERE template_id = 2');
		static::assertIsArray($row);
		// the type is the one of the creation
		static::assertSame([3, 'global', 'fabric.js', 'Umbenannt'], [(int) $row['UID'], $row['visibility'], $row['type'], $row['name']]);
	}

	public function testChangingAFormWithoutChangesIsNoError(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['template_id' => '1', 'name' => 'Admin Vorlage', 'visibility' => 'global', 'UID' => '1']), '/templates');
		static::assertSame([], $this->flash('error'));
	}

	public function testAFormWithErrorsOfAnExistingTemplateIsShownAgain(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['template_id' => '2', 'name' => '']));

		static::assertSame('templates/edit', $page->getTemplate());
		static::assertSame([$this->text('name_noexists')], $this->flash('error'));
		static::assertSame('Annas Vorlage', $this->databaseValue('SELECT name FROM templates WHERE template_id = 2'));
	}

	public function testATemplateOfAnotherUserCanNotBeStoredByAUser(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->store(['template_id' => '3', 'name' => 'Hack']), '/templates', 'No rights');
		static::assertSame('Bernds Vorlage', $this->databaseValue('SELECT name FROM templates WHERE template_id = 3'));
	}

	public function testTheValuesOfTheUserStayInTheFormOfANewTemplateAfterAnError(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['name' => 'Neu', 'visibility' => 'global', 'UID' => '3', 'type' => 'fabric.js']));

		$fields = $this->fields($page);
		static::assertSame('Neu', $fields['name']['value']);
		// a form of a new template: the type, no id; the owner is the one of the form, the name of a changed owner is not known
		static::assertSame('canvas', $fields['type']['value']);
		static::assertArrayNotHasKey('template_id', $fields);
		static::assertStringContainsString('admin', json_encode($page->getTemplateData(), JSON_THROW_ON_ERROR));
		static::assertStringContainsString('global', json_encode($page->getTemplateData(), JSON_THROW_ON_ERROR));
	}

	public function testTheValuesOfTheUserStayInTheFormOfAnExistingTemplateAfterAnError(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['template_id' => '2', 'name' => '', 'visibility' => 'global', 'UID' => '3']));

		$fields = $this->fields($page);
		static::assertSame(['', '2'], [$fields['name']['value'], $fields['template_id']['value']]);
		// the owner of the template stays anna
		static::assertStringContainsString('anna', json_encode($page->getTemplateData(), JSON_THROW_ON_ERROR));
		static::assertStringNotContainsString('bernd', json_encode($page->getTemplateData(), JSON_THROW_ON_ERROR));
		static::assertSame('Annas Vorlage', $this->databaseValue('SELECT name FROM templates WHERE template_id = 2'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}> the owner which is no user: it leaves the owner as it is
	 */
	public static function ownersWhichAreNoUsers(): array
	{
		return ['none' => [[]], 'text' => [['UID' => 'abc']], 'an array' => [['UID' => ['3']]], 'zero' => [['UID' => '0']], 'negative' => [['UID' => '-3']]];
	}

	/**
	 * @param array<string,mixed> $owner
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('ownersWhichAreNoUsers')]
	public function testANewTemplateWithoutAValidOwnerBelongsToTheLoggedInUser(array $owner): void
	{
		$this->actingAs(1);

		$this->store(['name' => 'Neu', 'visibility' => 'private'] + $owner);

		static::assertSame(1, (int) $this->databaseValue("SELECT UID FROM templates WHERE name = 'Neu'"));
	}

	/**
	 * @param array<string,mixed> $owner
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('ownersWhichAreNoUsers')]
	public function testATemplateWithoutAValidOwnerKeepsItsOwner(array $owner): void
	{
		$this->actingAs(1);

		$this->store(['template_id' => '2', 'name' => 'Umbenannt', 'visibility' => 'private'] + $owner);

		static::assertSame(['Umbenannt', 2], [$this->databaseValue('SELECT name FROM templates WHERE template_id = 2'), (int) $this->databaseValue('SELECT UID FROM templates WHERE template_id = 2')]);
	}

	/**
	 * @return array<string,array{mixed,string}> the visibility of the request and the one which is stored
	 */
	public static function visibilities(): array
	{
		return ['private' => ['private', 'private'], 'global' => ['global', 'global'], 'unknown' => ['evil', 'private'], 'public' => ['public', 'private'], 'an array' => [['global'], 'private'], 'empty' => ['', 'private']];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('visibilities')]
	public function testANewTemplateIsOnlyGlobalOrPrivate(mixed $sent, string $stored): void
	{
		$this->actingAs(1);

		$this->store(['name' => 'Neu', 'visibility' => $sent]);

		static::assertSame($stored, $this->databaseValue("SELECT visibility FROM templates WHERE name = 'Neu'"));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('visibilities')]
	public function testAnExistingTemplateIsOnlyGlobalOrPrivate(mixed $sent, string $stored): void
	{
		$this->actingAs(1);

		$this->store(['template_id' => '3', 'name' => 'Bernds Vorlage', 'visibility' => $sent]);

		static::assertSame($stored, $this->databaseValue('SELECT visibility FROM templates WHERE template_id = 3'));
	}

	/**
	 * What the browser can not send but a script can: an id which is no id is neither the template 1 nor a new template.
	 */
	public function testAnIdOfTheWrongTypeIsNoTemplate(): void
	{
		$this->actingAs(1);

		foreach (['2' => ['2'], 'text' => 'abc', 'negative' => '-1', 'decimal' => '1.5'] as $id)
		{
			$this->assertRedirect($this->store(['template_id' => $id, 'name' => 'Hack']), '/templates');
			static::assertSame(['Template ID not valid.'], $this->flash('error'));
			unset($_SESSION['slimFlash']);
		}
		static::assertSame(['Admin Vorlage', 'Annas Vorlage', 'Bernds Vorlage'], $this->connection->fetchFirstColumn('SELECT name FROM templates ORDER BY template_id'));
	}

	public function testNoIdOrZeroIsANewTemplate(): void
	{
		$this->actingAs(1);

		foreach (['', '0', 0] as $n => $id)
			$this->store(['template_id' => $id, 'name' => 'Neu ' . $n]);

		static::assertSame(6, $this->countTemplates());
	}

	public function testWithoutABodyTheFormOfANewTemplateIsShownWithErrors(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->controller->store($this->request()->withParsedBody(null), $this->newResponse()));

		static::assertSame('templates/edit', $page->getTemplate());
		static::assertNotSame([], $this->flash('error'));
		static::assertSame(3, $this->countTemplates());
	}
}
