<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your verification code</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:40px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.07);">

                {{-- Header --}}
                <tr>
                    <td style="background:#091929;padding:24px 32px;">
                        <span style="font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#00B4D8;">Doctor Portal</span>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px 32px 24px;">
                        <p style="margin:0 0 8px;font-size:15px;font-weight:600;color:#0d1b2a;">Hi {{ $recipientName }},</p>
                        <p style="margin:0 0 24px;font-size:14px;color:#566e7d;line-height:1.6;">
                            Your one-time verification code for Doctor Portal is:
                        </p>

                        {{-- Code block --}}
                        <div style="background:#f0f7ff;border:2px dashed #93c5fd;border-radius:8px;padding:20px;text-align:center;margin-bottom:24px;">
                            <span style="font-family:'Courier New',Courier,monospace;font-size:36px;font-weight:700;letter-spacing:.25em;color:#1d4ed8;">{{ $code }}</span>
                        </div>

                        <p style="margin:0 0 8px;font-size:13px;color:#566e7d;line-height:1.6;">
                            This code expires in <strong style="color:#0d1b2a;">10 minutes</strong> and can only be used once.
                        </p>
                        <p style="margin:0;font-size:13px;color:#566e7d;line-height:1.6;">
                            If you did not attempt to sign in, please ignore this email — your account remains secure.
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="border-top:1px solid #e5e7eb;padding:16px 32px;">
                        <p style="margin:0;font-size:11.5px;color:#9ca3af;line-height:1.5;">
                            This is an automated message from Doctor Portal.<br>
                            Do not reply to this email.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
