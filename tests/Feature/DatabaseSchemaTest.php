<?php

namespace Tests\Feature;

use App\Models\Purok;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_and_migrations_use_the_application_schema(): void
    {
        $this->assertSame('login_user', (new User)->getTable());
        $this->assertFalse((new User)->usesTimestamps());
        $this->assertSame('records', (new Record)->getTable());
        $this->assertFalse((new Record)->usesTimestamps());
        $this->assertSame('puroks', (new Purok)->getTable());
        $this->assertSame('id', (new Purok)->getKeyName());
        $this->assertTrue((new Purok)->getIncrementing());

        $this->assertTrue(Schema::hasColumns('login_user', [
            'gmail',
            'password',
            'tungkulin',
            'head_secretary_slot',
            'is_verified',
            'remember_token',
            'remember_token_expires_at',
            'first_name',
            'last_name',
            'gender',
            'profile_picture',
            'dark_mode',
            'dashboard_allow',
            'form_generator_allow',
            'Delistment_allow',
            'Inquire_Allow',
            'filter_bnh_helper_allow',
            'chatbot_records_allow',
        ]));
        $this->assertTrue(Schema::hasColumns('puroks', [
            'id',
            'purok_number',
            'purok_name',
        ]));
        $purokColumns = Schema::getColumnListing('puroks');
        $this->assertSame(['id', 'purok_number', 'purok_name'], array_slice($purokColumns, 0, 3));
        $this->assertFalse(collect(Schema::getIndexes('puroks'))->contains(
            fn (array $index): bool => $index['unique']
                && ! $index['primary']
                && $index['columns'] === ['purok_number']
        ));
        $this->assertFalse(Schema::hasTable('members'));
        $this->assertTrue(Schema::hasColumns('records', [
            'id',
            'full_name',
            'registry',
            'gender',
            'purok',
            'grupo',
            'kapisanan',
            'date_of_baptism',
            'evangelist',
            'date_of_entry',
            'status',
            'tarheta_status',
            'monthly_code',
            'date_of_birth',
            'is_married',
            'is_valid1',
            'is_valid2',
            'r2_10_endorsement',
            'reason_of_r2_10_endorsement',
            'log_section',
        ]));
        $this->assertFalse(Schema::hasColumn('records', 'r2_10_eligibility'));
        $this->assertTrue(Schema::hasColumns('records', ['filing_status', 'filing_number']));
        $this->assertFalse(Schema::hasColumn('puroks', 'created_at'));
        $this->assertFalse(Schema::hasColumn('puroks', 'updated_at'));
        $this->assertTrue(Schema::hasColumns('monthly_record_movement_histories', [
            'previous_purok',
            'previous_grupo',
            'destination_status',
        ]));
        $this->assertFalse(Schema::hasColumn('monthly_record_movement_histories', 'purok'));
        $this->assertFalse(Schema::hasColumn('monthly_record_movement_histories', 'grupo'));
        $this->assertFalse(Schema::hasColumn('monthly_record_movement_histories', 'destination_purok'));
        $this->assertFalse(Schema::hasColumn('monthly_record_movement_histories', 'destination_grupo'));
        $this->assertFalse(Schema::hasTable('monthly_record_movement_notes'));
        $this->assertFalse(Schema::hasColumn('records', 'reason_of_delistment'));
        $this->assertFalse(Schema::hasColumn('records', 'r2_10_endorsement_reason'));
        $this->assertSame('varchar', Schema::getColumnType('puroks', 'manggagawa'));
        $this->assertTrue(Schema::hasColumns('global_variables', [
            'id',
            'locale',
            'lcode',
            'district',
            'dcode',
            's_number',
            'pastor_destinado',
            'ksp_pd',
            'head_lsp',
            'kalihim_ms',
            'kalihim_uwp',
            'baptism_requirement',
            'entry_years_requirement',
            'dateofbaptism_requirement_boolean',
            'r401_use_purok_secretary',
        ]));
        $this->assertSame('date', Schema::getColumnType('global_variables', 'baptism_requirement'));
        $this->assertSame('integer', Schema::getColumnType('global_variables', 'entry_years_requirement'));
        $this->assertContains(Schema::getColumnType('global_variables', 'dateofbaptism_requirement_boolean'), ['boolean', 'tinyint']);
        $this->assertFalse(Schema::hasColumn('global_variables', 'dateofentryrequirement_boolean'));
        $this->assertTrue(Schema::hasTable('delistment_histories'));
        $this->assertEqualsCanonicalizing([
            'id',
            'record_id',
            'delisted_by_user_id',
            'delisted_by',
            'reason',
            'delisted_at',
        ], Schema::getColumnListing('delistment_histories'));
        $this->assertTrue(Schema::hasColumns('record_audit_logs', [
            'id',
            'record_id',
            'record_name',
            'actor_email',
            'action',
            'summary',
            'changes',
            'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns('monthly_record_snapshots', [
            'id',
            'month',
            'purok_number',
            'purok_id',
            'ms_count',
            'uwp_count',
            'mk_count',
            'movement_counts',
            'created_at',
            'updated_at',
        ]));
        $this->assertDatabaseCount('monthly_record_snapshots', 60);
        $this->assertTrue(Schema::hasColumns('monthly_movement_sessions', [
            'id',
            'active_key',
            'status',
            'period_month',
            'started_at',
            'completed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('permanent_history', [
            'session_id',
            'period_month',
            'record_id',
            'full_name',
            'monthly_code',
            'destination_description',
            'changed_by',
            'changed_at',
            'date_of_baptism',
            'evangelist',
        ]));

        $user = new User;
        $purok = new Purok;

        $this->assertInstanceOf(HasMany::class, $user->secretaryPuroks());
        $this->assertInstanceOf(BelongsTo::class, $purok->secretaryUser());
        $this->assertSame('login_user', $purok->secretaryUser()->getRelated()->getTable());
    }

    public function test_database_rejects_a_second_head_secretary_role(): void
    {
        User::query()->create([
            'gmail' => 'first-head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Head Secretary',
            'is_verified' => true,
        ]);

        $this->expectException(QueryException::class);
        User::query()->create([
            'gmail' => 'second-head@example.test',
            'password' => 'secret-password',
            'tungkulin' => 'Secretary Head',
            'is_verified' => true,
        ]);
    }
}
