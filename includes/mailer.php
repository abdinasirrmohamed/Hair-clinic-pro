<?php
/**
 * Mock Mailer and SMS utility for demonstration purposes.
 * In a real production environment, this would integrate with PHPMailer or Twilio.
 */

function send_mock_email($to, $subject, $body) {
    // Simulate sending email by logging it to a local file
    $log_file = __DIR__ . '/../mock_emails.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] EMAIL TO: $to | SUBJECT: $subject | BODY: $body\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    return true;
}

function send_mock_sms($phone, $message) {
    // Simulate sending SMS by logging it to a local file
    $log_file = __DIR__ . '/../mock_sms.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] SMS TO: $phone | MESSAGE: $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    return true;
}
