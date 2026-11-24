<?php
/**
 * Lemon Tree Law — contact form handler
 * Runs on Hostinger's PHP. No third-party service, no account, no monthly cap.
 *
 * Delivers to the address in $TO below. Change that line and nothing else.
 */

/* ─────────────────────────────────────────────────────────────────────────
   WHERE LEADS GO — edit this line and nothing else.

   Currently pointed at a Gmail address because mail sent from Hostinger to
   info@lemontreelaw.com is being quarantined: the domain's SPF record does not
   authorise Hostinger's servers, so the receiving mail host treats it as
   spoofed. Gmail is far more tolerant of this and will deliver (possibly to
   spam the first time — mark it "Not spam" once and it will stick).

   You can send to more than one address with a comma:
       $TO = 'michael@gmail.com, info@lemontreelaw.com';

   Once SPF is fixed, put info@lemontreelaw.com back on its own.
   ───────────────────────────────────────────────────────────────────────── */
$TO         = 'makankhods@gmail.com';

$SITE       = 'lemontreelaw.com';
$FROM_LOCAL = 'noreply';           // becomes noreply@lemontreelaw.com

// ---- only accept POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.html');
    exit;
}

$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
);

function respond($ok, $msg, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code($ok ? 200 : 400);
        echo json_encode(['ok' => $ok, 'message' => $msg]);
    } else {
        // relative redirect so this works from a staging subfolder too
        $back = 'index.html?' . ($ok ? 'sent=1' : 'error=1') . '#contact';
        header('Location: ' . $back);
    }
    exit;
}

// ---- honeypot: bots fill every field, humans never see this one ----
if (!empty($_POST['company_website'])) {
    respond(true, 'Thanks.', $isAjax);   // silent success, nothing sent
}

// ---- collect + validate ----
$name    = trim($_POST['name']        ?? '');
$phone   = trim($_POST['phone']       ?? '');
$email   = trim($_POST['email']       ?? '');
$detail  = trim($_POST['case_detail'] ?? '');
$consent = isset($_POST['sms_consent']);

$errors = [];
if ($name === '')                                    $errors[] = 'name';
if ($phone === '')                                   $errors[] = 'phone';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if ($detail === '')                                  $errors[] = 'case detail';

if ($errors) {
    respond(false, 'Please check these fields: ' . implode(', ', $errors), $isAjax);
}

// strip anything that could be used for header injection
$clean = function ($v) { return str_replace(["\r", "\n", "%0a", "%0d"], ' ', $v); };
$name  = $clean($name);
$phone = $clean($phone);
$email = $clean($email);

// ---- compose ----
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$when = date('D, j M Y, g:i a T');

$subject = "New case inquiry — {$name}";

$body  = "New case inquiry from lemontreelaw.com\n";
$body .= str_repeat('=', 46) . "\n\n";
$body .= "Name:    {$name}\n";
$body .= "Phone:   {$phone}\n";
$body .= "Email:   {$email}\n\n";
$body .= "Case detail:\n{$detail}\n\n";
$body .= str_repeat('-', 46) . "\n";
$body .= "SMS consent: " . ($consent ? 'yes' : 'NO') . "\n";
$body .= "Submitted:   {$when}\n";
$body .= "IP:          {$ip}\n";

$headers   = [];
$headers[] = 'From: Lemon Tree Law Website <' . $FROM_LOCAL . '@' . $SITE . '>';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$sent = @mail($TO, $subject, $body, implode("\r\n", $headers), '-f' . $FROM_LOCAL . '@' . $SITE);

if ($sent) {
    respond(true, "Message received. Michael reviews these personally and will follow up by phone or email.", $isAjax);
}

// A silent failure on an intake form costs real clients, so log the reason.
@file_put_contents(__DIR__ . '/form-errors.log',
    date('c') . "  mail() refused the message  [from {$email}]\n", FILE_APPEND);

respond(false, "That didn't send. Please call (833) 566-8733 or email info@lemontreelaw.com and it'll get handled.", $isAjax);
