<?php
declare(strict_types=1);

namespace App\Modules\Player\Helper\PlayerPlaylist;

use App\Framework\Controller\BaseResponseBuilder;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Handles building JSON error responses for various scenarios related to players and playlists.
 * Extends the functionality of the BaseResponseBuilder class by providing pre-defined error handlers.
 *
 * Each method translates specific error messages and returns them in a JSON response format.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function invalidPlayerId(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('invalid_player_id', 'player')
		);
    }

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function invalidPlaylistId(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('invalid_playlist_id', 'playlist')
		);
    }

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function playerNotFound(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('player_not_found', 'player')
		);
    }

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function playerNotReachable(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('player_not_reachable', 'player')
		);
    }

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function noPlaylistAssigned(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('no_playlist_assigned', 'player')
		);
    }
}
