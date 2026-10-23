# Protected IT recovery account

A break-glass credential for the IT administrator. Use it when nobody can get
into LSPDMS normally: the Head Secretary forgot their password, an account was
disabled by mistake, or Require OTP / Forgot Password is on while email (SMTP)
is broken.

It is deliberately small. It can do four things and nothing else.

## Architecture

| Piece | Where |
| --- | --- |
| Accounts | table `it_recovery_accounts` (model `App\Models\ItRecoveryAccount`) |
| Audit log | table `it_recovery_audit_logs` (model `App\Models\ItRecoveryAuditLog`), append-only |
| Routes | `routes/it-recovery.php`, loaded from `bootstrap/app.php` in the `web` group, names `it-recovery.*` |
| Session guard | `App\Http\Middleware\EnsureItRecoverySession` (alias `it-recovery.session`) |
| Controller | `App\Http\Controllers\ItRecoveryController` |
| Provisioning | `php artisan lspdms:it-recovery:provision` (`App\Console\Commands\ItRecoveryProvision`) |
| Views | `resources/views/it-recovery/*` (standalone pages, not the app layout) |
| Config | `config/it_recovery.php`: `IT_RECOVERY_EMAIL`, `IT_RECOVERY_ALLOWED_IPS` |
| Migration | `database/migrations/2026_10_24_004000_create_it_recovery_tables.php` |

Separation from the normal application:

- The IT account is not a `login_user` row. No user list, permission editor,
  bulk operation or the Superadmin dashboard reads these tables.
- The IT session uses its own session key, `it_recovery`. It never sets
  `login_user`, so every normal LSPDMS page still redirects to `/login`.
- A browser that holds a normal LSPDMS session (`login_user`, for example the
  Head Secretary) gets 403 on every `/it-recovery` page, including the login
  form. Nothing in the normal app can grant IT recovery access.
- Both tables are excluded from the SQL export and import (handled in
  `App\Services\DatabaseBackupService`), so the Head Secretary cannot read the
  IT password hash from a backup or overwrite the audit log with a restore.
- There is no seeder, no default password and no automatic account creation.

## What it can do

Every action is a POST form that asks for the IT password again, and every
action is written to the IT audit log with the target account id and the
outcome (never a password).

1. **Turn off email-dependent security features.** Calls
   `SecurityPolicy::disableEmailDependentFeatures('it_recovery', 'it:<email>')`,
   which switches off Require OTP and Allow Forgot Password and also writes a
   `system_maintenance_audits` row (`email_features_off:it_recovery`).
   Everyone can then sign in with a password only.
2. **Reset a login_user password.** Generates a random 20-character temporary
   password (letters, numbers, symbols), shows it once on the result page, and
   stores only its hash. The account's `auth_version` is bumped (signing it out
   on every device), `auth_notice` becomes `password_changed`, and its
   remember-me token is cleared. The temporary password is not logged, audited
   or kept in the session.
3. **Re-enable a login_user account** (`is_verified = 1`).
4. **Read the IT audit log** (read-only, 50 per page, newest first).

It cannot read records, messages, backups or imports, and it cannot change
permissions or roles. The account picker shows only id, name, gmail, role and
verified status.

## Sign-in protections

- Password only, by design, so it works while SMTP is down.
- CSRF protected; the session is fully regenerated on sign-in and sign-out.
- Throttle: 5 failed attempts per 15 minutes per (lower-cased email + IP).
- Lockout: 10 consecutive wrong passwords (sign-in or re-entry inside an
  action) lock the account for 30 minutes. A lockout during an action also
  ends the session.
- One generic failure message for unknown email, wrong password, inactive or
  locked account. A bcrypt check runs even when the email is unknown so timing
  does not reveal whether the account exists.
- Session ends after 15 minutes idle or 2 hours total, when the account is
  deactivated or locked, and when its password is rotated.
- Responses are `Cache-Control: no-store`, `X-Frame-Options: DENY`,
  `noindex`.
- Optional IP allow-list: set `IT_RECOVERY_ALLOWED_IPS=127.0.0.1,::1` to make
  `/it-recovery` return 404 for every other client.

