<?php

namespace Tests\Unit\Modules\Playlists\Services;

use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AclValidatorTest extends TestCase
{
	private AclValidator $aclValidator;
	private AclHelper&MockObject $aclHelperMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->aclHelperMock = $this->createMock(AclHelper::class);

		$this->aclValidator    = new AclValidator($this->aclHelperMock);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws PhpfastcacheSimpleCacheException|ModuleException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableOwner(): void
	{
		$UID = 1;
		$playlist = ['UID' => 1, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->expects($this->never())->method('isModuleAdmin');
		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableModuleAdmin(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')
			->with($UID, 'playlists')
			->willReturn(true);
		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableEdgeEdition(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$configMock = $this->createMock(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configMock);
		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertFalse($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableMissingCompanyOrUID(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2];

		$configMock = $this->createMock(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configMock);
		$configMock->expects($this->once())->method('getEdition')->willReturn(Config::PLATFORM_EDITION_ENTERPRISE);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Missing company id or UID in unit data.');

		$this->aclValidator->isPlaylistEditable($UID, $playlist);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableSubAdminAccessFailed(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $playlist['company_id'], 'playlists')
			->willReturn(false);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertFalse($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableSubAdminAccessSucceed(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $playlist['company_id'], 'playlists')
			->willReturn(true);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception|ModuleException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableEditorAccess(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $playlist['playlist_id'], 'playlists')
			->willReturn(true);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableNoAccess(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $playlist['playlist_id'], 'playlists')
			->willReturn(false);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		$this->assertFalse($result);
	}
}
