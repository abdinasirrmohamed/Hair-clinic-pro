<?php

namespace App\Services;

use App\Models\{Appointment, Doctor, DoctorBlockedDate, DoctorSchedule};
use Carbon\Carbon;

class AppointmentAvailability
{
    public static function check(?int $doctorId, string $date, string $time, ?int $excludeId = null): array
    {
        if (!Doctor::whereKey($doctorId)->where('status', 'Active')->exists()) return [false, 'Select an active doctor.'];
        if (DoctorBlockedDate::where('doctor_id', $doctorId)->whereDate('block_date', $date)->exists()) return [false, 'The doctor is unavailable on this date.'];
        $slot = Carbon::parse($date.' '.$time);
        if ($slot->isPast()) return [false, 'Appointment time must be in the future.'];
        $schedules = DoctorSchedule::where('doctor_id', $doctorId)->where('day_of_week', $slot->format('l'))->where('is_working', true)->get();
        $fits = $schedules->contains(function ($schedule) use ($date, $slot) {
            $start = Carbon::parse($date.' '.$schedule->start_time);
            $end = Carbon::parse($date.' '.$schedule->end_time);
            $minutes = max(5, (int) $schedule->slot_minutes);
            return $slot->gte($start) && $slot->copy()->addMinutes($minutes)->lte($end)
                && ((int) $start->diffInSeconds($slot)) % ($minutes * 60) === 0;
        });
        if (!$fits) return [false, 'Select a valid appointment slot within the doctor working schedule.'];
        $booked = Appointment::where('doctor_id', $doctorId)->whereDate('appointment_date', $date)
            ->whereTime('appointment_time', Carbon::parse($time)->format('H:i:s'))->whereIn('status', ['Pending', 'Approved'])
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))->exists();
        return $booked ? [false, 'Slot is already booked.'] : [true, 'Available'];
    }
}
