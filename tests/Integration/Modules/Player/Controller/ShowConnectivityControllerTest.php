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

namespace Tests\Integration\Modules\Player\Controller;

use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Player\Controller\ShowConnectivityController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The page /player/connectivity/{id} where the admin sets how the server reaches a player: the address of its API
 * and whether it is in the network. The form is shown and stored through the real validation and the database.
 */
#[Group('acl')]
class ShowConnectivityControllerTest extends PlayerControllerTestCase
{
	private ShowConnectivityController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(ShowConnectivityController::class);
	}

	private function show(int|string $playerId): ResponseInterface
	{
		return $this->controller->show($this->request('GET', '/player/connectivity/' . $playerId), $this->newResponse(), ['player_id' => (string) $playerId]);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function store(array $body): ResponseInterface
	{
		return $this->controller->store($this->request('POST', '/player/connectivity', $body), $this->newResponse());
	}

	/**
	 * @return list<string>
	 */
	private function flash(string $type): array
	{
		/** @var array<string,list<string>> $flash */
		$flash = $_SESSION['slimFlash'] ?? [];

		return $flash[$type] ?? [];
	}

	private function page(ResponseInterface $response): PageResponse
	{
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @return array<string,string> the html of the fields of the form by their names
	 */
	private function fields(PageResponse $page): array
	{
		$fields = [];
		$data   = $page->getTemplateData();
		foreach (array_merge(array_column($data['form_element'], 'HTML_ELEMENT'), array_column($data['element_hidden'], 'HIDDEN_HTML_ELEMENT')) as $html)
		{
			if (preg_match('/name="([^"]+)"/', $html, $match) === 1)
				$fields[$match[1]] = $html;
		}

		return $fields;
	}

	private function assertRedirect(ResponseInterface $response, string $location): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}

	public function testTheFormShowsTheNetworkSettingsOfThePlayer(): void
	{
		$this->setPlayer(3, 'is_intranet = 1, api_endpoint = ?', 'http://10.0.0.7:8080/v2');
		$this->actingAs(1);

		$page = $this->page($this->show(3));

		static::assertSame('player/edit', $page->getTemplate());
		static::assertSame('/player/connectivity', $page->getTemplateData()['FORM_ACTION']);
		static::assertStringContainsString('Bernds Player', $page->getTemplateData()['LANG_PAGE_HEADER']);
		$fields = $this->fields($page);
		static::assertEqualsCanonicalizing(['api_endpoint', 'is_intranet', 'player_id', 'csrf_token'], array_keys($fields));
		static::assertStringContainsString('value="http://10.0.0.7:8080/v2"', $fields['api_endpoint']);
		static::assertStringContainsString('checked', $fields['is_intranet']);
		static::assertStringContainsString('value="3"', $fields['player_id']);
	}

	public function testThePlayerWhichIsNotInTheNetworkHasTheBoxNotChecked(): void
	{
		$this->actingAs(1);

		static::assertStringNotContainsString('checked', $this->fields($this->page($this->show(3)))['is_intranet']);
	}

	/**
	 * @return array<string,array{int,int|string}> the user and the id
	 */
	public static function pagesWhichAreNotAccessible(): array
	{
		return [
			'a player of another user' => [2, 3],
			'an unknown player'        => [1, 999],
			'no id'                    => [1, 0],
			'text'                     => [1, 'abc'],
		];
	}

	#[DataProvider('pagesWhichAreNotAccessible')]
	public function testAPageWhichIsNotAccessibleLeadsBackToTheListWithAMessage(int $user, int|string $playerId): void
	{
		$this->actingAs($user);

		$this->assertRedirect($this->show($playerId), '/player');
		static::assertCount(1, $this->flash('error'));
	}

	public function testTheSettingsAreStored(): void
	{
		$this->actingAs(1);

		$response = $this->store(['player_id' => '3', 'is_intranet' => '1', 'api_endpoint' => 'http://10.0.0.7:8080/v2']);

		$this->assertRedirect($response, '/player');
		static::assertSame('http://10.0.0.7:8080/v2', $this->playerField(3, 'api_endpoint'));
		static::assertSame(1, (int) $this->playerField(3, 'is_intranet'));
		static::assertSame(['Player connectivity data successfully stored.'], $this->flash('success'));
	}

	public function testTheBoxWhichIsNotSentMeansNotInTheNetwork(): void
	{
		$this->setPlayer(3, 'is_intranet = 1, api_endpoint = ?', 'http://10.0.0.7:8080/v2');
		$this->actingAs(1);

		$this->store(['player_id' => '3', 'api_endpoint' => 'http://10.0.0.7:8080/v2']);

		static::assertSame(0, (int) $this->playerField(3, 'is_intranet'));
	}

	public function testASaveWithoutChangesIsNoError(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store(['player_id' => '3']), '/player');
		static::assertSame([], $this->flash('error'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongSettings(): array
	{
		return [
			'in the network, without an address' => [['is_intranet' => '1', 'api_endpoint' => '']],
			'a file'                             => [['api_endpoint' => 'file:///etc/passwd']],
			'an address with a user'             => [['api_endpoint' => 'http://admin:secret@10.0.0.7/']],
			'an address with a query'            => [['api_endpoint' => 'http://10.0.0.7/?x=1']],
			'no host'                            => [['is_intranet' => '1', 'api_endpoint' => 'http:///v2']],
		];
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	#[DataProvider('wrongSettings')]
	public function testWrongSettingsShowTheFormAgainAndChangeNothing(array $settings): void
	{
		$this->actingAs(1);
		$before = $this->connection->fetchAssociative('SELECT api_endpoint, is_intranet FROM player WHERE player_id = 3');

		$response = $this->store(['player_id' => '3'] + $settings);

		$page = $this->page($response);
		static::assertSame('player/edit', $page->getTemplate());
		static::assertSame($before, $this->connection->fetchAssociative('SELECT api_endpoint, is_intranet FROM player WHERE player_id = 3'));
		static::assertStringContainsString('value="3"', $this->fields($page)['player_id']);
		static::assertSame([], $this->flash('success'));
	}

	public function testTheFormAfterAMistakeShowsWhatWasEnteredEscaped(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['player_id' => '3', 'api_endpoint' => 'ftp://"><script>alert(1)</script>']));

		$html = $this->fields($page)['api_endpoint'];
		static::assertStringNotContainsString('<script>', $html);
		static::assertStringContainsString('&lt;script&gt;', $html);
	}

	/**
	 * Input in a form the page never sends: the form is shown again without an error of the server.
	 */
	public function testAnAddressAsAListIsAnEmptyAddress(): void
	{
		$this->actingAs(1);

		$page = $this->page($this->store(['player_id' => '3', 'is_intranet' => '1', 'api_endpoint' => ['http://10.0.0.7']]));

		static::assertStringContainsString('value=""', $this->fields($page)['api_endpoint']);
		static::assertContains($this->text('no_api_endpoint'), $this->container->get(\Slim\Flash\Messages::class)->getMessages()['error'] ?? []);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function noPlayers(): array
	{
		return [
			'nothing'  => [[]],
			'a list'   => [['player_id' => ['3']]],
			'text'     => [['player_id' => 'bernd']],
			'unknown'  => [['player_id' => '999']],
		];
	}

	/**
	 * A list is not taken for the player 1: nothing is stored for a player who is not meant.
	 *
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('noPlayers')]
	public function testAStoreWithoutAUsablePlayerLeadsBackToTheList(array $body): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->store($body + ['api_endpoint' => 'http://10.0.0.7:8080/v2']), '/player');
		static::assertSame('', $this->playerField(1, 'api_endpoint'));
		static::assertSame('', $this->playerField(3, 'api_endpoint'));
	}

	public function testThePlayerOfAnotherUserIsNotChanged(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->store(['player_id' => '3', 'api_endpoint' => 'http://10.0.0.7:8080/v2']), '/player');
		static::assertSame('', $this->playerField(3, 'api_endpoint'));
	}

	public function testARequestWithoutABodyLeadsBackToTheList(): void
	{
		$this->actingAs(1);
		$request = new \Slim\Psr7\Factory\ServerRequestFactory()->createServerRequest('POST', '/player/connectivity');

		$this->assertRedirect($this->controller->store($request, $this->newResponse()), '/player');
	}
}
