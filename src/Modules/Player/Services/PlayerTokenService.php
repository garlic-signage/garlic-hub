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

namespace App\Modules\Player\Services;

use App\Framework\Core\Crypt;
use App\Framework\Services\AbstractBaseService;
use App\Modules\Player\Repositories\PlayerTokenRepository;
use Defuse\Crypto\Crypto;
use DateTime;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service class for handling player tokens. This service provides functionalities
 * for storing, retrieving, verifying, and cleaning up player tokens, as well as
 * refreshing existing tokens.
 */
class PlayerTokenService extends AbstractBaseService
{
    private readonly PlayerTokenRepository $playerTokenRepository;
    private readonly Crypt $crypt;

    public function __construct(PlayerTokenRepository $playerTokenRepository, Crypt $crypt, LoggerInterface $logger)
    {
        $this->playerTokenRepository = $playerTokenRepository;
        $this->crypt = $crypt;
        parent::__construct($logger);
    }
    
    public function storeToken(int $playerId, string $accessToken, string $expiresAt, string $tokenType = 'Bearer'): bool
    {
        try
		{
            $encryptedToken = Crypto::encrypt($accessToken, $this->crypt->getEncryptionKey());
            
            $data = [
                'player_id' => $playerId,
                'access_token' => $encryptedToken,
                'token_type' => $tokenType,
                'expires_at' => $expiresAt,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Check if there is already a token
            $existingToken = $this->playerTokenRepository->findByPlayerId($playerId);
            
            if ($existingToken !== [])
                $this->playerTokenRepository->update($existingToken['token_id'], $data);
             else
                $this->playerTokenRepository->insert($data);

            return true;
            
        }
		catch (Throwable $e)
		{
			$this->logger->error($e->getMessage());
            return false;
        }
    }

	/**
	 * Look for a token and delete it if it is expired
	 *
	 * @return array{access_token:string, UID:int, token_type:string, expired_at:string}|array<empty,empty>
	 */
    public function getToken(int $playerId): array
    {
        try
		{
            $tokenData = $this->playerTokenRepository->findByPlayerId($playerId);
            
            if ($tokenData === [])
                return [];

            // is expired? except some IAdea player does not deliver an expiry date.
			if ($tokenData['expires_at'] !== '')
			{
				$expiresAt = new DateTime($tokenData['expires_at']);
				if ($expiresAt <= new DateTime())
				{
					$this->playerTokenRepository->delete($playerId);
					return [];
				}
			}

            $decryptedToken = Crypto::decrypt($tokenData['access_token'], $this->crypt->getEncryptionKey());
            
            return [
                'access_token' => $decryptedToken,
                'token_type' => $tokenData['token_type'],
                'expires_at' => $tokenData['expires_at']
			];
            
        }
		catch (Throwable $e)
		{
            return [];
        }
    }
    
    public function hasValidToken(int $playerId): bool
    {
        return $this->getToken($playerId) !== [];
    }

	/**
	 * @throws Exception
	 */
	public function cleanupExpiredTokens(): int
    {
        return $this->playerTokenRepository->deleteBy(['expires_at <=' => date('Y-m-d H:i:s')]);
    }

	/**
	 * @param array{access_token:string, expires_at:string, token_type:string} $newTokenData
	 */
    public function refreshToken(int $playerId, array $newTokenData): bool
    {
        return $this->storeToken(
            $playerId,
            $newTokenData['access_token'],
            $newTokenData['expires_at'],
            $newTokenData['token_type']
        );
    }
}
