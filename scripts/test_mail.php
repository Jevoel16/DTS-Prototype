<?php

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Parse .env file
$env = parse_ini_file('.env');

if (!$env) {
    die("Failed to parse .env file.\n");
}

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = $env['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $env['SMTP_USERNAME'];
    $mail->Password   = $env['SMTP_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $env['SMTP_PORT'];

    // Recipients
    $mail->setFrom($env['SMTP_FROM_EMAIL'], $env['SMTP_FROM_NAME']);
    // Add the user's personal email as requested
    $mail->addAddress('jevoel.orbilla@g.msuiit.edu.ph', ''); 

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from DTS Prototype';
    $mail->Body    = 'Hello! This is a test email sent using <b>PHPMailer</b> to confirm your SMTP setup works.';
    $mail->AltBody = 'Hello! This is a test email sent using PHPMailer to confirm your SMTP setup works.';

    $mail->send();
    echo "\n\nEmail has been sent successfully to mirkolouis33@gmail.com!\n";
} catch (Exception $e) {
    echo "\n\nMessage could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}
