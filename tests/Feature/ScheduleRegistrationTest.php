<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class ScheduleRegistrationTest extends TestCase
{
    public function test_job_pemeliharaan_harian_terdaftar_dengan_waktu_dan_mutex_yang_tepat(): void
    {
        $schedule = app(Schedule::class);

        $this->assertSchedule($schedule, 'documents:update-expired-status', '5 0 * * *');
        $this->assertSchedule($schedule, 'documents:purge-trash', '20 0 * * *');
        $this->assertSchedule($schedule, 'activitylog:clean --force', '40 0 * * *');
    }

    private function assertSchedule(Schedule $schedule, string $command, string $expression): void
    {
        /** @var Event|null $event */
        $event = collect($schedule->events())
            ->first(fn (Event $event): bool => str_contains($event->command, $command));

        $this->assertNotNull($event, "Command {$command} harus terjadwal.");
        $this->assertSame($expression, $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
