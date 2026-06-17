<?php

declare(strict_types=1);

function sea_email_shell(array $config, string $title, string $bodyHtml, string $footerNote = ''): string
{
    $logoUrl = htmlspecialchars((string) $config['logo_url'], ENT_QUOTES, 'UTF-8');
    $siteUrl = htmlspecialchars((string) $config['site_url'], ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars((string) $config['phone'], ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $footerEsc = htmlspecialchars($footerNote, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$titleEsc}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f4;font-family:Inter,'Segoe UI',Helvetica,Arial,sans-serif;color:#111318;line-height:1.6;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef1f4;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #d8dee4;border-radius:10px;overflow:hidden;box-shadow:0 14px 28px rgba(15,23,32,0.08);">
          <tr>
            <td style="background:#0f1720;padding:28px 32px;text-align:center;">
              <a href="{$siteUrl}" style="text-decoration:none;display:inline-block;">
                <img src="{$logoUrl}" alt="SOLUTIONS EA LLC" width="56" height="98" style="display:block;margin:0 auto 12px;border:0;" />
              </a>
              <div style="font-size:13px;letter-spacing:0.12em;text-transform:uppercase;color:#f8fafc;font-weight:700;">SOLUTIONS <span style="color:#f2b100;">EA LLC</span></div>
              <div style="font-size:12px;color:#cbd2d9;margin-top:4px;">Demolition &amp; Post-Construction Services</div>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 8px;font-size:24px;line-height:1.3;color:#111318;">{$titleEsc}</h1>
              {$bodyHtml}
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 28px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #d8dee4;padding-top:20px;">
                <tr>
                  <td style="font-size:13px;color:#5b6670;">
                    <strong style="color:#111318;">SOLUTIONS EA LLC</strong><br />
                    6500 Hoffner Ave, Orlando, FL 32822<br />
                    <a href="tel:+14076392669" style="color:#c59200;text-decoration:none;">{$phone}</a>
                    &nbsp;·&nbsp;
                    <a href="mailto:sales@solutionseallc.com" style="color:#c59200;text-decoration:none;">sales@solutionseallc.com</a>
                    {$footerEsc}
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function sea_email_data_table(array $rows): string
{
    $html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-top:20px;">';
    foreach ($rows as $label => $value) {
        $labelEsc = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
        $valueEsc = nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
        $html .= '<tr>';
        $html .= '<td style="padding:12px 0;border-bottom:1px solid #eef1f4;vertical-align:top;width:38%;font-size:13px;font-weight:700;color:#5b6670;">' . $labelEsc . '</td>';
        $html .= '<td style="padding:12px 0 12px 16px;border-bottom:1px solid #eef1f4;vertical-align:top;font-size:15px;color:#111318;">' . $valueEsc . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
}

function sea_build_internal_quote_email(array $config, array $data): array
{
    $name = $data['fullName'];
    $subject = 'New Quote Request — ' . $name . ' — SOLUTIONS EA LLC';

    $rows = [
        'Submitted' => $data['submittedAt'],
        'Full Name' => $data['fullName'],
        'Company' => $data['company'],
        'Email' => $data['email'],
        'Phone' => $data['phone'],
        'Preferred Contact' => $data['preferredContact'],
        'Best Time to Reach' => $data['bestTime'],
        'Facility Type' => $data['facilityType'],
        'Services Needed' => implode(', ', $data['servicesNeeded']),
        'Square Footage' => $data['squareFootage'] !== '' ? $data['squareFootage'] . ' sq ft' : 'Not provided',
        'City / State' => $data['cityState'],
        'Desired Timeframe' => $data['timeframe'],
        'Scope Description' => $data['scopeDescription'],
        'Service Reasons' => implode(', ', $data['serviceReasons']),
        'Additional Notes' => $data['additionalNotes'],
    ];

    $bodyHtml = '<p style="margin:0 0 4px;font-size:15px;color:#5b6670;">A new quote request was submitted through the website contact form.</p>';
    $bodyHtml .= '<p style="margin:16px 0 0;"><a href="mailto:' . htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#f2b100;color:#111318;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px;">Reply to ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a></p>';
    $bodyHtml .= sea_email_data_table($rows);

    $html = sea_email_shell($config, 'New Quote Request', $bodyHtml);

    $textLines = ["New quote request from {$name}", ''];
    foreach ($rows as $label => $value) {
        $textLines[] = "{$label}: {$value}";
    }
    $text = implode("\n", $textLines);

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function sea_build_confirmation_quote_email(array $config, array $data): array
{
    $name = $data['fullName'];
    $firstName = explode(' ', trim($name))[0] ?: $name;
    $subject = 'We received your quote request — SOLUTIONS EA LLC';

    $summaryRows = [
        'Facility Type' => $data['facilityType'],
        'Services' => implode(', ', $data['servicesNeeded']),
        'Location' => $data['cityState'],
        'Timeframe' => $data['timeframe'],
        'Preferred Contact' => $data['preferredContact'] . ' (' . $data['bestTime'] . ')',
    ];

    $bodyHtml = '<p style="margin:0;font-size:16px;color:#111318;">Hi ' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . ',</p>';
    $bodyHtml .= '<p style="margin:16px 0 0;font-size:15px;color:#5b6670;">Thank you for contacting <strong style="color:#111318;">SOLUTIONS EA LLC</strong>. We have received your quote request and our team is reviewing your project details.</p>';
    $bodyHtml .= '<p style="margin:16px 0 0;font-size:15px;color:#5b6670;">A project specialist will follow up within <strong style="color:#111318;">one business day</strong> using your preferred contact method. For urgent scheduling needs, call us directly.</p>';
    $bodyHtml .= '<p style="margin:24px 0 8px;font-size:14px;font-weight:700;color:#111318;letter-spacing:0.04em;text-transform:uppercase;">Your submission summary</p>';
    $bodyHtml .= sea_email_data_table($summaryRows);
    $bodyHtml .= '<p style="margin:24px 0 0;"><a href="tel:+14076392669" style="display:inline-block;background:#0f1720;color:#f8fafc;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:8px;margin-right:8px;">Call ' . htmlspecialchars((string) $config['phone'], ENT_QUOTES, 'UTF-8') . '</a></p>';
    $bodyHtml .= '<p style="margin:20px 0 0;font-size:13px;color:#5b6670;">This is an automated confirmation. Please do not reply to this message — replies are not monitored. If you need to add details, submit an updated request or call our team.</p>';

    $html = sea_email_shell($config, 'Request Received', $bodyHtml);

    $text = "Hi {$firstName},\n\n";
    $text .= "Thank you for contacting SOLUTIONS EA LLC. We have received your quote request.\n\n";
    $text .= "Summary:\n";
    foreach ($summaryRows as $label => $value) {
        $text .= "- {$label}: {$value}\n";
    }
    $text .= "\nWe will follow up within one business day.\n";
    $text .= 'Phone: ' . $config['phone'] . "\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
