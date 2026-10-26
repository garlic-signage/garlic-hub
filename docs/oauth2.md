# OAuth2 Implementation - Garlic Hub

## Overview
This document provides an overview of the OAuth2 implementation for Garlic Hub. It includes information about controllers, entities, and repositories used to manage OAuth2 flows such as authorization and token issuance. The implementation is based on the League OAuth2 Server library and follows modular design principles.

---

## Controllers

### `OAuth2Controller`
Handles OAuth2-specific requests such as:
- Authorization requests.
- Token generation and validation.

It leverages the League\OAuth2\Server for core functionalities.

### `LoginController`
Manages user authentication and session data:
- Verifies user credentials.
- Manages OAuth redirection parameters post-login.
- Renews the CSRF token after login and logout (the `CsrfMiddleware` checks it for every form submission).

---

## Entity Classes

### `AccessTokenEntity`
Implements `AccessTokenEntityInterface` and uses the following traits:
- `AccessTokenTrait`
- `EntityTrait`
- `TokenEntityTrait`

Purpose:
- Represents access tokens issued to clients.

### `AuthCodeEntity`
Implements `AuthCodeEntityInterface` and provides:
- Redirect URI handling via `getRedirectUri()` and `setRedirectUri()` methods.

Purpose:
- Represents authorization codes used in the OAuth2 flow.

### `ClientEntity`
Implements `ClientEntityInterface` and uses:
- `ClientTrait`
- `EntityTrait`

Purpose:
- Represents OAuth2 clients with properties such as `client_id`, `redirect_uri`, and `name`.

### `RefreshTokenEntity`
Implements `RefreshTokenEntityInterface` and uses:
- `RefreshTokenTrait`
- `EntityTrait`

Purpose:
- Represents refresh tokens used for renewing access tokens.

### `ScopeEntity`
Implements `ScopeEntityInterface`.

Purpose:
- Represents scopes that define access levels. Currently not actively used in the implementation.

---

## Repositories

### `ClientsRepository`
Implements `ClientRepositoryInterface`.

Methods:
- `getClientEntity(string $clientIdentifier)`: Retrieves the client entity by its identifier.
- `validateClient(string $clientIdentifier, string $clientSecret, string $grantType)`: Validates the client credentials and grant type.

Purpose:
- Handles storage and retrieval of client data.

### `TokensRepository`
Implements:
- `AuthCodeRepositoryInterface`
- `AccessTokenRepositoryInterface`
- `RefreshTokenRepositoryInterface`

Methods:
- `getNewAuthCode()`: Creates a new authorization code.
- `getNewToken()`: Creates a new access token.
- `getNewRefreshToken()`: Creates a new refresh token.
- Persist and revoke methods for authorization codes, access tokens, and refresh tokens.

Purpose:
- Manages the lifecycle of OAuth2 tokens.

### `ScopeRepository`
Implements `ScopeRepositoryInterface`.

Methods:
- `getScopeEntityByIdentifier(string $identifier)`: Fetches a scope entity (currently not implemented).
- `finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, ?string $userIdentifier, ?string $authCodeId)`: Finalizes scopes for a request (currently returns an empty array).

Purpose:
- Manages scopes, though functionality is currently disabled.

---

## Key Features

1. **Modular Design**:
    - Separate classes for entities and repositories ensure flexibility and maintainability.

2. **Token Management**:
    - Comprehensive handling of access tokens, refresh tokens, and authorization codes.

3. **Security**:
    - CSRF token validation by the `CsrfMiddleware` for the login form.
    - Secure password handling using `password_verify` in `ClientsRepository`.

4. **Extensibility**:
    - Built-in support for scopes (can be enabled later).
    - Designed to accommodate additional grant types and token storage mechanisms.

---

## Tutorial: Getting Started with OAuth2

### Step 1: Set Up Clients
1. Create entries in your `oauth2_clients` database table for each client.
    - Include `client_id`, `client_secret`, `redirect_uri`, and supported grant types.
2. Use the `ClientsRepository` to validate clients during the OAuth2 flow.

### Step 2: Configure Authorization
1. Implement the `OAuth2Controller` endpoints for:
    - Authorizing requests (`/api/authorize`).
    - Issuing tokens (`/api/token`).
2. Ensure the `AuthCodeRepository` is correctly integrated for managing authorization codes.

### Step 3: Handle User Authentication
1. Use the `LoginController` to authenticate users.
    - Validate credentials via `authService->login()`.
    - Store session data upon successful login.
2. Redirect users to `/api/authorize` with necessary parameters after login.

### Step 4: Issue and Validate Tokens
1. Use `TokensRepository` to create and persist tokens:
    - Access tokens with `getNewToken()`.
    - Refresh tokens with `getNewRefreshToken()`.
2. Revoke tokens using `revokeAccessToken()` or `revokeRefreshToken()` when needed.
3. Validate tokens during API requests using `isAccessTokenRevoked()`.

### Step 5: Extend Scope Support (Optional)
1. Define supported scopes in `ScopeEntity`.
2. Implement custom logic in `ScopeRepository` for scope validation and assignment.

