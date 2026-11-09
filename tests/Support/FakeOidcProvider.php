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

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * An OpenID Connect provider in memory, as the HTTP client of the login: discovery, keys and the token endpoint.
 * authorize() plays the browser at the provider. The provider checks the code, PKCE and the client secret
 * like a real one. The test changes the claims of the next ID token or signs it with a foreign key.
 */
class FakeOidcProvider implements ClientInterface
{
	public const string ISSUER = 'https://idp.example.test';

	/** @var array<string,mixed> replace (null: remove) claims of the next ID token */
	public array $claims = [];
	public bool $signWithForeignKey = false;
	/** the Authorization header of the last token request */
	public string $tokenAuthorization = '';
	/** @var list<string> how the provider takes the secret: in the header (basic) or in the body (post) */
	public array $authMethods = ['client_secret_basic'];
	/** the scope of the last authorization */
	public string $scope = '';

	private static ?JWK $key = null;
	private static ?JWK $foreignKey = null;
	/** @var array<string,array{nonce:string, challenge:string, client_id:string}> code => what the authorization asked for */
	private array $codes = [];

	public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $issuer = self::ISSUER)
	{
		// creating an RSA key takes a while: once for all tests
		self::$key        ??= JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'test']);
		self::$foreignKey ??= JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'test']);
	}

	/**
	 * The user logs in at the provider: the answer which the browser brings back.
	 *
	 * @return array<string,string> the query of the callback
	 */
	public function authorize(string $authorizationUri): array
	{
		parse_str((string) parse_url($authorizationUri, PHP_URL_QUERY), $params);
		$text = static fn(string $name): string => is_string($params[$name] ?? null) ? $params[$name] : '';
		if (!str_starts_with($authorizationUri, $this->issuer . '/authorize?') || $text('code_challenge_method') !== 'S256')
			throw new RuntimeException('unexpected authorization: ' . $authorizationUri);

		$this->scope = $text('scope');
		$code = bin2hex(random_bytes(8));
		$this->codes[$code] = ['nonce' => $text('nonce'), 'challenge' => $text('code_challenge'), 'client_id' => $text('client_id')];

		return ['code' => $code, 'state' => $text('state')];
	}

	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$uri = (string) $request->getUri();

		return match (strtok($uri, '?'))
		{
			$this->issuer . '/.well-known/openid-configuration' => $this->json([
				'issuer'                                => $this->issuer,
				'authorization_endpoint'                => $this->issuer . '/authorize',
				'token_endpoint'                        => $this->issuer . '/token',
				'jwks_uri'                              => $this->issuer . '/jwks',
				'response_types_supported'              => ['code'],
				'subject_types_supported'               => ['public'],
				'id_token_signing_alg_values_supported' => ['RS256'],
				'token_endpoint_auth_methods_supported' => $this->authMethods,
				'code_challenge_methods_supported'      => ['S256']
			]),
			$this->issuer . '/jwks'  => $this->json(['keys' => [$this->key()->toPublic()->all()]]),
			$this->issuer . '/token' => $this->token($request),
			default                 => new Response(404)
		};
	}

	private function token(RequestInterface $request): ResponseInterface
	{
		$this->tokenAuthorization = $request->getHeaderLine('Authorization');
		parse_str((string) $request->getBody(), $params);
		$code     = is_string($params['code'] ?? null) ? $params['code'] : '';
		$verifier = is_string($params['code_verifier'] ?? null) ? $params['code_verifier'] : '';
		$asked    = $this->codes[$code] ?? null;
		// a code counts once
		unset($this->codes[$code]);

		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		if ($asked === null || !hash_equals($asked['challenge'], $challenge) || !$this->isClientAuthenticated($params))
			return $this->json(['error' => 'invalid_grant'], 400);

		return $this->json(['access_token' => 'access', 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => $this->idToken($asked)]);
	}

	/**
	 * @param array<mixed> $params the body of the token request
	 */
	private function isClientAuthenticated(array $params): bool
	{
		if (in_array('client_secret_basic', $this->authMethods, true))
			return $this->tokenAuthorization === 'Basic ' . base64_encode(urlencode($this->clientId) . ':' . urlencode($this->clientSecret));

		return $this->tokenAuthorization === '' && ($params['client_id'] ?? null) === $this->clientId && ($params['client_secret'] ?? null) === $this->clientSecret;
	}

	/**
	 * A valid ID token for the login of a code, signed by the provider: what a thief of a token of the browser has.
	 */
	public function idTokenFor(string $code): string
	{
		$asked = $this->codes[$code] ?? null;
		if ($asked === null)
			throw new RuntimeException('unknown code');

		return $this->idToken($asked);
	}

	/**
	 * @param array{nonce:string, challenge:string, client_id:string} $asked
	 */
	private function idToken(array $asked): string
	{
		$claims = array_merge([
			'iss'            => $this->issuer,
			'sub'            => 'subject-carla',
			'aud'            => $asked['client_id'],
			'iat'            => time(),
			'exp'            => time() + 300,
			'nonce'          => $asked['nonce'],
			'email'          => 'carla@example.com',
			'email_verified' => true,
			'name'           => 'Carla'
		], $this->claims);

		return $this->sign(array_filter($claims, static fn(mixed $value): bool => $value !== null));
	}

	/**
	 * @param array<string,mixed> $claims
	 */
	private function sign(array $claims): string
	{
		$key = $this->signWithForeignKey ? self::$foreignKey : $this->key();
		if ($key === null)
			throw new RuntimeException('no key');

		$jws = new JWSBuilder(new AlgorithmManager([new RS256()]))
			->create()
			->withPayload((string) json_encode($claims))
			->addSignature($key, ['alg' => 'RS256', 'kid' => 'test'])
			->build();

		return new CompactSerializer()->serialize($jws, 0);
	}

	private function key(): JWK
	{
		return self::$key ?? throw new RuntimeException('no key');
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function json(array $data, int $status = 200): ResponseInterface
	{
		return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($data));
	}
}
