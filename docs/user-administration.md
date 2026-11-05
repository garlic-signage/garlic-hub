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
  The module admin of `system` changes the settings and reads the audit log.
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

## Users

- **Delete** anonymizes the user: the row stays, so the UID is never given to somebody else and his content
  keeps an owner. Email, password and session are cleared, tokens and rights are removed, his media folder is
  deleted.
- **Password reset** by an admin clears the password, ends the running sessions and the autologin and creates a
  link with which the user sets a new password.
