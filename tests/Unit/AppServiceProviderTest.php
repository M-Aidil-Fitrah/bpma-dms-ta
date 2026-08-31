<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use LogicException;
use Tests\TestCase;

final class AppServiceProviderTest extends TestCase
{
    public function test_produksi_menolak_debug_yang_aktif(): void
    {
        $environmentAsli = $this->app->environment();
        $debugAsli = config('app.debug');
        $this->app->instance('env', 'production');
        config()->set('app.debug', true);

        try {
            (new AppServiceProvider($this->app))->boot();
            $this->fail('Bootstrap produksi harus menolak APP_DEBUG=true.');
        } catch (LogicException $exception) {
            $this->assertSame('APP_DEBUG wajib false saat APP_ENV=production.', $exception->getMessage());
        } finally {
            $this->app->instance('env', $environmentAsli);
            config()->set('app.debug', $debugAsli);
        }
    }
}
