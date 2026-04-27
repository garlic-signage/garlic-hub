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

namespace App\Modules\Playlists\Repositories;

use App\Framework\Core\Config\Config;
use App\Framework\Database\BaseRepositories\SqlBase;
use App\Framework\Database\BaseRepositories\Traits\CrudTraits;
use App\Framework\Database\BaseRepositories\Traits\FindOperationsTrait;
use App\Framework\Database\BaseRepositories\Traits\TransactionsTrait;
use App\Modules\Playlists\Helper\ItemType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class ItemsRepository extends SqlBase
{
	use CrudTraits;
	use FindOperationsTrait;

	/*	later CONST int FLAG_DISABLED = 1;
		CONST  int FLAG_LOCKED   = 2;
		CONST  int FLAG_LOGGABLE  = 4;
	*/
	use TransactionsTrait;
	use FindOperationsTrait;

	public function __construct(Connection $connection)
	{
		parent::__construct($connection, 'playlists_items', 'item_id');
	}

	/**
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllByPlaylistId(int $playlistId): array
	{
		$where   = ['playlist_id' => $this->generateWhereClause($playlistId)];
		$orderBy = [['sort' => 'item_order', 'order' => 'ASC']];

		return $this->findAllBy($where,[], [], '', $orderBy);
	}

	/**
	 * @return list<array{item_id:int, item_name:string}>
	 * @throws Exception
	 */
	public function findMediaInPlaylistId(int $playlistId): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->select('item_id, item_name')
			->from($this->table)
			->where('playlists_items.playlist_id = :playlistId')
			->andWhere('item_type in (\''.ItemType::MEDIAPOOL->value.'\', \''.ItemType::MEDIA_EXTERN->value.'\', \''.ItemType::TEMPLATE->value.'\')')
			->andWhere("mimetype LIKE 'image%' OR mimetype LIKE 'video%'")
			->setParameter('playlistId', $playlistId);

		// @phpstan-ignore-next-line // we clearly define our result in select
		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}


	/**
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findAllByPlaylistIdWithJoins(int $playlistId, string $edition): array
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->from($this->table);
		$queryBuilder->where('playlists_items.playlist_id = :playlistId');
		$queryBuilder->setParameter('playlistId', $playlistId);
		$queryBuilder->orderBy('item_order', 'ASC');
		$queryBuilder->leftJoin(
			'playlists_items',
			'mediapool_files',
			'',
			'playlists_items.file_resource = mediapool_files.checksum'
		);

		$queryBuilder->leftJoin(
			'playlists_items',
			'playlists',
			'nested_playlist',
			'playlists_items.file_resource = nested_playlist.playlist_id'
		);

		$select = 'item_id, flags, playlists_items.playlist_id, playlists_items.UID, item_type, item_order, file_resource, datasource, item_duration, item_filesize, playlists_items.mimetype, item_name, properties, conditional, categories, content_data, begin_trigger, end_trigger, mediapool_files.extension, nested_playlist.time_limit, nested_playlist.owner_duration';

		if ($edition === Config::PLATFORM_EDITION_CORE || $edition === Config::PLATFORM_EDITION_ENTERPRISE)
		{
			$select .= ', templates_content.filetype, templates_content.media_type';
			$queryBuilder->leftJoin(
				'playlists_items',
				'templates_content',
				'',
				'item_type='.ItemType::TEMPLATE->value.' AND  playlists_items.file_resource = templates_content.content_id'
			);
		}

		if ($edition === Config::PLATFORM_EDITION_ENTERPRISE)
		{
			$select .= ', channels.channel_type, channels.view_mode, channels.vendor';
			$queryBuilder->leftJoin(
				'playlists_items',
				'channels',
				'',
				'item_type='.ItemType::CHANNEL->value.' AND  playlists_items.file_resource = channels.channel_id'
			);
		}

		$queryBuilder->select($select);
		// important! otherwise left join will grab an additional mediapool_files.checksum if exists
		// remember: mediapool recognises double files.
		$queryBuilder->groupBy('playlists_items.item_id');

		return $queryBuilder->executeQuery()->fetchAllAssociative();
	}

	/**
	 * This method finds all nested playlists, in a playlist
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllPlaylistItemsByPlaylistId(int $playlistId): array
	{
		$where = [
			'playlist_id' => $this->generateWhereClause($playlistId),
			'item_type'  => $this->generateWhereClause(ItemType::PLAYLIST->value)
		];
		return $this->findAllBy($where);
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function sumAndCountMetricsByPlaylistIdAndOwner(int $playlistId, int $owner): array
	{
		$qb = $this->connection->createQueryBuilder();

		$qb->select(
			'COUNT(*) AS count_items',
			'COUNT(CASE WHEN UID = '.$owner.' THEN 1 ELSE NULL END) AS count_owner_items',
			'SUM(item_filesize) AS filesize',
			'SUM(item_duration) AS duration',
			'SUM(CASE WHEN UID = '.$owner.' THEN item_duration ELSE 0 END) AS owner_duration'
		)
		   ->from($this->table)
		   ->where('playlist_id = :playlist_id')
			/*	->andWhere('flags & '.self::FLAG_DISABLED.' <> 0')*/
		   ->setParameter('playlist_id', $playlistId);

		$result = $qb->executeQuery()->fetchAssociative();
		if ($result === false)
			return [];

		return $result;
	}

	/**
	 *  This method finds all playlists that have nested the playlistId
	 *
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllPlaylistsContainingPlaylist(int $playlistId): array
	{
		$playlistsTable = 'playlists';

		$fields = [$this->table.'.item_id, '.$playlistsTable.'.*'];
		$join   = [$playlistsTable => $playlistsTable.'.playlist_id = '.$this->table.'.playlist_id'];
		$where  = [
			$this->table.'.file_resource' => $this->generateWhereClause($playlistId),
			$this->table.'.item_type' => $this->generateWhereClause(ItemType::PLAYLIST->value)
		];

		return $this->findAllByWithFields($fields, $where, $join, [], $this->table.'.playlist_id');
	}


	/**
	 * @throws Exception
	 */
	public function updatePositionsWhenInserted(int $playlistId, int $position): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->getTable());

		$queryBuilder->set('item_order', 'item_order + 1');

		$queryBuilder->where('playlist_id = :playlist_id');
		$queryBuilder->setParameter('playlist_id', $playlistId);
		$queryBuilder->andWhere('item_order >= :item_order');
		$queryBuilder->setParameter('item_order', $position);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function updatePositionsWhenDeleted(int $playlistId, int $position): int
	{
		$queryBuilder = $this->connection->createQueryBuilder();
		$queryBuilder->update($this->getTable());

		$queryBuilder->set('item_order', 'item_order - 1');

		$queryBuilder->where('playlist_id = :playlist_id');
		$queryBuilder->setParameter('playlist_id', $playlistId);
		$queryBuilder->andWhere('item_order >= :item_order');
		$queryBuilder->setParameter('item_order', $position);

		return (int) $queryBuilder->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function updateItemOrder(int $itemId, int $newOrder): int
	{
		return $this->update($itemId,['item_order' => $newOrder]);
	}

	/**
	 * @param int[] $playlistIds
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	public function findFileResourcesByPlaylistId(array $playlistIds): array
	{
		if (empty($playlistIds))
			return [];

		$ids   = implode(',', $playlistIds);
		$sql   = 'SELECT file_resource as playlist_id FROM '.$this->getTable().' WHERE item_type = \''.ItemType::PLAYLIST->value. '\' AND CAST(file_resource AS UNSIGNED) IN('.$ids.')';

		return $this->connection->executeQuery($sql)->fetchAllAssociative();
	}

	public function countFileResourcesByTemplateId(array $templateIds): array
	{
		if (empty($templateIds))
			return [];

		$ids   = implode(',', $templateIds);
		$sql = 'SELECT file_resource as template_id, COUNT(*) as count FROM ' . $this->getTable() . ' WHERE item_type = \'' . ItemType::TEMPLATE->value . '\' AND CAST(file_resource AS UNSIGNED) IN(' . $ids . ') GROUP BY file_resource';

		return $this->connection->executeQuery($sql)->fetchAllAssociative();
	}

}