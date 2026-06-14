<?php

namespace Mxnwire\AuditLog\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Mxnwire\AuditLog\Http\Controllers\AuditLogController;
use Mxnwire\AuditLog\Tests\TestCase;

/** Minimal causer model with a hidden column, used to exercise causer trimming. */
class CauserUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}

class AuditLogCauserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('locale')->default('en');
            $table->timestamps();
        });
    }

    /** Log one activity caused by a freshly created user, then return the data() payload. */
    private function logsAfterCausedBy(array $attributes): array
    {
        $user = CauserUser::create($attributes);

        activity('user')->causedBy($user)->log('user.login');

        $response = $this->app->make(AuditLogController::class)->data(new Request());

        $this->assertInstanceOf(JsonResponse::class, $response);

        return $response->getData(true)['data'];
    }

    public function test_causer_is_trimmed_to_configured_attributes(): void
    {
        config(['audit-log.causer_attributes' => ['id', 'name', 'email']]);

        $causer = $this->logsAfterCausedBy([
            'name'     => 'Jane',
            'email'    => 'jane@example.com',
            'password' => 'secret',
            'locale'   => 'bn',
        ])[0]['causer'];

        $this->assertSame(['id', 'name', 'email'], array_keys($causer));
        $this->assertSame('Jane', $causer['name']);
        $this->assertArrayNotHasKey('locale', $causer);
    }

    public function test_hidden_attribute_is_never_exposed_even_if_listed(): void
    {
        config(['audit-log.causer_attributes' => ['id', 'name', 'password']]);

        $causer = $this->logsAfterCausedBy([
            'name'     => 'Jane',
            'email'    => 'jane@example.com',
            'password' => 'secret',
        ])[0]['causer'];

        // password is in $hidden on the model, so listing it cannot leak it.
        $this->assertArrayNotHasKey('password', $causer);
        $this->assertSame(['id', 'name'], array_keys($causer));
    }

    public function test_null_config_leaves_full_causer(): void
    {
        config(['audit-log.causer_attributes' => null]);

        $causer = $this->logsAfterCausedBy([
            'name'     => 'Jane',
            'email'    => 'jane@example.com',
            'password' => 'secret',
            'locale'   => 'bn',
        ])[0]['causer'];

        // Full model (minus its own $hidden) — locale survives, password does not.
        $this->assertArrayHasKey('locale', $causer);
        $this->assertArrayNotHasKey('password', $causer);
    }
}
