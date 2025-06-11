<?php

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserAgentHandlerTest extends TestCase
{
	private PlayerDetector&MockObject $playerDetectorMock;
	private UserAgentHandler $handler;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->playerDetectorMock = $this->createMock(PlayerDetector::class);
		$this->handler = new UserAgentHandler($this->playerDetectorMock);
	}

	#[Group('units')]
	public function testParseUserAgent(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP1X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid'     => 'b8375cab-c52f-40ce-b51f-001060b32d06',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name'     => 'testplayername',
			'model'    => PlayerModel::IADEA_XMP1X0
		];

		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithSpaces(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:a8294bat-c28f-50af-f94o-800869af5854; NAME:Player with spaces in name) SK8855-ADAPI/2.0.5 (MODEL:XMP-330)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);
		$expected = [
			'uuid' => 'a8294bat-c28f-50af-f94o-800869af5854',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name' => 'Player with spaces in name',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithDeprecatedIadeaAndroidPlayer(): void
	{
		$userAgent = 'Mozilla/5.0 (Linux; U; Android 4.0.4; en-us; Build/ICS.MBX.20121225) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Safari/534.30 
	ADAPI/2.0 (UUID:80db43f3-c323-41b5-914a-d0aeece2df95) AML8726M3-ADAPI/20121225.020028 (MODEL:XMP-2200)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '80db43f3-c323-41b5-914a-d0aeece2df95',
			'firmware' => 'ADAPI/20121225.020028',
			'name' => 'AML8726M3',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithNetopsiePlayer(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:80db43f3-c323-41b5-abcd-542aa2fff06c; NAME:TEST PLAYER) WIN74D-ADAPI/2.0.0 (MODEL:GDATA-1100)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '80db43f3-c323-41b5-abcd-542aa2fff06c',
			'firmware' => 'WIN74D-ADAPI/2.0.0',
			'name' => 'TEST PLAYER',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWith6xxxXIadeaAndroidPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:9e7df0ed-2a5c-4a19-bec7-2cc548004d30) RK3188-ADAPI/1.2.59.161 (MODEL:XMP-6250)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '9e7df0ed-2a5c-4a19-bec7-2cc548004d30',
			'firmware' => 'ADAPI/1.2.59.161',
			'name' => 'RK3188',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithXIadeaMbrPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6) RK3188-ADAPI/1.2.52.152 (MODEL:MBR-1100)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6',
			'firmware' => 'ADAPI/1.2.52.152',
			'name' => 'RK3188',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithIadea7300Player(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:22a6d755-8ca6-4a82-a724-2cc548000d06) RK3288-ADAPI/1.0.3.74 (MODEL:XMP-7300)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '22a6d755-8ca6-4a82-a724-2cc548000d06',
			'firmware' => 'ADAPI/1.0.3.74',
			'name' => 'RK3288',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithUnknownPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:0d8df0bd-3a5c-4a19-bec7-ecf00e3012e6;)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::UNKNOWN);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '0d8df0bd-3a5c-4a19-bec7-ecf00e3012e6',
			'firmware' => '',
			'name' => '',
			'model' => PlayerModel::UNKNOWN
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithQbicPlayer(): void
	{
		$userAgent = 'SmartAPI/1.0 (UUID:cc009f47-5a8d-42b4-af5a-1865710c05ba; NAME:05B200T100223; VERSION:v1.0.16; MODEL:TD-1050)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::QBIC);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => 'cc009f47-5a8d-42b4-af5a-1865710c05ba',
			'firmware' => 'v1.0.16',
			'name' => '05B200T100223',
			'model' => PlayerModel::QBIC
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseUserAgentWithIdsApp(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:898a48587eb9f96f; NAME:Android-App-898a48587eb9f96f) Android/1.0.180vv (MODEL:IDS-App)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IDS);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '898a48587eb9f96f',
			'firmware' => 'Android/1.0.180vv',
			'name' => 'Android-App-898a48587eb9f96f',
			'model' => PlayerModel::IDS
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}



	#[Group('units')]
	public function testParseScreenlitePlayer(): void
	{
		$userAgent = 'GAPI/1.0 (UUID:15920d5d-7e68-4a61-a145-15b58b6d2090; NAME:Screenlite Web Test) screenlite-web/0.0.1 (MODEL:ScreenliteWeb)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::SCREENLITE);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '15920d5d-7e68-4a61-a145-15b58b6d2090',
			'firmware' => 'screenlite-web/0.0.1',
			'name' => 'Screenlite Web Test',
			'model' => PlayerModel::SCREENLITE
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseFallbackOldIadeas(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:b8375cab-c52f-40ce-b51f-001060b32d06; NAME:testplayername) SK8855-ADAPI/2.0.5 (MODEL:XDS-101)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP1X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => 'b8375cab-c52f-40ce-b51f-001060b32d06',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name' => 'testplayername',
			'model' => PlayerModel::IADEA_XMP1X0
		];
		$this->assertEquals($expected, $this->handler->getInfo());

	}

	#[Group('units')]
	public function testParseFallbackOldIadeasWithSpaces(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:a8294bat-c28f-50af-f94o-800869af5854; NAME:Player with spaces in name) SK8855-ADAPI/2.0.5 (MODEL:XMP-330)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => 'a8294bat-c28f-50af-f94o-800869af5854',
			'firmware' => 'SK8855-ADAPI/2.0.5',
			'name' => 'Player with spaces in name',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseFallbackNetopsie(): void
	{
		$userAgent = 'ADAPI/1.0 (UUID:80db43f3-c323-41b5-abcd-542aa2fff06c; NAME:TEST PLAYER) WIN74D-ADAPI/2.0.0 (MODEL:GDATA-1100)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP3X0);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '80db43f3-c323-41b5-abcd-542aa2fff06c',
			'firmware' => 'WIN74D-ADAPI/2.0.0',
			'name' => 'TEST PLAYER',
			'model' => PlayerModel::IADEA_XMP3X0
		];
		$this->assertEquals($expected, $this->handler->getInfo());
	}

	#[Group('units')]
	public function testParseFallbackAndroidIAdeaPlayer(): void
	{
		$userAgent = 'ADAPI/2.0 (UUID:0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6) RK3188-ADAPI/1.2.52.152 (MODEL:MBR-1100)';
		$this->playerDetectorMock->method('detectModelId')->willReturnSelf();
		$this->playerDetectorMock->method('getModelId')->willReturn(PlayerModel::IADEA_XMP2X00);
		$this->handler->parseUserAgent($userAgent);

		$expected = [
			'uuid' => '0e8df0ed-2a5c-4a19-bec7-ecf00e3012e6',
			'firmware' => 'ADAPI/1.2.52.152',
			'name' => 'RK3188',
			'model' => PlayerModel::IADEA_XMP2X00
		];
		$this->assertEquals($expected, $this->handler->getInfo());

	}

}
