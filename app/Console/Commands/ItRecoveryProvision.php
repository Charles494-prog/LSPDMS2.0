<?php

namespace App\Console\Commands;

use App\Models\ItRecoveryAccount;
use App\Models\ItRecoveryAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates, rotates, activates or deactivates the protected IT recovery
 * account. The password is only ever read from a hidden prompt (typed twice)
 * or, for scripted use, one line of STDIN with --password-stdin. It is never
 * accepted as a command-line argument, so it cannot leak into shell history
 * or process listings.
 */
class ItRecoveryProvision extends Command
{
    /** Container key tests may bind to a readable stream instead of STDIN. */
    public const STDIN_BINDING = 'it-recovery.stdin';

    protected $signature = 'lspdms:it-recovery:provision
        {email? : Account email (defaults to IT_RECOVERY_EMAIL)}
        {--rotate : Change the password of the existing account}
        {--deactivate : Disable the account (its sessions end on the next request)}
        {--activate : Re-enable a deactivated account}
        {--password-stdin : Read the password from one line of STDIN (non-interactive use)}';

    protected $description = 'Provision or manage the protected IT recovery account';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->argument('email') ?? config('it_recovery.email') ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $this->error('Give a valid email argument or set IT_RECOVERY_EMAIL.');

            return self::FAILURE;
        }

        $modes = array_filter([
            'rotate' => (bool) $this->option('rotate'),
            'deactivate' => (bool) $this->option('deactivate'),
            'activate' => (bool) $this->option('activate'),
        ]);
        if (count($modes) > 1) {
            $this->error('Use only one of --rotate, --deactivate or --activate.');

            return self::FAILURE;
        }
        $mode = array_key_first($modes) ?? 'create';

        $account = ItRecoveryAccount::query()->where('email', $email)->first();

        if ($mode === 'activate' || $mode === 'deactivate') {
            return $this->toggle($account, $email, $mode === 'activate');
        }

        if ($mode === 'create' && $account !== null) {
            $this->error('An IT recovery account with that email already exists. Use --rotate to change its password.');

            return self::FAILURE;
        }
        if ($mode === 'rotate' && $account === null) {
            $this->error('No IT recovery account exists with that email.');

            return self::FAILURE;
        }

        $password = $this->readPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'max:72', Password::min(16)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($account, $email, $password, $mode): void {
            $account ??= new ItRecoveryAccount(['email' => $email, 'is_active' => true]);
            $account->forceFill([
                'password' => Hash::make($password),
                'failed_attempts' => 0,
                'locked_until' => null,
                'password_changed_at' => now(),
            ])->save();

            ItRecoveryAuditLog::record(
                $mode === 'rotate' ? 'password_rotated' : 'account_provisioned',
                $email,
                null,
                ['via' => 'artisan', 'os_user' => mb_substr((string) get_current_user(), 0, 64)]
            );
        });

        $this->info($mode === 'rotate'
            ? 'The IT recovery password has been rotated. Existing IT sessions are signed out.'
            : 'The IT recovery account has been provisioned.');

        return self::SUCCESS;
    }

    private function toggle(?ItRecoveryAccount $account, string $email, bool $active): int
    {
        if ($account === null) {
            $this->error('No IT recovery account exists with that email.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($account, $email, $active): void {
            $account->forceFill(['is_active' => $active])->save();
            ItRecoveryAuditLog::record(
                $active ? 'account_activated' : 'account_deactivated',
                $email,
                null,
                ['via' => 'artisan', 'os_user' => mb_substr((string) get_current_user(), 0, 64)]
            );
        });

        $this->info($active ? 'The IT recovery account is active.' : 'The IT recovery account has been deactivated.');

        return self::SUCCESS;
    }

    private function readPassword(): ?string
    {
        if ($this->option('password-stdin')) {
            $stream = app()->bound(self::STDIN_BINDING) ? app(self::STDIN_BINDING) : STDIN;
            $line = is_resource($stream) ? fgets($stream) : false;
            if ($line === false) {
                $this->error('No password was received on STDIN.');

                return null;
            }

            return rtrim($line, "\r\n");
        }

        if (! $this->input->isInteractive()) {
            $this->error('Refusing to run non-interactively. Use --password-stdin to pipe the password; never pass it as an argument.');

            return null;
        }

        $password = (string) $this->secret('IT recovery password (min 16 chars, upper/lowercase letters, numbers and symbols)');
        $confirmation = (string) $this->secret('Repeat the IT recovery password');
        if (! hash_equals($password, $confirmation)) {
            $this->error('The two passwords did not match.');

            return null;
        }

        return $password;
    }
}
