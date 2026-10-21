<?php

namespace App\Http\Controllers;

use App\Models\GlobalVariable;
use App\Models\Purok;
use App\Models\R402Hakbang;
use App\Models\Record;
use App\Models\RecordAuditLog;
use App\Models\SystemSmtpSetting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\SystemSmtpConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperadminController extends Controller
{
    public function index(Request $request, SystemSmtpConfiguration $smtpConfiguration)
    {
        $auditFilters = $this->validatedAuditFilters($request);
        $users = User::query()
            ->select([
                'id',
                'gmail',
                'tungkulin',
                'is_verified',
                'first_name',
                'last_name',
                'profile_picture',
                'dashboard_allow',
                'form_generator_allow',
                'Delistment_allow',
                'Inquire_Allow',
                'tarheta_status_allow',
                'filter_bnh_helper_allow',
                'chatbot_records_allow',
            ])
            ->orderBy('id')
            ->get();

        $puroks = Purok::query()
            ->with('secretaryUser:id,first_name,last_name,gmail')
            ->orderBy('purok_number')
            ->get();
        $smtpSettings = SystemSmtpSetting::query()->first();
        $smtpPasswordConfigured = filled($smtpSettings?->getRawOriginal('app_password'));
        $smtpDatabaseConfigured = filled($smtpSettings?->email) && $smtpPasswordConfigured;
        $smtpConfiguredFromEnvironment = $smtpConfiguration->isEnvironmentConfigured();
        $smtpConfigured = $smtpConfiguration->isConfigured();
        $securitySettings = GlobalVariable::query()->first();

        return view('superadmin-dashboard', [
            'users' => $users,
            'puroks' => $puroks,
            'globalVariables' => $securitySettings,
            'r402Hakbangs' => R402Hakbang::query()
                ->orderBy('status')
                ->orderBy('code')
                ->get(),
            'recordAuditLogs' => $this->recordAuditQuery($auditFilters)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
            'auditFilters' => $auditFilters,
            'databaseTables' => app(DatabaseBackupService::class)->tableNames(),
            'smtpEmail' => $smtpSettings?->email
                ?: ($smtpConfiguredFromEnvironment ? config('mail.mailers.smtp.username') : null),
            'smtpPasswordConfigured' => $smtpPasswordConfigured,
            'smtpDatabaseConfigured' => $smtpDatabaseConfigured,
            'smtpConfiguredFromEnvironment' => $smtpConfiguredFromEnvironment && ! $smtpDatabaseConfigured,
            'smtpConfigured' => $smtpConfigured,
            'smtpRequiredForAuthentication' => (bool) ($securitySettings?->require_otp ?? false)
                || (bool) ($securitySettings?->allow_forgot_password ?? false),
            'headSecretaryTransferCandidates' => $users->filter(fn (User $user): bool => $user->is_verified
                && mb_strtolower(trim((string) $user->tungkulin)) === 'secretary'
                && (int) $user->id !== (int) $request->session()->get('login_user.id'))->values(),
        ]);
    }

    public function downloadRecordAuditLogs(Request $request): StreamedResponse
    {
        $auditFilters = $this->validatedAuditFilters($request);
        $query = $this->recordAuditQuery($auditFilters)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Record Activity Log</title>'
                .'<style>@page{margin:.5in}body{font-family:Arial,sans-serif;font-size:9pt}h1{text-align:center;font-size:16pt}p{margin:4px 0 12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #222;padding:5px;text-align:left;vertical-align:top}th{background:#e8edf5}</style>'
                .'</head><body><h1>Record Activity Log</h1><table><thead><tr>'
                .'<th>Date of action</th><th>Secretary email</th><th>Record</th><th>Record ID</th><th>Action</th><th>Details</th><th>Changed values</th>'
                .'</tr></thead><tbody>';
            foreach ($query->cursor() as $auditLog) {
                $values = [
                    $auditLog->created_at?->timezone('Asia/Manila')->format('M d, Y h:i:s A') ?? '',
                    $auditLog->actor_email ?? 'System / background task',
                    $auditLog->record_name ?? 'Deleted record',
                    (string) ($auditLog->record_id ?? ''),
                    $auditLog->action,
                    $auditLog->summary,
                    json_encode($auditLog->changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ];
                echo '<tr>';
                foreach ($values as $value) {
                    echo '<td>'.e($value).'</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></body></html>';
        }, 'record-activity-log.doc', [
            'Content-Type' => 'application/msword; charset=UTF-8',
        ]);
    }

    private function validatedAuditFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'record_name' => ['nullable', 'string', 'max:150'],
            'actor_email' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function recordAuditQuery(array $filters): Builder
    {
        return RecordAuditLog::query()
            ->when($filters['date_from'] ?? null, function (Builder $query, string $date): void {
                $query->where('created_at', '>=', Carbon::parse($date, 'Asia/Manila')->startOfDay()->utc());
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $date): void {
                $query->where('created_at', '<=', Carbon::parse($date, 'Asia/Manila')->endOfDay()->utc());
            })
            ->when($filters['record_name'] ?? null, function (Builder $query, string $name): void {
                $query->where('record_name', 'like', '%'.$name.'%');
            })
            ->when($filters['actor_email'] ?? null, function (Builder $query, string $email): void {
                $query->where('actor_email', 'like', '%'.$email.'%');
            });
    }

    public function storeR402Hakbang(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['MS', 'UWP'])],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d+-\d+$/',
                Rule::unique('r4_02_hakbangs', 'code')->where('status', $request->input('status')),
            ],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $hakbang = R402Hakbang::query()->create([
            ...$validated,
            'code' => trim($validated['code']),
            'description' => trim($validated['description']),
        ]);

        return response()->json([
            'message' => "{$hakbang->status} hakbang {$hakbang->code} saved.",
            'data' => $hakbang,
        ], 201);
    }

    public function updateR402Hakbang(Request $request, R402Hakbang $hakbang): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['MS', 'UWP'])],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^\d+-\d+$/',
                Rule::unique('r4_02_hakbangs', 'code')
                    ->where('status', $request->input('status'))
                    ->ignore($hakbang->id),
            ],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $hakbang->fill([
            ...$validated,
            'code' => trim($validated['code']),
            'description' => trim($validated['description']),
        ])->save();

        return response()->json([
            'message' => "{$hakbang->status} hakbang {$hakbang->code} updated.",
            'data' => $hakbang->fresh(),
        ]);
    }

    public function destroyR402Hakbang(R402Hakbang $hakbang): JsonResponse
    {
        $hakbang->delete();

        return response()->json(['message' => 'R4-02 hakbang deleted.']);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'tungkulin' => ['sometimes', 'nullable', 'string', 'max:100', Rule::in(['Secretary', 'Head Secretary'])],
            'is_verified' => ['sometimes', 'boolean'],
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'user' => 'Choose a role or access value to update.',
            ]);
        }

        $isHeadSecretary = static fn (?string $role): bool => in_array(
            mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $role) ?? '')),
            ['head secretary', 'secretary head'],
            true
        );
        $isSelf = (int) $request->session()->get('login_user.id') === (int) $user->id;
        $targetIsHead = $isHeadSecretary($user->tungkulin);

        if (array_key_exists('tungkulin', $validated)
            && $isHeadSecretary($validated['tungkulin'])
            && ! $targetIsHead) {
            throw ValidationException::withMessages([
                'tungkulin' => 'Use the confirmed Head Secretary transfer control to assign this role.',
            ]);
        }

        if ($targetIsHead && array_key_exists('tungkulin', $validated)
            && ! $isHeadSecretary($validated['tungkulin'])) {
            if ($isSelf) {
                throw ValidationException::withMessages([
                    'tungkulin' => 'You cannot demote yourself. Use the confirmed Head Secretary transfer control instead.',
                ]);
            }
        }

        if ($targetIsHead && array_key_exists('is_verified', $validated) && ! $validated['is_verified']) {
            throw ValidationException::withMessages([
                'is_verified' => 'A Head Secretary cannot be revoked. Demote the account to Secretary first.',
            ]);
        }

        $user->fill($validated)->save();
        if (array_key_exists('is_verified', $validated) && ! $user->is_verified) {
            $user->forceFill([
                'remember_token' => null,
                'remember_token_expires_at' => null,
            ])->save();
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'tungkulin' => $user->tungkulin,
                'is_verified' => (bool) $user->is_verified,
                'revoked_current_session' => $isSelf && ! $user->is_verified,
            ],
        ]);
    }

    public function transferHeadSecretary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:login_user,id'],
            'current_password' => ['required', 'string'],
            'confirmation' => ['required', 'accepted'],
        ]);
        $currentUserId = (int) $request->session()->get('login_user.id');
        if ((int) $validated['target_user_id'] === $currentUserId) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Choose another verified Secretary account.',
            ]);
        }

        $targetUser = DB::transaction(function () use ($validated, $currentUserId): User {
            $currentHead = User::query()
                ->whereKey($currentUserId)
                ->lockForUpdate()
                ->firstOrFail();
            $target = User::query()
                ->whereKey($validated['target_user_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $isHeadSecretary = static fn (?string $role): bool => in_array(
                mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $role) ?? '')),
                ['head secretary', 'secretary head'],
                true
            );

            if (! $isHeadSecretary($currentHead->tungkulin) || ! $currentHead->is_verified) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'Only the active Head Secretary can transfer this role.',
                ]);
            }
            $passwordMatches = str_starts_with((string) $currentHead->password, '$')
                ? Hash::check($validated['current_password'], $currentHead->password)
                : hash_equals((string) $currentHead->password, $validated['current_password']);
            if (! $passwordMatches) {
                throw ValidationException::withMessages([
                    'current_password' => 'The current password is incorrect.',
                ]);
            }
            if (! $target->is_verified || mb_strtolower(trim((string) $target->tungkulin)) !== 'secretary') {
                throw ValidationException::withMessages([
                    'target_user_id' => 'Choose a verified Secretary account for the transfer.',
                ]);
            }

            $currentHead->forceFill(['tungkulin' => 'Secretary'])->save();
            $target->forceFill(['tungkulin' => 'Head Secretary'])->save();

            return $target;
        });

        return response()->json([
            'message' => 'Head Secretary role transferred. Your account is now a Secretary.',
            'target_user' => $targetUser->only(['id', 'gmail', 'first_name', 'last_name']),
            'redirect_url' => route('home'),
        ]);
    }

    public function updatePermissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*.id' => ['required', 'integer', 'distinct'],
            'permissions.*.dashboard_allow' => ['required', 'boolean'],
            'permissions.*.form_generator_allow' => ['required', 'boolean'],
            'permissions.*.Delistment_allow' => ['required', 'boolean'],
            'permissions.*.Inquire_Allow' => ['required', 'boolean'],
            'permissions.*.tarheta_status_allow' => ['required', 'boolean'],
            'permissions.*.filter_bnh_helper_allow' => ['required', 'boolean'],
            'permissions.*.chatbot_records_allow' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated['permissions'] as $permission) {
                $user = User::query()
                    ->whereKey($permission['id'])
                    ->where('is_verified', 1)
                    ->first();

                if (! $user) {
                    throw ValidationException::withMessages([
                        'permissions' => 'A selected user is no longer verified. Reload the dashboard and try again.',
                    ]);
                }

                $userPermissions = [
                    'dashboard_allow' => $permission['dashboard_allow'],
                    'form_generator_allow' => $permission['form_generator_allow'],
                    'Delistment_allow' => $permission['Delistment_allow'],
                    'Inquire_Allow' => $permission['Inquire_Allow'],
                    'tarheta_status_allow' => $permission['tarheta_status_allow'],
                    'filter_bnh_helper_allow' => $permission['filter_bnh_helper_allow'],
                ];
                if (array_key_exists('chatbot_records_allow', $permission)) {
                    $userPermissions['chatbot_records_allow'] = $permission['chatbot_records_allow'];
                }
                $user->fill($userPermissions)->save();
            }
        });

        return response()->json(['message' => 'Permissions saved.']);
    }

    public function updateSignatories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'puroks' => ['present', 'array'],
            'puroks.*.id' => ['nullable', 'integer', 'exists:puroks,id'],
            'puroks.*.purok_number' => ['required', 'integer', 'min:1'],
            'puroks.*.purok_name' => ['required', 'string', 'max:40'],
            'puroks.*.secretary' => ['nullable', 'integer', 'exists:login_user,id'],
            'puroks.*.manggagawa' => ['nullable', 'string', 'max:255'],
            'puroks.*.ministerial_id' => ['nullable', 'string', 'max:50'],
            'puroks.*.grupo_start' => ['required', 'integer', 'min:0'],
            'puroks.*.grupo_end' => ['required', 'integer', 'min:0'],
            'puroks.*.full_purok' => ['required', 'boolean'],
        ]);

        foreach ($validated['puroks'] as $index => $purok) {
            if ($purok['grupo_end'] < $purok['grupo_start']) {
                throw ValidationException::withMessages([
                    "puroks.{$index}.grupo_end" => 'The end of the Sakop na Grupo range must not be less than its start.',
                ]);
            }
        }

        foreach ($validated['puroks'] as $index => $purok) {
            foreach ($validated['puroks'] as $otherIndex => $other) {
                if ($otherIndex <= $index
                    || (int) $purok['purok_number'] !== (int) $other['purok_number']) {
                    continue;
                }

                $completeIndex = $purok['full_purok']
                    ? $index
                    : ($other['full_purok'] ? $otherIndex : null);
                if ($completeIndex !== null) {
                    throw ValidationException::withMessages([
                        "puroks.{$completeIndex}.full_purok" => 'Complete Purok cannot be enabled when another configured Purok shares the same header number. Use non-overlapping Sakop na Grupo ranges instead.',
                    ]);
                }

                if ((int) $purok['grupo_start'] <= (int) $other['grupo_end']
                    && (int) $other['grupo_start'] <= (int) $purok['grupo_end']) {
                    throw ValidationException::withMessages([
                        "puroks.{$otherIndex}.grupo_start" => 'Sakop na Grupo ranges cannot overlap for Puroks sharing the same header number.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($validated): void {
            $savedIds = [];

            foreach ($validated['puroks'] as $attributes) {
                $id = $attributes['id'] ?? null;
                unset($attributes['id']);

                if ($id) {
                    $purok = Purok::query()->findOrFail($id);
                    $purok->fill($attributes)->save();
                } else {
                    $purok = Purok::query()->create($attributes);
                }

                $savedIds[] = $purok->id;
            }

            $query = Purok::query();
            if ($savedIds !== []) {
                $query->whereNotIn('id', $savedIds);
            }
            $query->delete();
        });

        return response()->json(['message' => 'Signatories saved.']);
    }

    public function updateGlobalVariables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:255'],
            'lcode' => ['nullable', 'string', 'max:50'],
            'district' => ['nullable', 'string', 'max:255'],
            'dcode' => ['nullable', 'string', 'max:50'],
            's_number' => ['nullable', 'string', 'max:50'],
            'pastor_destinado' => ['nullable', 'string', 'max:255'],
            'ksp_pd' => ['nullable', 'string', 'max:255'],
            'head_lsp' => ['nullable', 'string', 'max:255'],
            'kalihim_ms' => ['nullable', 'string', 'max:255'],
            'kalihim_uwp' => ['nullable', 'string', 'max:255'],
            'r401_use_purok_secretary' => ['sometimes', 'boolean'],
            'baptism_requirement' => ['required_if:dateofbaptism_requirement_boolean,1', 'nullable', 'date'],
            'entry_years_requirement' => ['required', 'integer', 'in:1,2,3'],
            'dateofbaptism_requirement_boolean' => ['required', 'boolean'],
        ]);

        $globalVariables = GlobalVariable::query()->first() ?? new GlobalVariable;
        $globalVariables->fill($validated)->save();

        return response()->json([
            'message' => 'Global variables saved.',
            'data' => $globalVariables,
        ]);
    }

    public function updateSecuritySettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'require_otp' => ['required', 'boolean'],
            'allow_forgot_password' => ['required', 'boolean'],
        ]);
        $globalVariables = GlobalVariable::query()->first() ?? new GlobalVariable;
        $globalVariables->fill($validated)->save();

        return response()->json([
            'message' => 'Security settings saved.',
            'data' => [
                'require_otp' => (bool) $globalVariables->require_otp,
                'allow_forgot_password' => (bool) $globalVariables->allow_forgot_password,
            ],
        ]);
    }

    public function generateBnhResetCode(Request $request): JsonResponse
    {
        $previousCodeHash = $request->session()->get('bnh_reset_confirmation.hash');
        $request->session()->forget('bnh_reset_confirmation');
        $checkedCount = Record::query()
            ->where(fn ($query) => $query->where('is_valid1', true)->orWhere('is_valid2', true))
            ->count();
        if ($checkedCount === 0) {
            return response()->json([
                'empty' => true,
                'message' => 'BNH CHECKS is currently Empty.',
            ], 409);
        }

        do {
            $code = Str::lower(Str::random(12));
            $codeHash = hash('sha256', $code);
        } while ($previousCodeHash && hash_equals((string) $previousCodeHash, $codeHash));
        $request->session()->put('bnh_reset_confirmation', [
            'hash' => $codeHash,
            'expires_at' => now()->addSeconds(45)->timestamp,
        ]);

        return response()->json([
            'code' => $code,
            'expires_in_seconds' => 45,
            'message' => 'Type the generated code within 45 seconds to reset the BNH checks.',
        ]);
    }

    public function resetBnhHelperChecks(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'size:12', 'regex:/^[a-z0-9]{12}$/'],
        ]);

        $affected = DB::transaction(function () use ($request): int {
            $confirmation = $request->session()->pull('bnh_reset_confirmation');
            if (
                ! is_array($confirmation)
                || (int) ($confirmation['expires_at'] ?? 0) <= now()->timestamp
                || ! hash_equals((string) ($confirmation['hash'] ?? ''), hash('sha256', $request->string('confirmation')->toString()))
            ) {
                throw ValidationException::withMessages([
                    'confirmation' => ['The generated code is invalid or expired. Generate a new code and try again.'],
                ]);
            }

            $count = Record::query()
                ->where(fn ($query) => $query->where('is_valid1', true)->orWhere('is_valid2', true))
                ->count();
            if ($count === 0) {
                return 0;
            }
            Record::query()
                ->where(fn ($query) => $query->where('is_valid1', true)->orWhere('is_valid2', true))
                ->orderBy('id')
                ->chunkById(100, function ($records): void {
                    foreach ($records as $record) {
                        $record->is_valid1 = false;
                        $record->is_valid2 = false;
                        $record->save();
                    }
                });
            DB::table('system_maintenance_audits')->insert([
                'action' => 'reset_bnh_helper_checks',
                'affected_count' => $count,
                'changed_by' => $this->changedBy($request),
                'changed_at' => now(),
            ]);

            return $count;
        });

        if ($affected === 0) {
            return response()->json(['empty' => true, 'message' => 'BNH CHECKS is currently Empty.'], 409);
        }

        return response()->json(['message' => "✓ BNH helper checks cleared for {$affected} record".($affected === 1 ? '' : 's').'.', 'affected' => $affected]);
    }

    private function changedBy(Request $request): ?string
    {
        $gmail = trim((string) $request->session()->get('login_user.gmail', ''));

        return $gmail !== '' ? $gmail : null;
    }
}
