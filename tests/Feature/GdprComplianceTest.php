<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\GdprRequestStatusMail;
use App\Models\GdprRequest;
use App\Models\User;
use App\Services\Gdpr\GdprErasureService;
use App\Services\Gdpr\GdprExportService;
use App\Services\Gdpr\GdprNotificationService;
use App\Services\Gdpr\GdprRetentionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GdprComplianceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.driver' => 'array',
            'cache.default' => 'array',
            'mail.from.address' => 'admin@example.test',
            'nntmux_settings.store_user_ip' => false,
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_export_includes_retained_payment_and_audit_records(): void
    {
        Mail::fake();
        Storage::fake('local');
        $user = $this->createUser();

        DB::table('payments')->insert($this->paymentRow($user->email, $user->username, 'payment-1'));
        DB::table('user_activities')->insert($this->activityRow($user->id, $user->username, ['email' => $user->email]));

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'requester_username' => $user->username,
            'requester_email' => $user->email,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_PROCESSING,
        ]);

        $result = app(GdprExportService::class)->generate($user, $gdprRequest);

        Storage::disk('local')->assertExists($result['path']);
        $payload = json_decode(Storage::disk('local')->get($result['path']), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($user->email, $payload['subject']['email']);
        $this->assertArrayNotHasKey('password', $payload['account']);
        $this->assertArrayNotHasKey('api_token', $payload['account']);
        $this->assertCount(1, $payload['retained_payment_records']);
        $this->assertSame('payment-1', $payload['retained_payment_records'][0]['payment_id']);
        $this->assertNotEmpty($payload['retained_audit_records']);
        $this->assertSame(GdprRequest::STATUS_COMPLETED, $gdprRequest->fresh()->status);
        Mail::assertSent(GdprRequestStatusMail::class, fn (GdprRequestStatusMail $mail): bool => $mail->hasTo($user->email) && $mail->heading === 'Your data export is ready');
    }

    public function test_erasure_anonymizes_account_removes_usage_and_retains_payment_data(): void
    {
        $user = $this->createUser();
        $originalEmail = $user->email;
        $originalUsername = $user->username;

        DB::table('payments')->insert($this->paymentRow($originalEmail, $originalUsername, 'payment-2'));
        DB::table('user_requests')->insert(['users_id' => $user->id, 'request' => 'search=linux', 'hosthash' => 'abc', 'timestamp' => now()]);
        DB::table('user_downloads')->insert(['users_id' => $user->id, 'releases_id' => 123, 'hosthash' => 'def', 'timestamp' => now()]);
        DB::table('release_comments')->insert([
            'users_id' => $user->id,
            'releases_id' => 1,
            'text' => 'hello',
            'username' => $originalUsername,
            'host' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_activities')->insert($this->activityRow($user->id, $originalUsername, ['email' => $originalEmail, 'ip_address' => '127.0.0.1']));

        app(GdprErasureService::class)->eraseForAccountDeletion($user, $user);

        $deletedUser = User::withTrashed()->findOrFail($user->id);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame('deleted_user_'.$user->id, $deletedUser->username);
        $this->assertSame('deleted-user-'.$user->id.'@deleted.invalid', $deletedUser->email);
        $this->assertDatabaseMissing('user_requests', ['users_id' => $user->id]);
        $this->assertDatabaseMissing('user_downloads', ['users_id' => $user->id]);
        $this->assertDatabaseMissing('payments', ['email' => $originalEmail, 'payment_id' => 'payment-2']);
        $this->assertDatabaseMissing('payments', ['username' => $originalUsername, 'payment_id' => 'payment-2']);
        $this->assertDatabaseHas('payments', [
            'email' => 'deleted-user-'.$user->id.'@deleted.invalid',
            'username' => 'deleted_user_'.$user->id,
            'payment_id' => 'payment-2',
        ]);
        $this->assertDatabaseHas('release_comments', ['users_id' => $user->id, 'username' => 'deleted_user_'.$user->id, 'host' => null]);

        $activity = DB::table('user_activities')->where('activity_type', 'registered')->first();
        $metadata = json_decode((string) $activity->metadata, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('deleted_user_'.$user->id, $activity->username);
        $this->assertArrayNotHasKey('email', $metadata);
        $this->assertArrayNotHasKey('ip_address', $metadata);
        $this->assertTrue($metadata['gdpr_minimized']);
    }

    public function test_erasure_request_is_completed_and_audited(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'requester_username' => $user->username,
            'requester_email' => $user->email,
            'type' => GdprRequest::TYPE_ERASURE,
            'status' => GdprRequest::STATUS_PROCESSING,
        ]);

        app(GdprErasureService::class)->eraseForAccountDeletion($user, $user, $gdprRequest);

        $freshRequest = $gdprRequest->fresh();
        $this->assertSame(GdprRequest::STATUS_COMPLETED, $freshRequest->status);
        $this->assertSame('completed', $freshRequest->response_payload['erasure']);
        $this->assertSame('payments', $freshRequest->response_payload['retained_records'][0]['table']);
        $this->assertSame('retained_anonymized', $freshRequest->response_payload['retained_records'][0]['erasure_action']);
        $this->assertDatabaseHas('gdpr_audit_logs', [
            'gdpr_request_id' => $gdprRequest->id,
            'user_id' => $user->id,
            'event' => 'erasure_soft_delete',
        ]);
        Mail::assertSent(GdprRequestStatusMail::class, fn (GdprRequestStatusMail $mail): bool => $mail->hasTo($user->email) && $mail->heading === 'Your account erasure is complete');
    }

    public function test_erasure_submission_notifies_requester_and_admin(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'requester_username' => $user->username,
            'requester_email' => $user->email,
            'type' => GdprRequest::TYPE_ERASURE,
            'status' => GdprRequest::STATUS_PENDING,
        ]);

        app(GdprNotificationService::class)->requestSubmitted($gdprRequest);

        Mail::assertSent(GdprRequestStatusMail::class, fn (GdprRequestStatusMail $mail): bool => $mail->hasTo($user->email) && $mail->heading === 'We received your GDPR request');
        Mail::assertSent(GdprRequestStatusMail::class, fn (GdprRequestStatusMail $mail): bool => $mail->hasTo('admin@example.test') && $mail->heading === 'New GDPR request submitted');
    }

    public function test_expired_exports_are_purged_but_request_record_is_retained(): void
    {
        Storage::fake('local');
        $user = $this->createUser();
        $path = 'gdpr/exports/expired-export.json';
        Storage::disk('local')->put($path, '{"expired":true}');

        $gdprRequest = GdprRequest::create([
            'user_id' => $user->id,
            'requester_username' => $user->username,
            'requester_email' => $user->email,
            'type' => GdprRequest::TYPE_EXPORT,
            'status' => GdprRequest::STATUS_COMPLETED,
            'export_disk' => 'local',
            'export_path' => $path,
            'export_expires_at' => now()->subMinute(),
            'completed_at' => now()->subDay(),
        ]);

        $purged = app(GdprRetentionService::class)->purgeExpiredExports();

        $this->assertSame(1, $purged);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('gdpr_requests', [
            'id' => $gdprRequest->id,
            'status' => GdprRequest::STATUS_COMPLETED,
            'export_path' => null,
            'export_disk' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(string $email, string $username, string $paymentId): array
    {
        return [
            'email' => $email,
            'username' => $username,
            'item_description' => 'VIP role',
            'order_id' => 'order-'.$paymentId,
            'payment_id' => $paymentId,
            'payment_status' => 'Settled',
            'invoice_status' => 'Settled',
            'invoice_amount' => '10.00',
            'payment_method' => 'BTC',
            'payment_value' => '10.00',
            'webhook_id' => 'webhook-'.$paymentId,
            'invoice_id' => 'invoice-'.$paymentId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function activityRow(int $userId, string $username, array $metadata): array
    {
        return [
            'user_id' => $userId,
            'username' => $username,
            'activity_type' => 'registered',
            'description' => 'Registration audit',
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'is_permanent' => false,
            'created_at' => now(),
        ];
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->integer('rate_limit')->default(60);
            $table->boolean('isdefault')->default(false);
            $table->unsignedInteger('defaultinvites')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('name')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('host')->default('');
            $table->unsignedInteger('roles_id')->default(1);
            $table->unsignedInteger('invites')->default(0);
            $table->text('notes')->nullable();
            $table->integer('rate_limit')->default(60);
            $table->string('api_token')->nullable()->unique();
            $table->string('resetguid')->nullable();
            $table->boolean('verified')->default(true);
            $table->boolean('can_post')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('session_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('deleted_by')->nullable();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedInteger('role_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_activities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('username');
            $table->string('activity_type', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('email');
            $table->string('username');
            $table->string('item_description');
            $table->string('order_id');
            $table->string('payment_id');
            $table->string('payment_status');
            $table->string('invoice_status')->nullable();
            $table->string('invoice_amount');
            $table->string('payment_method');
            $table->string('payment_value');
            $table->string('webhook_id');
            $table->string('invoice_id');
        });

        Schema::create('user_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->string('request')->default('');
            $table->string('hosthash')->default('');
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('user_downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id')->default(0);
            $table->string('hosthash')->default('');
            $table->timestamp('timestamp')->nullable();
        });

        Schema::create('release_comments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id');
            $table->text('text');
            $table->string('username');
            $table->string('host')->nullable();
            $table->timestamps();
        });

        Schema::create('gdpr_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('requester_username')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('export_disk')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamp('export_expires_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gdpr_consents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('consent_type');
            $table->string('status')->default('granted');
            $table->timestamps();
        });

        Schema::create('gdpr_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gdpr_request_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event', 64);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::table('settings')->insert([
            ['name' => 'title', 'value' => 'NNTmux Test'],
            ['name' => 'home_link', 'value' => '/'],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ]);
    }

    private function createUser(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            ['rate_limit' => 60, 'isdefault' => true, 'defaultinvites' => 1]
        );

        $user = User::query()->create([
            'username' => 'user_'.Str::random(8),
            'email' => Str::random(12).'@example.test',
            'password' => bcrypt('password'),
            'roles_id' => $role->id,
            'rate_limit' => 60,
            'api_token' => Str::random(32),
            'verified' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
