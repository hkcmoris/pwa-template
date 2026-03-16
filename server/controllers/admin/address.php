<?php

declare(strict_types=1);

use Administration\Repository;

require_once __DIR__ . '/../../bootstrap.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

csrf_require_valid($_POST, 'json');

$user = app_get_current_user();
$role = $user['role'] ?? 'guest';
if ($role !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Nemáte oprávnění upravovat firemní adresu.']);
    exit;
}

$countryCode = strtoupper(trim((string) ($_POST['country_code'] ?? 'CZ')));
$state = trim((string) ($_POST['state'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$street = trim((string) ($_POST['street'] ?? ''));
$streetNumber = trim((string) ($_POST['street_number'] ?? ''));
$postCode = trim((string) ($_POST['post_code'] ?? ''));

if ($countryCode === '' || strlen($countryCode) !== 2) {
    http_response_code(422);
    echo json_encode(['error' => 'Kód země musí mít 2 znaky (např. CZ).']);
    exit;
}

if (
    $state === '' ||
    $city === '' ||
    $street === '' ||
    $streetNumber === '' ||
    $postCode === ''
) {
    http_response_code(422);
    echo json_encode(['error' => 'Vyplňte všechna povinná pole adresy.']);
    exit;
}

$repository = new Repository();

try {
    $addressId = $repository->saveCompanyAddress([
        'country_code' => $countryCode,
        'state' => $state,
        'city' => $city,
        'street' => $street,
        'street_number' => $streetNumber,
        'post_code' => $postCode,
    ]);

    echo json_encode([
        'message' => 'Firemní adresa byla uložena.',
        'address_id' => $addressId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    log_message('Admin address save failed: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['error' => 'Uložení adresy selhalo.']);
}
