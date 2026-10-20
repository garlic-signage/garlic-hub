<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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


namespace Tests\Unit\Framework\Media;

use App\Framework\Media\MediaUrlSigner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MediaUrlSignerTest extends TestCase
{
	private const string SECRET   = 'unit-test-secret';
	private const string CHECKSUM = 'd41d8cd98f00b204e9800998ecf8427e';
	private const int NOW         = 1_760_000_000;

	private MediaUrlSigner $mediaUrlSigner;

	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaUrlSigner = new MediaUrlSigner(self::SECRET, 3600);
	}

	/**
	 * @return array<array-key, mixed> the parameters of an url as media.php hands them to verify():
	 *                                 variant, name and extension from the path, expiry and signature from the query
	 */
	private function queryOf(string $url): array
	{
		$query = [];
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
		if (preg_match('#^/media\.php/([a-z-]+)/([a-z0-9]+)\.([a-z0-9]+)$#', (string) parse_url($url, PHP_URL_PATH), $m) !== 1)
			static::fail('Unexpected media url: ' . $url);

		return ['c' => $m[2], 'ext' => $m[3], 'v' => $m[1]] + $query;
	}

	#[Group('units')]
	public function testThumbUrlContainsAllDeliveryParameters(): void
	{
		$url = $this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW);

		static::assertStringStartsWith('/media.php/thumb/' . self::CHECKSUM . '.jpg?exp=', $url);
		static::assertSame(
			['c' => self::CHECKSUM, 'ext' => 'jpg', 'v' => 'thumb', 'exp' => '1760004000', 'sig' => $this->queryOf($url)['sig']],
			$this->queryOf($url)
		);
	}

	#[Group('units')]
	public function testTemplateAndItemThumbUrlsUseTheirOwnVariantAndVerify(): void
	{
		$template = $this->queryOf($this->mediaUrlSigner->templateThumbUrl(12, 'jpg', self::NOW));
		$item     = $this->queryOf($this->mediaUrlSigner->itemThumbUrl(34, 'jpg', self::NOW));

		static::assertSame(['12', 'tpl-thumb'], [$template['c'], $template['v']]);
		static::assertSame(['34', 'item-thumb'], [$item['c'], $item['v']]);
		static::assertTrue(MediaUrlSigner::verify($template, self::SECRET, self::NOW));
		// the variant is part of the signature, a template url cannot be turned into an item url
		static::assertFalse(MediaUrlSigner::verify(['v' => 'item-thumb'] + $template, self::SECRET, self::NOW));
	}

	#[Group('units')]
	public function testOriginalUrlUsesTheOriginalVariant(): void
	{
		$url = $this->mediaUrlSigner->originalUrl(self::CHECKSUM, 'mp4', self::NOW);

		static::assertSame('orig', $this->queryOf($url)['v']);
		static::assertSame('mp4', $this->queryOf($url)['ext']);
	}

	#[Group('units')]
	public function testExpiryIsRoundedToTheNextButOneSlotSoUrlsStayStable(): void
	{
		$first  = $this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW);
		$second = $this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW + 300);

		// inside the same slot the url does not change, so the browser can cache the file
		static::assertSame($first, $second);
		// and whenever it is built it stays valid for at least one whole ttl
		static::assertGreaterThan(self::NOW + 3600, (int) $this->queryOf($first)['exp']);
	}

	#[Group('units')]
	public function testVerifyAcceptsAFreshlySignedUrl(): void
	{
		$query = $this->queryOf($this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW));

		static::assertTrue(MediaUrlSigner::verify($query, self::SECRET, self::NOW));
	}

	#[Group('units')]
	public function testVerifyRejectsAnExpiredUrl(): void
	{
		$query = $this->queryOf($this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW));

		static::assertFalse(MediaUrlSigner::verify($query, self::SECRET, (int) $query['exp'] + 1));
	}

	#[Group('units')]
	public function testVerifyRejectsAnotherSecret(): void
	{
		$query = $this->queryOf($this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW));

		static::assertFalse(MediaUrlSigner::verify($query, 'another-secret', self::NOW));
	}

	#[Group('units')]
	public function testVerifyRejectsAVariantRaisedToTheOriginal(): void
	{
		$query = $this->queryOf($this->mediaUrlSigner->thumbUrl(self::CHECKSUM, 'jpg', self::NOW));
		$query['v'] = MediaUrlSigner::VARIANT_ORIGINAL;

		static::assertFalse(MediaUrlSigner::verify($query, self::SECRET, self::NOW));
	}

	#[Group('units')]
	public function testVerifyRejectsAnExchangedExtension(): void
	{
		$query = $this->queryOf($this->mediaUrlSigner->originalUrl(self::CHECKSUM, 'jpg', self::NOW));
		$query['ext'] = 'mp4';

		static::assertFalse(MediaUrlSigner::verify($query, self::SECRET, self::NOW));
	}

	#[Group('units')]
	public function testVerifyRejectsAnExchangedChecksum(): void
	{
		$query = $this->queryOf($this->mediaUrlSigner->originalUrl(self::CHECKSUM, 'jpg', self::NOW));
		$query['c'] = 'e99a18c428cb38d5f260853678922e03';

		static::assertFalse(MediaUrlSigner::verify($query, self::SECRET, self::NOW));
	}

	#[Group('units')]
	public function testVerifyRejectsIncompleteParameters(): void
	{
		static::assertFalse(MediaUrlSigner::verify([], self::SECRET, self::NOW));
		static::assertFalse(MediaUrlSigner::verify(
			['c' => self::CHECKSUM, 'ext' => 'jpg', 'v' => 'thumb', 'exp' => '1760004000'],
			self::SECRET,
			self::NOW
		));
	}

	#[Group('units')]
	public function testVerifyRejectsNonStringParameters(): void
	{
		static::assertFalse(MediaUrlSigner::verify(
			['c' => self::CHECKSUM, 'ext' => 'jpg', 'v' => 'thumb', 'exp' => 1760004000, 'sig' => ['array']],
			self::SECRET,
			self::NOW
		));
	}

	#[Group('units')]
	public function testAnInvalidTtlFallsBackToTheDefault(): void
	{
		$signer = new MediaUrlSigner(self::SECRET, 0);

		static::assertSame('1760004000', $this->queryOf($signer->thumbUrl(self::CHECKSUM, 'jpg', self::NOW))['exp']);
	}
}
