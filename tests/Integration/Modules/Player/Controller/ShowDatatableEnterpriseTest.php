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

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\DatatableTemplatePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use App\Framework\Validation\Sanitizer;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Controller\ShowDatatableController;
use App\Modules\Player\Helper\Datatable\DatatableBuilder;
use App\Modules\Player\Helper\Datatable\DatatablePreparer;
use App\Modules\Player\Helper\Datatable\Orchestrator;
use App\Modules\Player\Helper\Datatable\Parameters;
use App\Modules\Player\Helper\Datatable\PlayerFilterDefinition;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Services\PlayerDatatableService;
use App\Shared\Acl\AclHelper;
use App\Shared\User\UsersService;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * The list of the players in the enterprise edition: the admin has the filter by activity and by owner and sees the owner.
 * The parts are built here with the AclValidator of this edition, the rest is the real application.
 */
#[Group('acl')]
class ShowDatatableEnterpriseTest extends PlayerControllerTestCase
{
	/**
	 * @param array<string,string> $query
	 */
	private function list(array $query = []): PageResponse
	{
		$root   = (string) realpath(__DIR__ . '/../../../../..');
		$config = new Config(new IniConfigLoader($root . '/config/settings'), ['systemDir' => $root], ['APP_PLATFORM_EDITION' => Config::PLATFORM_EDITION_ENTERPRISE]);
		$acl    = new AclValidator(new AclHelper($this->container->get(UsersService::class), $config));
		$parameters = new Parameters($this->container->get(Sanitizer::class), $this->container->get(Session::class));
		$translator = $this->container->get(Translator::class);
		$conditions = new FilterConditions();

		$orchestrator = new Orchestrator(
			new DatatableBuilder($this->container->get(BuildService::class), $parameters, $acl, $translator),
			new DatatablePreparer($this->container->get(PrepareService::class), $parameters, $this->container->get(TimeUnitsCalculator::class), $translator, new DateTimeZone('UTC')),
			new PlayerDatatableService(
				new PlayerFilterDefinition($conditions, $this->connection->getDatabasePlatform()),
				new DatatableQuery($this->connection),
				$parameters,
				$acl,
				$this->container->get(UserSession::class),
				new NullLogger()
			),
			$this->container->get(UserSession::class)
		);
		$controller = new ShowDatatableController($orchestrator, $this->container->get(DatatableTemplatePreparer::class));
		$response   = $controller->show($this->request('GET', '/player')->withQueryParams($query), $this->newResponse());
		static::assertInstanceOf(PageResponse::class, $response);

		return $response;
	}

	/**
	 * @param array<string,string> $query
	 * @return list<int>
	 */
	private function idsOf(array $query = []): array
	{
		return array_values(array_map(static fn(array $row): int => (int) $row['UNIT_ID'], $this->list($query)->getTemplateData()['elements_results']));
	}

	private function lastAccessSecondsAgo(int $playerId, int $seconds): void
	{
		$this->connection->executeStatement('UPDATE player SET refresh = 300, last_access = ? WHERE player_id = ?', [gmdate('Y-m-d H:i:s', time() - $seconds), $playerId]);
	}

	public function testTheAdminHasTheOwnerColumnAndTheFiltersOfTheEnterpriseEdition(): void
	{
		$this->actingAs(1);

		$data = $this->list()->getTemplateData();

		static::assertSame(['last_access', 'player_name', 'UID', 'model', 'playlist_id'], array_column($data['elements_result_header'], 'CONTROL_NAME'));
		static::assertEqualsCanonicalizing(['activity', 'player_name', 'model', 'UID'], array_column($data['form_element'], 'LANG_ELEMENT_NAME'));
	}

	public function testTheOwnerIsShownWithItsName(): void
	{
		$this->actingAs(1);

		$rows = $this->list()->getTemplateData()['elements_results'];
		$cells = [];
		foreach ($rows[0]['elements_result_element'] as $element)
			$cells[$element['CONTROL_NAME_BODY']] = $element;

		static::assertStringContainsString('anna', json_encode($cells['UID']['is_UID']) ?: '');
	}

	public function testTheFilterByActivity(): void
	{
		$this->actingAs(1);
		$this->lastAccessSecondsAgo(1, 10);
		$this->lastAccessSecondsAgo(2, 5000);
		$this->lastAccessSecondsAgo(3, 100);
		$this->lastAccessSecondsAgo(4, 5000);

		static::assertSame([1, 3], $this->idsOf(['activity' => 'active']));
		static::assertSame([2, 4], $this->idsOf(['activity' => 'inactive']));
		static::assertSame([1, 2, 3, 4], $this->idsOf(['activity' => 'all']));
	}

	/**
	 * The owner is filtered by the number of the user which the autocomplete sends.
	 */
	public function testTheFilterByOwner(): void
	{
		$this->actingAs(1);

		static::assertSame([1, 2], $this->idsOf(['UID' => '2']));
		static::assertSame([3], $this->idsOf(['UID' => '3']));
		static::assertSame([], $this->idsOf(['UID' => '99']));
	}

	/**
	 * The columns status and firmware are not in the list of the player but the preparer knows them.
	 */
	public function testTheStatusIsShownAsIconAndOtherFieldsAsText(): void
	{
		$build = $this->container->get(BuildService::class);
		$build->createDatatableField('status', false);
		$build->createDatatableField('firmware', false);
		$parameters = new Parameters($this->container->get(Sanitizer::class), $this->container->get(Session::class));
		$preparer   = new DatatablePreparer($this->container->get(PrepareService::class), $parameters, $this->container->get(TimeUnitsCalculator::class), $this->container->get(Translator::class), new DateTimeZone('UTC'));
		$players = [
			['player_id' => 1, 'status' => 2, 'firmware' => 'v1'],
			['player_id' => 2, 'status' => 1, 'firmware' => 'v2'],
			['player_id' => 3, 'status' => 4, 'firmware' => 'v3'],
		];

		$body = $preparer->prepareTableBody($players, $build->getDatatableFields(), 1);

		$icons = array_map(static fn(array $row): string => $row['elements_result_element'][0]['is_icon']['ICON_CLASS'], $body);
		static::assertSame(['bi bi-check', 'bi bi-x', 'bi bi-bug'], $icons);
		static::assertSame('with license', $body[0]['elements_result_element'][0]['is_icon']['ICON_TITLE']);
		static::assertSame(['CONTROL_ELEMENT_VALUE_TEXT' => 'v2'], $body[1]['elements_result_element'][1]['is_text']);
	}
}
