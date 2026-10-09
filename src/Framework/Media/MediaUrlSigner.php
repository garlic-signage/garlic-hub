<?php
/*
 GarlicSignage: Open Source Digital Signage Stack

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the GarlicSignage source code

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


namespace App\Framework\Media;

class MediaUrlSigner
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 3600)
    {
        $this->secret = $secret;
        $this->ttl    = $ttl;
    }

    public function signForAddUrl(string $checksum, ?int $now = null): string
    {
        $param = $this->sign($checksum, $now);

        return '&exp=' . $param['exp'] . '&sig=' . $param['sig'];
    }

    /**
     * @return array{exp: int, sig: string}
     */
    public function sign(string $checksum, ?int $now = null): array
    {
        $exp = (intdiv($now ?? time(), $this->ttl) + 2) * $this->ttl;

        return array(
            'exp' => $exp,
            'sig' => hash_hmac('sha256', $checksum . '|' . $exp, $this->secret),
        );
    }

    /**
     * @param array<string, mixed> $get
     */
    public static function verify(array $get, string $secret, ?int $now = null): bool
    {
        if (!isset($get['c'], $get['exp'], $get['sig'])
            || !is_string($get['c']) || !is_string($get['exp']) || !is_string($get['sig']))
            return false;

        if ((int) $get['exp'] < ($now ?? time()))
            return false;

        $expected = hash_hmac('sha256', $get['c'] . '|' . (int) $get['exp'], $secret);

        return hash_equals($expected, $get['sig']);
    }
}