## Threat model and trade-offs

- **This is a powerful credential.** Anyone holding it can reset the Head
  Secretary's password and then sign in as them. Treat it like a root
  password: store it offline (sealed envelope or a password manager the IT
  administrator controls), never share it with LSPDMS users, rotate it after
  every use.
- **Protect the server.** Anyone with shell access to the PC can run the
  provision command or edit the database directly. The guarantees here are
  against application users (including the Head Secretary), not against
  someone who controls the machine.
- **The audit log is append-only inside the application only.** The app never
  updates or deletes rows, there is no route to do so, and it is not part of
  export/import. A MariaDB superuser (`root`) can still alter or delete rows
  directly. If that matters, restrict DB root access and copy the audit log
  off the machine periodically.
- **No second factor.** Email OTP would defeat the purpose (it must work while
  SMTP is broken). The compensating controls are the long password, the
  throttle and lockout, short sessions, re-entry for each action, the optional
  IP allow-list and the audit log.
- **The temporary password is visible on screen once.** Hand it over in person
  and have the user change it right away in their profile.

## Operator runbook

All commands run from the project folder (`C:\laragon\www\LSPDMSfinal`).
Run migrations first if the tables do not exist yet (`php artisan migrate`).

### Provision

```
php artisan lspdms:it-recovery:provision it-admin@example.org
```

You are asked for the password twice (hidden). Requirements: at least 16
characters with upper- and lower-case letters, numbers and symbols (max 72).
If `IT_RECOVERY_EMAIL` is set in `.env` the email argument can be left out.

Scripted / non-interactive use reads exactly one line from STDIN:

```
php artisan lspdms:it-recovery:provision it-admin@example.org --password-stdin < secret-file
```

The password is never accepted as a command-line argument or option (it
would end up in shell history and process lists). Without a terminal and
without `--password-stdin` the command refuses to run.

### Rotate (after every use)

```
php artisan lspdms:it-recovery:provision it-admin@example.org --rotate
```

Open IT sessions for that account are signed out on their next request.

### Deactivate / activate

```
php artisan lspdms:it-recovery:provision it-admin@example.org --deactivate
php artisan lspdms:it-recovery:provision it-admin@example.org --activate
```

Deactivating ends open IT sessions immediately. Keep the account deactivated
when not needed if you prefer, and activate it only during an incident.

Each provision, rotate, activate and deactivate writes an audit row
(`account_provisioned`, `password_rotated`, `account_activated`,
`account_deactivated`) with the OS user that ran it.

### Sign in

Open `http://<server>/it-recovery/login` in a browser that is **not** signed in
to LSPDMS (use a private window). The page is not linked anywhere in the app.
Sign out with the "Sign out" link when finished.

### Read the audit log

In the IT recovery pages choose "Audit log", or from the server:

```
mysql -uroot lspdms -e "SELECT id, created_at, event, account_email, ip, details FROM it_recovery_audit_logs ORDER BY id DESC LIMIT 100"
```

Events: `login_succeeded`, `login_failed` (details.reason: `unknown_account`,
`bad_password`, `inactive`), `login_locked`, `login_throttled`,
`account_locked`, `logout`, `session_timeout` (`idle` / `absolute`),
`session_revoked`, `reauth_failed`, `action_disable_email_features`,
`action_reset_password`, `action_reenable_account` (details: `target_user_id`,
`outcome`), `audit_log_viewed`, and the provisioning events above. Timestamps
are stored in UTC and shown in Asia/Manila on the page.

### After an incident

1. Sign out of IT recovery.
2. Have the affected user sign in with the temporary password and change it in
   their profile immediately.
3. Re-enable Require OTP / Allow Forgot Password from the Superadmin dashboard
   once SMTP works again (they are not turned back on automatically).
4. Rotate the IT recovery password (`--rotate`), or deactivate the account.
5. Review the IT audit log for anything you did not do yourself (unexpected
   `login_failed` bursts, sign-ins from unknown IPs). If you find any, rotate
   the password, review the server's access, and check `system_maintenance_audits`.
