<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\NotificationLog;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function appointmentConfirmation(Appointment $appointment): void
    {
        $appointment->loadMissing(['patient', 'doctor']);
        $message = sprintf(
            'Hello %s, your appointment with %s is scheduled for %s at %s. Status: %s.',
            $appointment->patient->full_name,
            $appointment->doctor?->full_name ?? 'your doctor',
            $appointment->appointment_date,
            substr($appointment->appointment_time, 0, 5),
            $appointment->status
        );

        $this->sendSms(
            $appointment->patient->phone,
            $message,
            'appointment_confirmation',
            "appointment:{$appointment->id}:sms",
            $appointment
        );

        if ($appointment->patient->email) {
            $this->sendEmail(
                $appointment->patient->email,
                'Appointment confirmation',
                $message,
                'appointment_confirmation',
                "appointment:{$appointment->id}:email",
                $appointment
            );
        }
    }

    public function paymentConfirmation(Payment $payment): void
    {
        $payment->loadMissing('patient');
        $message = sprintf(
            'Payment received: $%s. Remaining balance: $%s. Reference: %s.',
            number_format((float) $payment->paid_amount, 2),
            number_format((float) $payment->remaining_amount, 2),
            $payment->reference_number
        );
        $this->sendSms(
            $payment->patient->phone,
            $message,
            'payment_confirmation',
            "payment:{$payment->id}:sms",
            $payment
        );
    }

    public function sendSms(string $recipient, string $message, string $type, string $key, ?Model $notifiable = null): void
    {
        if (NotificationLog::where('idempotency_key', $key)->exists()) {
            return;
        }

        $log = $this->makeLog('sms', $recipient, null, $message, $type, $key, $notifiable);
        if (config('services.sms.driver') !== 'http' || !config('services.sms.endpoint')) {
            $log->update(['status' => 'Skipped', 'error_message' => 'SMS provider is not configured.']);
            return;
        }

        try {
            $response = Http::asJson()
                ->withToken((string) config('services.sms.token'))
                ->timeout(15)
                ->post(config('services.sms.endpoint'), [
                    'to' => $recipient,
                    'message' => $message,
                    'sender_id' => config('services.sms.sender_id'),
                ]);
            $response->throw();
            $log->update([
                'status' => 'Sent',
                'provider_reference' => data_get($response->json(), 'id'),
            ]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'Failed', 'error_message' => $e->getMessage()]);
            Log::warning('SMS notification failed', ['notification_log_id' => $log->id, 'message' => $e->getMessage()]);
        }
    }

    public function sendEmail(string $recipient, string $subject, string $message, string $type, string $key, ?Model $notifiable = null): void
    {
        if (NotificationLog::where('idempotency_key', $key)->exists()) {
            return;
        }
        $log = $this->makeLog('email', $recipient, $subject, $message, $type, $key, $notifiable);
        try {
            Mail::raw($message, fn ($mail) => $mail->to($recipient)->subject($subject));
            $log->update(['status' => 'Sent']);
        } catch (\Throwable $e) {
            $log->update(['status' => 'Failed', 'error_message' => $e->getMessage()]);
            Log::warning('Email notification failed', ['notification_log_id' => $log->id, 'message' => $e->getMessage()]);
        }
    }

    private function makeLog(string $channel, string $recipient, ?string $subject, string $message, string $type, string $key, ?Model $notifiable): NotificationLog
    {
        return NotificationLog::create([
            'channel' => $channel,
            'notification_type' => $type,
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $message,
            'status' => 'Pending',
            'idempotency_key' => $key,
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
        ]);
    }
}
