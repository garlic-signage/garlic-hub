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

use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UserAgentHandlerTest extends TestCase
{
	private PlayerDetector&Stub $playerDetectorStub;
	private UserAgentHandler $handler;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerDetectorStub = $this->createStub(PlayerDetector::class);
		$this->handler = new UserAgentHandler($this->playerDetectorStub);
	}

	#[Group('units')]
	public function testParseUserAgent(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP1X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid'     => 'b8375cab-c52f-40ce-b51f-001060b32d06',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name'     => 'testplayername',
			'model'    => PlayerModel::IADEA_XMP1X0
		];

		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithSpaces(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:a8294bad-c28f-50af-f940-800869af5854; NAME:Player with spaces in name) SK8855-ADAPI/2.0.5 (MODEL:XMP-330)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);
		$expected = [
			'uuid' => 'a8294bad-c28f-50af-f940-800869af5854',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name' => 'Player with spaces in name',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithDeprecatedIadeaAndroidPlayer(): void
	{
		$userAgent = 'Mozilla/5.0 (Linux; U; Android 4.0.4; en-us; Build/ICS.MBX.20121225) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Safari/534.30 
	ADAPI/2.0 (UUID:80db43f3-c323-41b5-914a-d0aeece2df95) AML8726M3-ADAPI/20121225.020028 (MODEL:XMP-2200)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '80db43f3-c323-41b5-914a-d0aeece2df95',
			'firmware' => 'ADAPI/20121225.020028',
			'name' => 'AML8726M3',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithNetopsiePlayer(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:80db43f3-c323-41b5-abcd-542aa2fff06c; NAME:TEST PLAYER) WIN74D-ADAPI/2.0.0 (MODEL:GDATA-1100)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '80db43f3-c323-41b5-abcd-542aa2fff06c',
			'firmware' => 'WIN74D-ADAPI/2.0.0',
			'name' => 'TEST PLAYER',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWith6xxxXIadeaAndroidPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:9e7df0ed-2a5c-4a19-bec7-2cc548004d30) RK3188-ADAPI/1.2.59.161 (MODEL:XMP-6250)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '9e7df0ed-2a5c-4a19-bec7-2cc548004d30',
			'firmware' => 'ADAPI/1.2.59.161',
			'name' => 'RK3188',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithXIadeaMbrPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6) RK3188-ADAPI/1.2.52.152 (MODEL:MBR-1100)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6',
			'firmware' => 'ADAPI/1.2.52.152',
			'name' => 'RK3188',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithIadea7300Player(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:22a6d755-8ca6-4a82-a724-2cc548000d06) RK3288-ADAPI/1.0.3.74 (MODEL:XMP-7300)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '22a6d755-8ca6-4a82-a724-2cc548000d06',
			'firmware' => 'ADAPI/1.0.3.74',
			'name' => 'RK3288',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithUnknownPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:0d8df0bd-3a5c-4a19-bec7-ecf00e3012e6;)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::UNKNOWN);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '',
			'firmware' => '',
			'name' => '',
			'model' => PlayerModel::UNKNOWN
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithQbicPlayer(): void
	{
		$userAgent = 'SmartAPI/1.0 (UUID:cc009f47-5a8d-42b4-af5a-1865710c05ba; NAME:05B200T100223; VERSION:v1.0.16; MODEL:TD-1050)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::QBIC);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => 'cc009f47-5a8d-42b4-af5a-1865710c05ba',
			'firmware' => 'v1.0.16',
			'name' => '05B200T100223',
			'model' => PlayerModel::QBIC
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithIdsApp(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:898a48587eb9f96f; NAME:Android-App-898a48587eb9f96f) Android/1.0.180vv (MODEL:IDS-App)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IDS);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '898a48587eb9f96f',
			'firmware' => 'Android/1.0.180vv',
			'name' => 'Android-App-898a48587eb9f96f',
			'model' => PlayerModel::IDS
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}



	#[Group('units')]
	public function testParseScreenlitePlayer(): void
	{
		$userAgent = 'GAPI/1.0 (UUID:15920d5d-7e68-4a61-a145-15b58b6d2090; NAME:Screenlite Web Test) screenlite-web/0.0.1 (MODEL:ScreenliteWeb)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::SCREENLITE);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '15920d5d-7e68-4a61-a145-15b58b6d2090',
			'firmware' => 'screenlite-web/0.0.1',
			'name' => 'Screenlite Web Test',
			'model' => PlayerModel::SCREENLITE
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseFallbackOldIadeas(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP1X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => 'b8375cab-c52f-40ce-b51f-001060b32d06',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name' => 'testplayername',
			'model' => PlayerModel::IADEA_XMP1X0
		];
		static::assertEquals($expected, $this->handler->getInfo());

	}

	#[Group('units')]
	public function testParseFallbackOldIadeasWithSpaces(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:a8294bad-c28f-50af-f940-800869af5854; NAME:Player with spaces in name) SK8855-ADAPI/2.0.5 (MODEL:XMP-330)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => 'a8294bad-c28f-50af-f940-800869af5854',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name' => 'Player with spaces in name',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseFallbackNetopsie(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:80db43f3-c323-41b5-abcd-542aa2fff06c; NAME:TEST PLAYER) WIN74D-ADAPI/2.0.0 (MODEL:GDATA-1100)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '80db43f3-c323-41b5-abcd-542aa2fff06c',
			'firmware' => 'WIN74D-ADAPI/2.0.0',
			'name' => 'TEST PLAYER',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		static::assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseFallbackAndroidIAdeaPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6) RK3188-ADAPI/1.2.52.152 (MODEL:MBR-1100)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6',
			'firmware' => 'ADAPI/1.2.52.152',
			'name' => 'RK3188',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		static::assertEquals($expected, $this->handler->getInfo());

	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function invalidUuidProvider(): array
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
			'uuid without dash'=> ['f9d65c88e4cd43b489eb5c338e856344'],
			'hex 15 digits'    => ['1628414f155256b'],
			'hex 17 digits'    => ['1628414f155256b4a'],
		];
	}

	#[Group('units')]
	#[DataProvider('invalidUuidProvider')]
	public function testParseUserAgentRejectsInvalidUuid(string $uuid): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:' . $uuid . '; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP1X0);
		$this->handler->parseUserAgent($userAgent);

		static::assertEquals([
			'uuid'     => '',
			'firmware' => '',
			'name'     => '',
			'model'    => PlayerModel::UNKNOWN
		], $this->handler->getInfo());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function validUuidProvider(): array
	{
		return [
			'uuid v4'     => ['f9d65c88-e4cd-43b4-89eb-5c338e856344'],
			'uuid upper'  => ['F9D65C88-E4CD-43B4-89EB-5C338E856344'],
			'16 digit hex' => ['1628414f155256b4'],
		];
	}

	#[Group('units')]
	#[DataProvider('validUuidProvider')]
	public function testParseUserAgentAcceptsKnownUuidFormats(string $uuid): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:' . $uuid . '; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)';
		$this->playerDetectorStub->method('detectModelId')->willReturnSelf();
		$this->playerDetectorStub->method('getModelId')->willReturn(PlayerModel::IADEA_XMP1X0);
		$this->handler->parseUserAgent($userAgent);

		static::assertSame($uuid, $this->handler->getUuid());
	}
}
