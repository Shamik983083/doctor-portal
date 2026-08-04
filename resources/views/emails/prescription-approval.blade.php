<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your prescription has been approved</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0"
  style="background:#f1f5f9;padding:24px 0 40px;">
  <tr>
    <td align="center">

      <table width="600" cellpadding="0" cellspacing="0" border="0"
        style="max-width:600px;width:100%;">

        {{-- HEADER --}}
        <tr>
          <td style="background:#0f172a;border-radius:12px 12px 0 0;padding:0;">

            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding:24px 32px 20px;">
                  <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td style="vertical-align:middle;">
                        <div style="display:inline-block;width:34px;height:34px;background:#0d9488;border-radius:8px;text-align:center;line-height:34px;vertical-align:middle;margin-right:10px;">
                          <span style="color:#ffffff;font-size:17px;font-weight:800;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;line-height:34px;">Rx</span>
                        </div>
                      </td>
                      <td style="vertical-align:middle;padding-left:2px;">
                        <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:16px;font-weight:700;color:#f8fafc;letter-spacing:-0.01em;">Doctor Portal</span>
                        <span style="display:block;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;color:#64748b;letter-spacing:0.04em;margin-top:1px;">Secure Medical Communications</span>
                      </td>
                    </tr>
                  </table>
                </td>
                <td style="padding:24px 32px 20px;text-align:right;vertical-align:middle;">
                  <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:10px;color:#475569;letter-spacing:0.04em;text-transform:uppercase;font-weight:600;">Case ref</span>
                  <span style="display:block;font-family:'Courier New',Courier,monospace;font-size:12px;color:#94a3b8;margin-top:2px;">{{ $caseRef }}</span>
                </td>
              </tr>
            </table>

            {{-- Teal accent bar --}}
            <div style="height:3px;background:#0d9488;"></div>

            {{-- Status ribbon --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding:20px 32px 24px;">
                  <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td style="vertical-align:middle;padding-right:14px;">
                        <div style="width:44px;height:44px;background:#0d9488;border-radius:50%;text-align:center;line-height:44px;">
                          <span style="color:#ffffff;font-size:24px;font-weight:700;line-height:44px;display:block;">&#10003;</span>
                        </div>
                      </td>
                      <td style="vertical-align:middle;">
                        <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:22px;font-weight:700;color:#f8fafc;display:block;line-height:1.2;letter-spacing:-0.02em;">Prescription Approved</span>
                        <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:13px;color:#64748b;display:block;margin-top:4px;">{{ $approvedAt }} &middot; {{ $partnerName }}</span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        {{-- BODY CARD --}}
        <tr>
          <td style="background:#ffffff;padding:32px 32px 0;">

            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:15px;font-weight:600;color:#0f172a;margin:0 0 20px;">
              Hi {{ $firstName }},
            </p>

            {{-- Message body with teal left border --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:24px;">
              <tr>
                <td width="4" style="background:#0d9488;border-radius:3px;">&nbsp;</td>
                <td style="padding:16px 20px;background:#f8fafc;border-radius:0 8px 8px 0;">
                  <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;line-height:1.75;color:#1e293b;margin:0;">{!! nl2br(e($messageBody)) !!}</p>
                </td>
              </tr>
            </table>

            {{-- What happens next --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
              style="border:1px solid #e2e8f0;border-radius:8px;margin-bottom:28px;">
              <tr>
                <td style="background:#f8fafc;padding:14px 18px;border-bottom:1px solid #e2e8f0;border-radius:8px 8px 0 0;">
                  <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#64748b;">What happens next</span>
                </td>
              </tr>
              <tr>
                <td style="padding:16px 18px;">
                  <table cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                    <tr>
                      <td style="padding-bottom:12px;vertical-align:middle;">
                        <span style="display:inline-block;width:22px;height:22px;background:#ccfbf1;border-radius:50%;text-align:center;line-height:22px;font-size:11px;color:#0d9488;font-weight:700;margin-right:10px;vertical-align:middle;">1</span>
                        <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:13px;color:#334155;vertical-align:middle;">Your prescription is sent to our pharmacy partner for fulfilment</span>
                      </td>
                    </tr>
                    <tr>
                      <td style="vertical-align:middle;">
                        <span style="display:inline-block;width:22px;height:22px;background:#ccfbf1;border-radius:50%;text-align:center;line-height:22px;font-size:11px;color:#0d9488;font-weight:700;margin-right:10px;vertical-align:middle;">2</span>
                        <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:13px;color:#334155;vertical-align:middle;">You will receive a shipping confirmation with your tracking number</span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            {{-- Clinician attribution --}}
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
              style="border-top:1px solid #e2e8f0;padding-top:20px;margin-bottom:28px;">
              <tr>
                <td>
                  <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:10px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#94a3b8;display:block;margin-bottom:6px;">Reviewed &amp; approved by</span>
                  <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;font-weight:600;color:#0f172a;">{{ $clinicianName }}</span>
                  <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:12px;color:#64748b;display:block;margin-top:2px;">{{ $partnerName }} &middot; Doctor Portal</span>
                </td>
                <td style="text-align:right;vertical-align:middle;">
                  <div style="display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;padding:6px 12px;">
                    <span style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;font-weight:600;color:#0d9488;letter-spacing:0.04em;">&#9679; APPROVED</span>
                  </div>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        {{-- FOOTER --}}
        <tr>
          <td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:20px 32px 24px;">
            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;color:#64748b;line-height:1.6;margin:0 0 10px;">
              This message was sent to you because you have an active case with <strong>{{ $partnerName }}</strong> through Doctor Portal. This is a clinical communication and is not affected by your marketing email preferences.
            </p>
            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:11px;color:#94a3b8;line-height:1.5;margin:0;">
              &copy; {{ date('Y') }} Doctor Portal &nbsp;&middot;&nbsp; Confidential medical communication
            </p>
            {{-- Privacy Policy link intentionally omitted --}}
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>
