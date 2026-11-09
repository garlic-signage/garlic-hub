# User Administration

Every module has its own roles. A user gets them per module on the rights page of the user
(`/users/rights/{UID}`). The roles are bits in `user_acl`, defined in the `[GlobalACLs]` section of
`config/settings/config_<module>.ini`.

Every module with rights derives its validator from
[AbstractAclValidator](../src/Shared/Acl/AbstractAclValidator.php). The only place which writes rights is
[AclGrantService](../src/Shared/Acl/AclGrantService.php).

## Roles per module

| Module    | Viewer | Editor | Sub admin | Module admin |
|-----------|:------:|:------:|:---------:|:------------:|
| mediapool | ✓      | ✓      | ✓         | ✓            |
| playlists | ✓      | ✓      | ✓         | ✓            |
| templates | ✓      | ✓      | ✓         | ✓            |
| player    |        | ✓      | ✓         | ✓            |
| users     |        |        | ✓         | ✓            |
| system    |        |        |           | ✓            |

- **Module admin**: everything in the module. Grants the roles of the module and shares its units.
  The module admin of `system` changes the settings of every module (`/system/settings`) and reads the audit log.
- **Sub admin**: Enterprise only, the same within his companies. The Core edition does not offer the role.
- **Editor**: may use and edit the units which are shared with him, but not delete or share them.
- **Viewer**: may use the units which are shared with him, e.g. a playlist in a zone or a template in a playlist.

Editor and viewer are general bits of the module. Which units a user gets is set per unit in the share dialog
(context menu of a unit → share), stored in `user_vip`.

## What a user may do with a unit

| Who                           | View | Use | Edit | Delete | Share |
|-------------------------------|:----:|:---:|:----:|:------:|:-----:|
| Module admin                  | ✓    | ✓   | ✓    | ✓      | ✓     |
| Owner of a playlist           | ✓    | ✓   | ✓    | ✓      |       |
| Editor of the unit            | ✓    | ✓   | ✓    |        |       |
| Viewer of the unit            | ✓    | ✓   |      |        |       |

Modules differ in the details:
- **Playlists**: the owner edits and deletes his own playlists (`Playlists\Services\AclValidator`).
  While the release is enabled in the playlist settings (off by default), owner and editor only ask for the release
  ("ready for release"), the module admin exports. Every change sets the playlist back to "in progress", the home
  page shows the module admin how many playlists wait and links to the list filtered by the status
  (`Playlists\Services\PlaylistRelease`).
- **Mediapool**: folders are shared one by one, a sub folder does not inherit the rights of its parent.
  The owner of a folder has all rights on it. An editor may upload into a shared folder but not change the
  folder. A viewer and everybody for a public folder may read it (`Mediapool\Services\AclValidator`).

## Rules for granting

- A module admin grants every role of his module.
- Nobody changes his own rights.
- A unit is shared only with users who have the editor or viewer bit of the module.
- A user has at most one role on a unit: the new role replaces the other one.
- Removing a role bit removes the shared units of this role, too.
- Grants and shares are written to the audit log, when it is enabled.

## User menu and administration

- The user menu has "Settings" for the module admin of `system` and "Administration" for everybody who
  administers an area. The administration page (`/administration`, `Shared\Layout\AdministrationLinks`) shows only
  the areas whose rights the user has: user management (module and sub admin of `users`), SSO providers (module admin
  of `users`), templates (module and sub admin of `templates`), audit log (module admin of `system`).
  New areas go onto this page, not into the menu.

## Users

- **Delete** anonymizes the user: the row stays, so the UID is never given to somebody else and his content
  keeps an owner. Email, password and session are cleared, tokens, rights and the second factor are removed, his
  media folder is deleted.
- **Password reset** by an admin clears the password, ends the running sessions and the autologin and creates a
  link with which the user sets a new password.
- **2FA reset** by an admin turns the second factor of a user off (lost device). The button is shown only for a
  user who has it. The user sets it up again, at once after his next login while it is required.

## Own password

