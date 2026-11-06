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

namespace App\Modules\Auth;

use App\Framework\Http\Https;
use App\Framework\Http\Session;
use App\Framework\Security\Crypt;
use App\Shared\User\Repositories\Core\SsoProvidersRepository;
use App\Shared\User\Sso\SsoIdentity;
use App\Shared\User\Sso\SsoProvider;
use App\Shared\User\Sso\SsoRefusedException;
use App\Shared\User\Sso\SsoUserProvisioning;
use Defuse\Crypto\Crypto;
use Facile\JoseVerifier\JWK\JwksProviderBuilder;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Issuer\Metadata\Provider\MetadataProviderBuilder;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Token\IdTokenVerifierBuilder;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * The login with an OpenID Connect provider (Google, Entra ID, Okta, Keycloak ...), which the module admin of
 * users sets up (table sso_providers). The library checks the signed ID token: signature, issuer, audience, expiry and nonce.
 * Here: the state against forged answers, PKCE against a stolen code, and the allowed domains.
 * The provider answers within 10 minutes, the answer counts once.
 */
readonly class SsoLogin
{
	private const string PENDING = 'sso_pending';
	private const int PENDING_SECONDS = 600;
	// discovery and keys of the provider change rarely
	private const int CACHE_SECONDS = 3600;
	// the clocks of the provider and of the server differ a little
	private const int CLOCK_TOLERANCE = 60;

	public function __construct(
		private SsoProvidersRepository $providers,
		private Session $session,
		private Crypt $crypt,
		private SsoUserProvisioning $provisioning,
		private HttpClient $httpClient,
		private CacheInterface $cache,
		private LoggerInterface $logger
	) {}

	/**
	 * The address the provider sends the browser back to, registered there. Behind a proxy the scheme comes from it.
	 */
	public static function redirectUri(ServerRequestInterface $request): string
	{
		$uri  = $request->getUri();
		$port = $uri->getPort();

		return (Https::isActive() || $uri->getScheme() === 'https' ? 'https' : 'http') . '://' . $uri->getHost() . ($port !== null ? ':' . $port : '') . '/login/sso/callback';
	}

	/**
	 * The buttons of the login page.
	 *
	 * @return list<SsoProvider>
	 * @throws \Doctrine\DBAL\Exception
	 */
	public function providers(): array
	{
		return $this->providers->findEnabled();
	}

	/**
	 * The address of the provider to which the browser goes, null for a provider which is unknown or off.
	 * Provider, state, nonce and the verifier of PKCE wait in the session.
	 *
	 * @throws Throwable
	 */
	public function authorizationUri(int $providerId, string $redirectUri): ?string
	{
		$provider = $this->enabledProvider($providerId);
		if ($provider === null)
			return null;

		$state    = $this->randomToken();
		$nonce    = $this->randomToken();
		$verifier = $this->randomToken();
		$this->session->set(self::PENDING, ['provider_id' => $provider->id, 'state' => $state, 'nonce' => $nonce, 'code_verifier' => $verifier, 'expires' => time() + self::PENDING_SECONDS]);

		return $this->authorizationService()->getAuthorizationUri($this->client($provider, $redirectUri), [
			'scope'                 => 'openid email profile',
			'state'                 => $state,
			'nonce'                 => $nonce,
			'code_challenge'        => $this->base64Url(hash('sha256', $verifier, true)),
			'code_challenge_method' => 'S256'
		]);
	}

	/**
	 * The answer of the provider: the user it confirmed.
	 *
	 * @return int the UID
	 * @throws SsoRefusedException with the key of the translation
	 */
	public function finish(ServerRequestInterface $request, string $redirectUri): int
	{
		$pending = $this->takePending();
		$params  = $request->getQueryParams();
		$state   = $params['state'] ?? null;
		if ($pending === null || !is_string($state) || !hash_equals($pending['state'], $state))
			throw new SsoRefusedException('sso_expired');

		// the admin may have turned it off since
		$provider = $this->enabledProvider($pending['provider_id']);
		if ($provider === null)
			throw new SsoRefusedException('sso_failed');

		try
		{
			$client  = $this->client($provider, $redirectUri);
			$service = $this->authorizationService();
			$session = AuthSession::fromArray(['state' => $pending['state'], 'nonce' => $pending['nonce'], 'code_verifier' => $pending['code_verifier']]);
			$tokens  = $service->callback($client, $service->getCallbackParams($request, $client), $redirectUri, $session);
			if ($tokens->getIdToken() === null)
				throw new SsoRefusedException('sso_failed');

			$identity = $this->identityOf($provider, $tokens->claims());
		}
		catch (SsoRefusedException $e)
		{
			throw $e;
		}
		catch (Throwable $e)
		{
			// a refusal of the provider (the user cancelled) as well as a forged or expired token
			$this->logger->warning('SSO login failed: ' . $e->getMessage());
			throw new SsoRefusedException('sso_failed');
		}

		if (!$this->isAllowedDomain($provider, $identity->email))
		{
			$this->logger->warning('SSO login refused for the domain of ' . $identity->email);
			throw new SsoRefusedException('sso_domain');
		}

		return $this->provisioning->provide($identity);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	private function enabledProvider(int $providerId): ?SsoProvider
	{
		$provider = $this->providers->findProvider($providerId);

		return $provider !== null && $provider->enabled ? $provider : null;
	}

	/**
	 * @param array<string,mixed> $claims
	 */
	private function identityOf(SsoProvider $provider, array $claims): SsoIdentity
	{
		$text = static fn(string $claim): string => is_string($claims[$claim] ?? null) ? trim($claims[$claim]) : '';
		if ($text('iss') === '' || $text('sub') === '')
			throw new SsoRefusedException('sso_failed');

		return new SsoIdentity(
			$provider,
			$text('sub'),
			strtolower($text('email')),
			// Entra ID sends no email_verified, but xms_edov when the domain of the email is verified
			($claims['email_verified'] ?? null) === true || ($claims['xms_edov'] ?? null) === true,
			$text('name')
		);
	}

	/**
	 * Without a list everybody whom the provider lets in. With Google a list is needed: everybody has a Google account.
	 */
	private function isAllowedDomain(SsoProvider $provider, string $email): bool
	{
		if ($provider->allowedDomains === [])
			return true;

		$at = strrpos($email, '@');

		return $at !== false && in_array(substr($email, $at + 1), $provider->allowedDomains, true);
	}

	/**
	 * @return array{provider_id:int, state:string, nonce:string, code_verifier:string}|null
	 */
	private function takePending(): ?array
	{
		$pending = $this->session->get(self::PENDING);
		$this->session->delete(self::PENDING);
		if (!is_array($pending) || !is_int($pending['expires'] ?? null) || $pending['expires'] < time() || !is_int($pending['provider_id'] ?? null))
			return null;

		foreach (['state', 'nonce', 'code_verifier'] as $key)
		{
			if (!is_string($pending[$key] ?? null) || $pending[$key] === '')
				return null;
		}

		return ['provider_id' => $pending['provider_id'], 'state' => $pending['state'], 'nonce' => $pending['nonce'], 'code_verifier' => $pending['code_verifier']];
	}

	/**
	 * @throws Throwable
	 */
	private function client(SsoProvider $provider, string $redirectUri): ClientInterface
	{
		$issuer = (new IssuerBuilder())
			->setMetadataProviderBuilder((new MetadataProviderBuilder())->setHttpClient($this->httpClient)->setCache($this->cache)->setCacheTtl(self::CACHE_SECONDS))
			->setJwksProviderBuilder((new JwksProviderBuilder())->withHttpClient($this->httpClient)->withCache($this->cache)->withCacheTtl(self::CACHE_SECONDS))
			->build($provider->issuer);

		$clientId = $provider->clientId;
		if ($clientId === '' || $redirectUri === '')
			throw new SsoRefusedException('sso_failed');

		// the secret in the body, when the provider does not take it in the header
		$methods    = $issuer->getMetadata()->getTokenEndpointAuthMethodsSupported();
		$authMethod = in_array('client_secret_basic', $methods, true) || $methods === [] ? 'client_secret_basic' : 'client_secret_post';

		return (new ClientBuilder())
			->setIssuer($issuer)
			->setClientMetadata(ClientMetadata::fromArray([
				'client_id'                  => $clientId,
				'client_secret'              => $provider->encryptedSecret === '' ? '' : Crypto::decrypt($provider->encryptedSecret, $this->crypt->getEncryptionKey()),
				'token_endpoint_auth_method' => $authMethod,
				'redirect_uris'              => [$redirectUri]
			]))
			->setHttpClient($this->httpClient)
			->build();
	}

	private function authorizationService(): AuthorizationService
	{
		$builder = (new AuthorizationServiceBuilder())->setIdTokenVerifierBuilder((new IdTokenVerifierBuilder())->setClockTolerance(self::CLOCK_TOLERANCE));
		$builder->setHttpClient($this->httpClient);

		return $builder->build();
	}

	private function randomToken(): string
	{
		return $this->base64Url(random_bytes(32));
	}

	private function base64Url(string $bytes): string
	{
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}
}
