<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function sea_verify_recaptcha(string $secret, string $token, string $ip): bool
{
    if ($token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);

    $result = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify',
        false,
        $context
    );

    if ($result === false) {
        return false;
    }

    $data = json_decode($result, true);
    return is_array($data) && !empty($data['success']);
}

function sea_check_rate_limit(array $config, string $ip): ?string
{
    $storageDir = __DIR__ . '/../storage/rate-limit';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        return null;
    }

    $file = $storageDir . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $minGap = (int) ($config['rate_limit_seconds'] ?? 60);
    $maxPerHour = (int) ($config['rate_limit_per_hour'] ?? 8);

    $record = ['last' => 0, 'events' => []];
    if (is_readable($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $record = array_merge($record, $decoded);
        }
    }

    if ($record['last'] > 0 && ($now - (int) $record['last']) < $minGap) {
        return 'Please wait a moment before submitting again.';
    }

    $events = array_values(array_filter(
        (array) ($record['events'] ?? []),
        static fn ($ts) => is_int($ts) && ($now - $ts) < 3600
    ));

    if (count($events) >= $maxPerHour) {
        return 'Too many requests. Please try again later or call us directly.';
    }

    $events[] = $now;
    file_put_contents($file, json_encode(['last' => $now, 'events' => $events]), LOCK_EX);

    return null;
}

function sea_validate_quote_submission(array $input): array
{
    $errors = [];

    $facilityType = sea_sanitize_string($input['facilityType'] ?? '', 120);
    $facilityTypeOther = sea_sanitize_string($input['facilityTypeOther'] ?? '', 200);
    if ($facilityType === '') {
        $errors['facilityType'] = 'Please select a facility type.';
    } elseif ($facilityType === 'Other' && $facilityTypeOther === '') {
        $errors['facilityTypeOther'] = 'Please describe your facility type.';
    }

    $servicesNeeded = array_values(array_filter(array_map(
        static fn ($v) => sea_sanitize_string((string) $v, 160),
        (array) ($input['servicesNeeded'] ?? [])
    )));
    $servicesOther = sea_sanitize_string($input['servicesOther'] ?? '', 300);
    if ($servicesNeeded === []) {
        $errors['servicesNeeded'] = 'Please select at least one service.';
    } elseif (in_array('Other', $servicesNeeded, true) && $servicesOther === '') {
        $errors['servicesOther'] = 'Please describe the service you need.';
    }

    $cityState = sea_sanitize_string($input['cityState'] ?? '', 120);
    $timeframe = sea_sanitize_string($input['timeframe'] ?? '', 80);
    $scopeDescription = sea_sanitize_string($input['scopeDescription'] ?? '', 3000);
    if ($cityState === '') {
        $errors['cityState'] = 'City / State is required.';
    }
    if ($timeframe === '') {
        $errors['timeframe'] = 'Please select a timeframe.';
    }
    if ($scopeDescription === '') {
        $errors['scopeDescription'] = 'Please provide a brief scope description.';
    }

    $serviceReasons = array_values(array_filter(array_map(
        static fn ($v) => sea_sanitize_string((string) $v, 160),
        (array) ($input['serviceReasons'] ?? [])
    )));
    $serviceReasonsOther = sea_sanitize_string($input['serviceReasonsOther'] ?? '', 300);
    if ($serviceReasons === []) {
        $errors['serviceReasons'] = 'Please select at least one reason.';
    } elseif (in_array('Other', $serviceReasons, true) && $serviceReasonsOther === '') {
        $errors['serviceReasonsOther'] = 'Please describe your reason.';
    }

    $fullName = sea_sanitize_string($input['fullName'] ?? '', 120);
    $company = sea_sanitize_string($input['company'] ?? '', 160);
    $email = sea_sanitize_string($input['email'] ?? '', 160);
    $phone = sea_sanitize_string($input['phone'] ?? '', 40);
    $preferredContact = sea_sanitize_string($input['preferredContact'] ?? '', 40);
    $bestTime = sea_sanitize_string($input['bestTime'] ?? '', 40);
    $additionalNotes = sea_sanitize_string($input['additionalNotes'] ?? '', 3000);

    if ($fullName === '') {
        $errors['fullName'] = 'Full name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    }
    if ($preferredContact === '') {
        $errors['preferredContact'] = 'Please select a preferred contact method.';
    }
    if ($bestTime === '') {
        $errors['bestTime'] = 'Please select the best time to reach you.';
    }

    if (!empty($input['website'])) {
        $errors['form'] = 'Submission blocked.';
    }

    if ($errors !== []) {
        return ['valid' => false, 'errors' => $errors, 'data' => []];
    }

    $squareFootage = sea_sanitize_string($input['squareFootage'] ?? '', 20);
    if ($squareFootage !== '' && !preg_match('/^\d+$/', $squareFootage)) {
        $errors['squareFootage'] = 'Square footage must be a whole number.';
        return ['valid' => false, 'errors' => $errors, 'data' => []];
    }

    $displayFacility = $facilityType === 'Other' && $facilityTypeOther !== ''
        ? 'Other — ' . $facilityTypeOther
        : $facilityType;

    $displayServices = $servicesNeeded;
    if (in_array('Other', $displayServices, true) && $servicesOther !== '') {
        $displayServices = array_map(
            static fn ($s) => $s === 'Other' ? 'Other — ' . $servicesOther : $s,
            $displayServices
        );
    }

    $displayReasons = $serviceReasons;
    if (in_array('Other', $displayReasons, true) && $serviceReasonsOther !== '') {
        $displayReasons = array_map(
            static fn ($r) => $r === 'Other' ? 'Other — ' . $serviceReasonsOther : $r,
            $displayReasons
        );
    }

    return [
        'valid' => true,
        'errors' => [],
        'data' => [
            'facilityType' => $displayFacility,
            'servicesNeeded' => $displayServices,
            'squareFootage' => $squareFootage,
            'cityState' => $cityState,
            'timeframe' => $timeframe,
            'scopeDescription' => $scopeDescription,
            'serviceReasons' => $displayReasons,
            'fullName' => $fullName,
            'company' => $company !== '' ? $company : '—',
            'email' => $email,
            'phone' => $phone,
            'preferredContact' => $preferredContact,
            'bestTime' => $bestTime,
            'additionalNotes' => $additionalNotes !== '' ? $additionalNotes : '—',
            'submittedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
        ],
    ];
}