- A logged-in user changes his password in his account (tab password) only with his current password. Wrong ones
  count like wrong logins (`Auth\LoginThrottle`): a session which somebody took over can not change it and lock the
  owner out. The change ends all other sessions and remember-me logins.
- A link (initial password, reset) needs no current password: the link proves it.
- A user without password, who logs in only by SSO, gets no password tab: a local password would be a second way
  into his account past the MFA of his provider.

## Two-factor authentication

- Every user sets it up in his account (tab 2FA) with an authenticator app such as Aegis: TOTP with SHA1, 6 digits
  and 30 seconds (`Shared\User\TwoFactor\TwoFactorService`, library spomky-labs/otphp). It counts only after the
  first valid code, which also gives 10 recovery codes. They are shown once and stored as hashes.
- The login asks for the code after the password (`/login/two-factor`). The session counts as logged in and the
  remember-me cookie is created only after it. Wrong codes are limited like wrong passwords, a code is accepted
  once, also one time slot before and after the current one.
- The module admin of `system` requires it for everybody in the settings (`[TwoFactor] required`, off by default),
  effective at once: a user without it reaches only his account and the logout (`Auth\AuthMiddleware`). While it is
  required, a user can not turn it off himself. Turning it off and new recovery codes need a code (of the app or a
  recovery code), the wrong ones count together with the ones of the login: a session which somebody took over is
  not enough.
- Players and OAuth2 tokens are not affected.
- A user who logs in by SSO is not asked for this second factor: his provider asks for its own.

## Single sign-on (SSO)

- The module admin of `users` sets up the providers of his company on the page "SSO providers" (`/users/sso`,
  table `sso_providers`): name, issuer URL, client ID, client secret and allowed domains, each provider can be
  turned off. Without a provider there is no SSO. Every OpenID Connect provider works, the hub finds its endpoints and
  keys by the discovery (`/.well-known/openid-configuration`). The secret is stored encrypted and is never shown
  again, an empty field keeps it.
- At the provider the app is registered with the redirect URI `https://<your hub>/login/sso/callback`, the page
  shows it.
- The providers have a `company_id`: in Enterprise every customer gets his own. A user is linked to the provider
  (`user_sso`), not to the issuer, a new user gets the company of the provider, and an account of another company is
  never linked.
- Issuer URLs: Google `https://accounts.google.com`, Entra ID `https://login.microsoftonline.com/<tenant id>/v2.0`,
  Okta `https://<your org>.okta.com`, Keycloak `https://<host>/realms/<realm>`.
- The login page shows a button "Log in with ..." per provider. The login checks the signed ID token (signature, issuer,
  audience, expiry, nonce), the state and PKCE (`Auth\SsoLogin`, library facile-it/php-openid-client).
- The first login creates the user without rights and without password, with his media folder. The admin grants
  the rights. The user is found again by the provider and his subject there, not by his email, which may change.
- Deleting a provider keeps its users: they log in by password, or by the provider again when it is set up once more.
- Every SSO login needs an email which the provider verified (`email_verified`, with Entra ID `xms_edov`), otherwise
  it is refused (`sso_email_not_verified`), before the allowed domains and before an account is created or linked. An
  email which the user may enter himself at the provider would pass the allowed domains and take over an account
  with the same email. There is no switch per provider to trust unverified emails.
- **Entra ID** sends no `email_verified`. Add the optional claim `xms_edov` to the ID token in the app registration
  (Token configuration → Add optional claim → ID → `xms_edov`), without it every Entra login is refused.
  Keycloak verifies an email only with the email verification of the realm (or an admin marks it as verified).
- An existing account with the same email is linked on the first login by SSO.
- Allowed domains limit who gets in by the domain of the email. With Google they are needed: everybody has a Google
  account. With allowed domains Google lets in only the accounts of the company's Workspace (claim `hd`): a private
  Google account with an email of the domain, e.g. of a former employee, is refused. Entra ID and Okta let in only
  the users of the directory.
- There is no remember-me: a user blocked at the provider is out with the end of his session. Blocking or deleting
  him in the hub ends his access at once. The password login stays, e.g. for the admin when the provider is down.
