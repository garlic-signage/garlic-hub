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

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Http\Session;
use App\Framework\Security\Crypt;
use App\Shared\Settings\SettingsService;
use App\Shared\User\Sso\SsoIdentity;
use App\Shared\User\Sso\SsoRefusedException;
use App\Shared\User\Sso\SsoUserProvisioning;
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
 * The login with an OpenID Connect provider (Google, Entra ID, Okta, Keycloak ...), set in the settings of
 * the users module. The library checks the signed ID token: signature, issuer, audience, expiry and nonce.
 * Here: the state against forged answers, PKCE against a stolen code, and the allowed domains.
 * The provider answers within 10 minutes, the answer counts once.
 */
readonly class SsoLogin
{
	private const string MODULE_NAME = 'users';
	private const string SECTION = 'Sso';
	private const string PENDING = 'sso_pending';
	private const int PENDING_SECONDS = 600;
	// discovery and keys of the provider change rarely
	private const int CACHE_SECONDS = 3600;
	// the clocks of the provider and of the server differ a little
	private const int CLOCK_TOLERANCE = 60;

	public function __construct(
		private Config $config,
		private Session $session,
		private Crypt $crypt,
		private SsoUserProvisioning $provisioning,
		private HttpClient $httpClient,
		private CacheInterface $cache,
		private LoggerInterface $logger
	) {}

	public function isEnabled(): bool
	{
		return $this->config->isSwitchOn('enabled', self::MODULE_NAME, self::SECTION) && $this->setting('issuer') !== '' && $this->setting('client_id') !== '';
	}

	public function providerName(): string
	{
		$name = $this->setting('name');

		return $name !== '' ? $name : (string) parse_url($this->setting('issuer'), PHP_URL_HOST);
	}

	/**
	 * The address of the provider to which the browser goes. State, nonce and the verifier of PKCE wait in the session.
	 *
	 * @throws Throwable
	 */
	public function authorizationUri(string $redirectUri): string
	{
		$state    = $this->randomToken();
		$nonce    = $this->randomToken();
		$verifier = $this->randomToken();
		$this->session->set(self::PENDING, ['state' => $state, 'nonce' => $nonce, 'code_verifier' => $verifier, 'expires' => time() + self::PENDING_SECONDS]);

		return $this->authorizationService()->getAuthorizationUri($this->client($redirectUri), [
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

		try
		{
			$client  = $this->client($redirectUri);
			$service = $this->authorizationService();
			$session = AuthSession::fromArray(['state' => $pending['state'], 'nonce' => $pending['nonce'], 'code_verifier' => $pending['code_verifier']]);
			$tokens  = $service->callback($client, $service->getCallbackParams($request, $client), $redirectUri, $session);
			if ($tokens->getIdToken() === null)
				throw new SsoRefusedException('sso_failed');

			$identity = $this->identityOf($tokens->claims());
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

		if (!$this->isAllowedDomain($identity->email))
		{
			$this->logger->warning('SSO login refused for the domain of ' . $identity->email);
			throw new SsoRefusedException('sso_domain');
		}

		return $this->provisioning->provide($identity);
	}

	/**
	 * @param array<string,mixed> $claims
	 */
	private function identityOf(array $claims): SsoIdentity
	{
		$text = static fn(string $claim): string => is_string($claims[$claim] ?? null) ? trim($claims[$claim]) : '';
		if ($text('iss') === '' || $text('sub') === '')
			throw new SsoRefusedException('sso_failed');

		return new SsoIdentity(
			$text('iss'),
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
	private function isAllowedDomain(string $email): bool
	{
		$allowed = array_filter(array_map(static fn(string $domain): string => strtolower(trim($domain)), explode(',', $this->setting('allowed_domains'))));
		if ($allowed === [])
			return true;

		$at = strrpos($email, '@');

		return $at !== false && in_array(substr($email, $at + 1), $allowed, true);
	}

	/**
	 * @return array{state:string, nonce:string, code_verifier:string}|null
	 */
	private function takePending(): ?array
	{
		$pending = $this->session->get(self::PENDING);
		$this->session->delete(self::PENDING);
		if (!is_array($pending) || !is_int($pending['expires'] ?? null) || $pending['expires'] < time())
			return null;

		foreach (['state', 'nonce', 'code_verifier'] as $key)
		{
			if (!is_string($pending[$key] ?? null) || $pending[$key] === '')
				return null;
		}

		return ['state' => $pending['state'], 'nonce' => $pending['nonce'], 'code_verifier' => $pending['code_verifier']];
	}

	/**
	 * @throws Throwable
	 */
	private function client(string $redirectUri): ClientInterface
	{
		$issuer = (new IssuerBuilder())
			->setMetadataProviderBuilder((new MetadataProviderBuilder())->setHttpClient($this->httpClient)->setCache($this->cache)->setCacheTtl(self::CACHE_SECONDS))
			->setJwksProviderBuilder((new JwksProviderBuilder())->withHttpClient($this->httpClient)->withCache($this->cache)->withCacheTtl(self::CACHE_SECONDS))
			->build($this->setting('issuer'));

		$clientId = $this->setting('client_id');
		if ($clientId === '' || $redirectUri === '')
			throw new SsoRefusedException('sso_failed');

		// the secret in the body, when the provider does not take it in the header
		$methods    = $issuer->getMetadata()->getTokenEndpointAuthMethodsSupported();
		$authMethod = in_array('client_secret_basic', $methods, true) || $methods === [] ? 'client_secret_basic' : 'client_secret_post';

		return (new ClientBuilder())
			->setIssuer($issuer)
			->setClientMetadata(ClientMetadata::fromArray([
				'client_id'                  => $clientId,
				'client_secret'              => SettingsService::decryptSecret($this->setting('client_secret'), $this->crypt),
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

	private function setting(string $key): string
	{
		return IniConfigLoader::withoutComment($this->config->getConfigValue($key, self::MODULE_NAME, self::SECTION));
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
