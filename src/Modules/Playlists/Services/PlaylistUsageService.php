<?php
declare(strict_types=1);

namespace App\Modules\Playlists\Services;

use App\Modules\Playlists\Contracts\PlaylistUsageFinderInterface;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;

// use App\Modules\Playlists\Repositories\ChannelRepository;

readonly class PlaylistUsageService
{
    private PlaylistUsageFinderInterface $playlistUsageFinder;
    private ItemsRepository $itemsRepository;
    // private readonly ChannelRepository $channelRepository;

    public function __construct(PlaylistUsageFinderInterface $playlistUsageFinder, ItemsRepository $itemsRepository /*ChannelRepository $channelRepository*/)
	{
        $this->playlistUsageFinder = $playlistUsageFinder;
        $this->itemsRepository  = $itemsRepository;
        // $this->channelRepository = $channelRepository;
    }

	/**
	 * @param int[] $playlistIds
	 * @return array<int,bool>
	 * @throws Exception
	 */
	public function determinePlaylistsInUse(array $playlistIds): array
    {
        $results = [];
        
        foreach($this->playlistUsageFinder->findUsedPlaylistIds($playlistIds) as $playlistId)
		{
            $results[$playlistId] = true;
        }
        
        foreach($this->itemsRepository->findFileResourcesByPlaylistId($playlistIds) as $value)
		{
            $results[$value['playlist_id']] = true;
        }

        /* no channels currently
        foreach($this->channelRepository->findTableIdsByPlaylistIds($playlistIds) as $value) {
            $results[$value['table_id']] = true;
        }
        */

        return $results;
    }
}