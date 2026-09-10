<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Your verification code</title>
    <style>
        /* Client resets */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; min-width: 100%; }
        table { border-collapse: collapse !important; }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Inter',Helvetica,Arial,sans-serif;">

<!-- Outer wrapper -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:40px 16px 48px;">
<tr><td align="center">

    <!-- Email card — max 520px -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

        <!-- ── Logo header ── -->
        <tr>
            <td style="padding-bottom:20px;" align="center">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background-color:#091929;border-radius:10px;padding:10px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right:8px;">
                                        <!-- CSS medical cross approximation via borders -->
                                        <div style="position:relative;width:18px;height:18px;display:inline-block;">
                                            <div style="background:#00B4D8;width:6px;height:18px;position:absolute;left:6px;top:0;border-radius:1px;"></div>
                                            <div style="background:#00B4D8;width:18px;height:6px;position:absolute;left:0;top:6px;border-radius:1px;"></div>
                                        </div>
                                    </td>
                                    <td style="font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#ffffff;white-space:nowrap;vertical-align:middle;">
                                        DOCTOR <span style="color:#00B4D8;">PORTAL</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- ── Card body ── -->
        <tr>
            <td style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(9,25,41,.08);">

                <!-- Dark header band -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background-color:#091929;padding:32px 40px 28px;border-radius:16px 16px 0 0;">
                            <!-- Shield icon (SVG inline) -->
                            <div align="center" style="margin-bottom:16px;">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto;">
                                    <circle cx="24" cy="24" r="24" fill="rgba(0,180,216,0.12)"/>
                                    <path d="M24 11L13 15.5V24C13 30.075 17.85 35.76 24 37C30.15 35.76 35 30.075 35 24V15.5L24 11Z" fill="none" stroke="#00B4D8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M20 24L22.5 26.5L28 21" stroke="#00B4D8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <p style="margin:0;font-size:11px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:#00B4D8;text-align:center;opacity:.9;">Security Verification</p>
                            <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;color:#ffffff;text-align:center;letter-spacing:-.02em;line-height:1.3;">Your one-time code</h1>
                        </td>
                    </tr>
                </table>

                <!-- Body content -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:32px 40px 8px;">
                            <p style="margin:0 0 6px;font-size:15px;font-weight:600;color:#0d1b2a;line-height:1.4;">Hi {{ $recipientName }},</p>
                            <p style="margin:0 0 28px;font-size:14px;color:#566e7d;line-height:1.65;">
                                Use the code below to complete your sign-in to Doctor Portal.
                                This code is valid for <strong style="color:#0d1b2a;">10 minutes</strong> and can only be used once.
                            </p>

                            <!-- Digit cells -->
                            <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto 28px;">
                                <tr>
                                    @php $digits = str_split($code); @endphp
                                    @foreach($digits as $digit)
                                    <td style="padding:0 4px;">
                                        <div style="width:44px;height:56px;background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:10px;text-align:center;line-height:56px;font-family:'Courier New',Courier,monospace;font-size:28px;font-weight:700;color:#0369a1;display:inline-block;">{{ $digit }}</div>
                                    </td>
                                    @endforeach
                                </tr>
                            </table>

                            <!-- Expiry notice -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                <tr>
                                    <td style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;">
                                        <p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
                                            <strong>⏱ Expires in 10 minutes.</strong>
                                            If you didn't try to sign in, you can safely ignore this email — your account remains secure.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Divider -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                                <tr><td style="border-top:1px solid #e5eaef;font-size:0;">&nbsp;</td></tr>
                            </table>

                            <!-- Security tip -->
                            <p style="margin:0 0 28px;font-size:12.5px;color:#8daab7;line-height:1.6;">
                                🔒 <strong style="color:#566e7d;">Security reminder:</strong>
                                Doctor Portal will never ask you to share this code by phone, email, or chat.
                                If someone asks for it, do not provide it.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Footer -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background:#f8fafc;border-top:1px solid #e5eaef;padding:20px 40px;border-radius:0 0 16px 16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p style="margin:0;font-size:11.5px;color:#94a3b8;line-height:1.6;">
                                            This is an automated message from Doctor Portal.<br>
                                            Please do not reply — this inbox is not monitored.
                                        </p>
                                    </td>
                                    <td align="right" style="white-space:nowrap;padding-left:16px;vertical-align:top;">
                                        <p style="margin:0;font-size:10px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#cbd5e1;">HIPAA&nbsp;Compliant</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>

        <!-- Bottom margin -->
        <tr><td style="height:24px;"></td></tr>

    </table>

</td></tr>
</table>

</body>
</html>
