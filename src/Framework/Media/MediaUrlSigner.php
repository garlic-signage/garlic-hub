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

/**
 * Builds and verifies the signed URLs that public/media.php delivers mediapool
 * files with. Everything the delivery script evaluates is part of the signature,
 * so a thumb URL cannot be edited into an original one.
 *
 * Url form: /media.php/<variant>/<name>.<ext>?exp=..&sig=.. - the file name has to be part of
 * the path, a query like ?ext=mp4 would not be recognised as media type by clients.
 *
 * The expiry is rounded up to the next but one TTL slot, so the URL of a file
 * stays identical for at least one TTL and remains cacheable in the browser.
 */
class MediaUrlSigner
{
    public const string VARIANT_THUMB    = 'thumb';
    public const string VARIANT_ORIGINAL = 'orig';
    public const string VARIANT_TEMPLATE_THUMB = 'tpl-thumb';  // templates/thumbs, named by template id
    public const string VARIANT_ITEM_THUMB     = 'item-thumb'; // playlists/items/thumbs, named by item id and content

    private const string ENDPOINT = '/media.php';

    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 3600)
    {
        $this->secret = $secret;
        $this->ttl    = $ttl > 0 ? $ttl : 3600;
    }

    public function thumbUrl(string $checksum, string $extension, ?int $now = null): string
    {
        return $this->buildUrl($checksum, $extension, self::VARIANT_THUMB, $now);
    }

    public function originalUrl(string $checksum, string $extension, ?int $now = null): string
    {
        return $this->buildUrl($checksum, $extension, self::VARIANT_ORIGINAL, $now);
    }

    public function templateThumbUrl(int $templateId, string $extension, ?int $now = null): string
    {
        return $this->buildUrl((string) $templateId, $extension, self::VARIANT_TEMPLATE_THUMB, $now);
    }

    /**
     * @param string $name <item_id>-<hmac>, before the first export after an update the item id, see TemplateItemName
     */
    public function itemThumbUrl(string $name, string $extension, ?int $now = null): string
    {
        return $this->buildUrl($name, $extension, self::VARIANT_ITEM_THUMB, $now);
    }

    public function buildUrl(string $checksum, string $extension, string $variant, ?int $now = null): string
    {
        $signature = $this->sign($checksum, $extension, $variant, $now);

        // variant, name and extension are the path: players, browsers and lightboxes take the
        // media type from the file extension there. Only expiry and signature are query.
        return self::ENDPOINT . '/' . $variant . '/' . $checksum . '.' . $extension . '?' . http_build_query([
            'exp' => $signature['exp'],
            'sig' => $signature['sig'],
        ]);
    }

    /**
     * @return array{exp: int, sig: string}
     */
    public function sign(string $checksum, string $extension, string $variant, ?int $now = null): array
    {
        $exp = (intdiv($now ?? time(), $this->ttl) + 2) * $this->ttl;

        return array(
            'exp' => $exp,
            'sig' => self::hash($checksum, $extension, $variant, $exp, $this->secret),
        );
    }

    /**
     * @param array<string, mixed> $get
     */
    public static function verify(array $get, string $secret, ?int $now = null): bool
    {
        if (!isset($get['c'], $get['ext'], $get['v'], $get['exp'], $get['sig'])
            || !is_string($get['c']) || !is_string($get['ext']) || !is_string($get['v'])
            || !is_string($get['exp']) || !is_string($get['sig']))
            return false;

        if ((int) $get['exp'] < ($now ?? time()))
            return false;

        $expected = self::hash($get['c'], $get['ext'], $get['v'], (int) $get['exp'], $secret);

        return hash_equals($expected, $get['sig']);
    }

    private static function hash(string $checksum, string $extension, string $variant, int $exp, string $secret): string
    {
        return hash_hmac('sha256', implode('|', [$checksum, $extension, $variant, $exp]), $secret);
    }
}
