<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use ImranDevBd\AttendanceHub\DTOs\AttendancePunch;
use ImranDevBd\AttendanceHub\DTOs\DeviceConnection;
use ImranDevBd\AttendanceHub\Enums\PunchType;
use ImranDevBd\AttendanceHub\Enums\VerifyMode;
use ImranDevBd\AttendanceHub\Facades\AttendanceHub;

class AttendanceImport
{
    public function simulate(Employee $employee): int
    {
        app(Modules::class)->require('attendance');
        $time = now()->startOfDay()->addHours(3);
        $punches = [new AttendancePunch(deviceUserId: (string) $employee->id, punchedAt: $time, verifyMode: VerifyMode::FINGERPRINT, punchType: PunchType::CHECK_IN, deviceId: 'simulation'), new AttendancePunch(deviceUserId: (string) $employee->id, punchedAt: $time->copy()->addHours(8), verifyMode: VerifyMode::FINGERPRINT, punchType: PunchType::CHECK_OUT, deviceId: 'simulation')];
        $driver = AttendanceHub::fake($punches);
        $driver->connect(new DeviceConnection(ip: '127.0.0.1', port: 4370));
        $count = 0;
        foreach ($driver->pullAttendanceLogs() as $punch) {
            $type = $punch->punchType === PunchType::CHECK_IN ? 'in' : 'out';
            $key = 'simulation:'.$employee->id.':'.$punch->punchedAt->toIso8601String().':'.$type;
            $event = Attendance::firstOrCreate(['event_id' => $key], ['employee_id' => $employee->id, 'type' => $type, 'occurred_at' => $punch->punchedAt]);
            if ($event->wasRecentlyCreated) {
                $count++;
            }
        }
        Audit::record('attendance.simulated', (string) $employee->id, ['new_events' => $count]);

        return $count;
    }
}
