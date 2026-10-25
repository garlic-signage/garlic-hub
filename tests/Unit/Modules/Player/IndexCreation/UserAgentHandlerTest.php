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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Framework\Config\Config;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The user agent is what a player says about itself, and the endpoint of the index is public: everybody can
 * send any text. The detector of the models is the real one.
 */
class UserAgentHandlerTest extends TestCase
{
	private UserAgentHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->handler = new UserAgentHandler(new PlayerDetector(new Config(new RecordingConfigLoader([]))));
	}

	/**
	 * @return array<string,array{string,string,string,string,PlayerModel}> the agent, uuid, firmware, name, model
	 */
	public static function knownPlayers(): array
	{
		return [
			'iadea 1x0'            => ['ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)', 'b8375cab-c52f-40ce-b51f-001060b32d06', 'SK8855-ADAPI/2.0.5', 'testplayername', PlayerModel::IADEA_XMP1X0],
			'iadea 3x0, name with spaces' => ['ADAPI/1.0 (UUID:a8294bad-c28f-50af-f940-800869af5854; NAME:Player with spaces in name) SK8855-ADAPI/2.0.5 (MODEL:XMP-330)', 'a8294bad-c28f-50af-f940-800869af5854', 'SK8855-ADAPI/2.0.5', 'Player with spaces in name', PlayerModel::IADEA_XMP3X0],
			'netopsie'             => ['ADAPI/1.0 (UUID:80db43f3-c323-41b5-abcd-542aa2fff06c; NAME:TEST PLAYER) WIN74D-ADAPI/2.0.0 (MODEL:GDATA-1100)', '80db43f3-c323-41b5-abcd-542aa2fff06c', 'WIN74D-ADAPI/2.0.0', 'TEST PLAYER', PlayerModel::IADEA_XMP3X0],
			'old android iadea'    => ['ADAPI/2.0 (UUID:80db43f3-c323-41b5-914a-d0aeece2df95) AML8726M3-ADAPI/20121225.020028 (MODEL:XMP-2200)', '80db43f3-c323-41b5-914a-d0aeece2df95', 'ADAPI/20121225.020028', 'AML8726M3', PlayerModel::IADEA_XMP2X00],
			'iadea 6250'           => ['ADAPI/2.0 (UUID:9e7df0ed-2a5c-4a19-bec7-2cc548004d30) RK3188-ADAPI/1.2.59.161 (MODEL:XMP-6250)', '9e7df0ed-2a5c-4a19-bec7-2cc548004d30', 'ADAPI/1.2.59.161', 'RK3188', PlayerModel::IADEA_XMP2X00],
			'iadea mbr'            => ['ADAPI/2.0 (UUID:0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6) RK3188-ADAPI/1.2.52.152 (MODEL:MBR-1100)', '0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6', 'ADAPI/1.2.52.152', 'RK3188', PlayerModel::IADEA_XMP2X00],
			'iadea 7300'           => ['ADAPI/2.0 (UUID:22a6d755-8ca6-4a82-a724-2cc548000d06) RK3288-ADAPI/1.0.3.74 (MODEL:XMP-7300)', '22a6d755-8ca6-4a82-a724-2cc548000d06', 'ADAPI/1.0.3.74', 'RK3288', PlayerModel::IADEA_XMP2X00],
			'qbic'                 => ['SmartAPI/1.0 (UUID:cc009f47-5a8d-42b4-af5a-1865710c05ba; NAME:05B200T100223; VERSION:v1.0.16; MODEL:TD-1050)', 'cc009f47-5a8d-42b4-af5a-1865710c05ba', 'v1.0.16', '05B200T100223', PlayerModel::QBIC],
			'ids app, 16 digits'   => ['ADAPI/1.0 (UUID:898a48587eb9f96f; NAME:Android-App-898a48587eb9f96f) Android/1.0.180vv (MODEL:IDS-App)', '898a48587eb9f96f', 'Android/1.0.180vv', 'Android-App-898a48587eb9f96f', PlayerModel::IDS],
			'screenlite'           => ['GAPI/1.0 (UUID:15920d5d-7e68-4a61-a145-15b58b6d2090; NAME:Screenlite Web Test) screenlite-web/0.0.1 (MODEL:ScreenliteWeb)', '15920d5d-7e68-4a61-a145-15b58b6d2090', 'screenlite-web/0.0.1', 'Screenlite Web Test', PlayerModel::SCREENLITE],
			'garlic'               => ['GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:9d2cefd78b3f) garlic-macOS/v0.6.0.679 (MODEL:Garlic)', '3f0cd56c-d511-486a-a8e1-9d2cefd78b3f', 'garlic-macOS/v0.6.0.679', '9d2cefd78b3f', PlayerModel::GARLIC],
			'name url encoded'     => ['GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:Eingang%20Nord%C3%A4) garlic/v1 (MODEL:Garlic)', '3f0cd56c-d511-486a-a8e1-9d2cefd78b3f', 'garlic/v1', 'Eingang Nordä', PlayerModel::GARLIC],
			'upper case uuid'      => ['GAPI/1.0 (UUID:F9D65C88-E4CD-43B4-89EB-5C338E856344; NAME:x) garlic/v1 (MODEL:Garlic)', 'F9D65C88-E4CD-43B4-89EB-5C338E856344', 'garlic/v1', 'x', PlayerModel::GARLIC],
		];
	}

	#[Group('units')]
	#[DataProvider('knownPlayers')]
	public function testWhatThePlayerSaysAboutItselfIsRead(string $agent, string $uuid, string $firmware, string $name, PlayerModel $model): void
	{
		$this->handler->parseUserAgent($agent);

		static::assertSame(['uuid' => $uuid, 'firmware' => $firmware, 'name' => $name, 'model' => $model], $this->handler->getInfo());
	}

	#[Group('units')]
	public function testTheParserReturnsTheHandlerSoItCanBeChained(): void
	{
		static::assertSame($this->handler, $this->handler->parseUserAgent('nothing'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function unknownAgents(): array
	{
		return [
			'empty'                  => [''],
			'a browser'              => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'],
			'a tool'                 => ['curl/8.5.0'],
			'no model'               => ['ADAPI/2.0 (UUID:0d8df0bd-3a5c-4a19-bec7-ecf00e3012e6;)'],
			'a very long text'       => [str_repeat('a', 200_000)],
			'a long text in the uuid' => ['ADAPI/1.0 (UUID:' . str_repeat('a', 200_000) . '; NAME:x) fw/1 (MODEL:Garlic)'],
			'control characters'     => ["ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06\x00; NAME:x) fw/1 (MODEL:Garlic)"],
		];
	}

	/**
	 * Everything which is not a player of the known models is nothing: all values are empty.
	 */
	#[Group('units')]
	#[DataProvider('unknownAgents')]
	public function testAnAgentWhichIsNoKnownPlayerIsUnknown(string $agent): void
	{
		$this->handler->parseUserAgent($agent);

		static::assertSame(['uuid' => '', 'firmware' => '', 'name' => '', 'model' => PlayerModel::UNKNOWN], $this->handler->getInfo());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function unknownModels(): array
	{
		return ['unknown model' => ['XYZ-9000'], 'model in other case' => ['garlic'], 'empty model' => ['']];
	}

	/**
	 * A model which is not in the list is the model UNKNOWN. The index service answers such a player as forbidden.
	 */
	#[Group('units')]
	#[DataProvider('unknownModels')]
	public function testAModelWhichIsNotInTheListIsUnknown(string $model): void
	{
		$this->handler->parseUserAgent('ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06; NAME:x) fw/1 (MODEL:' . $model . ')');

		static::assertSame(PlayerModel::UNKNOWN, $this->handler->getModel());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function invalidUuids(): array
	{
		return [
			'path traversal'   => ['../../etc'],
			'slash'            => ['abc/def'],
			'dot'              => ['abc.def'],
			'space'            => ['abc def'],
			'quote'            => ['abc"def'],
			'markup'           => ['<b>abc</b>'],
			'trailing newline' => ["abc\n"],
			'too long'         => [str_repeat('a', 65)],
			'not hex'          => ['a8294bat-c28f-50af-f94o-800869af5854'],
			'uuid too short'   => ['f9d65c88-e4cd-43b4-89eb-5c338e85634'],
			'uuid without dash' => ['f9d65c88e4cd43b489eb5c338e856344'],
			'hex 15 digits'    => ['1628414f155256b'],
			'hex 17 digits'    => ['1628414f155256b4a'],
			'sql'              => ["' OR '1'='1"],
			'empty'            => [''],
		];
	}

	/**
	 * The uuid becomes a directory name of the index file: only the two formats of the players pass.
	 */
	#[Group('units')]
	#[DataProvider('invalidUuids')]
	public function testAnInvalidUuidMakesTheAgentUnknown(string $uuid): void
	{
		$this->handler->parseUserAgent('ADAPI/1.0 (UUID:' . $uuid . '; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)');

		static::assertSame(['uuid' => '', 'firmware' => '', 'name' => '', 'model' => PlayerModel::UNKNOWN], $this->handler->getInfo());
	}

	#[Group('units')]
	public function testTheNextAgentReplacesTheLastOne(): void
	{
		$this->handler->parseUserAgent('GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:first) garlic/v1 (MODEL:Garlic)');
		$this->handler->parseUserAgent('GAPI/1.0 (UUID:15920d5d-7e68-4a61-a145-15b58b6d2090; NAME:second) screenlite/1 (MODEL:ScreenliteWeb)');
		static::assertSame('second', $this->handler->getName());
		static::assertSame(PlayerModel::SCREENLITE, $this->handler->getModel());

		$this->handler->parseUserAgent('curl/8.5.0');
		static::assertSame('', $this->handler->getUuid());
		static::assertSame(PlayerModel::UNKNOWN, $this->handler->getModel());
	}
}
