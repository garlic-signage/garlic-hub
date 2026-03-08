<?php
declare(strict_types=1);

namespace App\Modules\Templates\Services;

use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Templates\Repositories\TemplatesRepository;
use Doctrine\DBAL\Exception;

// use App\Modules\Playlists\Repositories\ChannelRepository;

readonly class TemplatesUsageService
{
    private TemplatesRepository $templatesRepository;
    // private readonly ChannelRepository $channelRepository;

    public function __construct(private readonly ItemsRepository $itemsRepository /*ChannelRepository $channelRepository*/)
	{
        // $this->channelRepository = $channelRepository;
    }

	/**
	 * @param int[] $templateIds
	 * @return array<int,bool>
	 * @throws Exception
	 */
	public function determineTemplatesInUse(array $templateIds): array
    {
        $results = [];
        
        foreach($this->itemsRepository->countFileResourcesByTemplateId($templateIds) as $value)
		{
            $results[$value['template_id']] = $value['count'];
        }

        /* no channels currently
        foreach($this->channelRepository->findTableIdsByPlaylistIds($playlistIds) as $value) {
            $results[$value['table_id']] = true;
        }
        */

        return $results;
    }
}