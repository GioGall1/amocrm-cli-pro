<?php
require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config/config.php';

use App\Services\AmoCrmApiClient;
use App\Services\LeadService;
use App\Services\TokenService;

$tokenService = new TokenService($config);
$accessToken = $tokenService->getAccessToken();

$repository = new AmoCrmApiClient($config['sub_domain'], $accessToken);
$leadService = new LeadService($repository);

echo "<pre>";
echo "Запуск CLI утилиты AmoCRM\n";

echo "\n=== Перемещение сделок с бюджетом > 5000 ===\n";
$leadService->moveLeadsWithBudgetOver5000(
    $config['pipeline_id'],
    $config['status_primary_contact'],
    $config['status_waiting_client']
);

echo "\n=== Проверка наличия сделок ===\n";
$leads = $leadService->getLeadsByStatus(
    $config['pipeline_id'],
    $config['status_primary_contact']
);
echo "Найдено сделок: " . count($leads) . "\n";
print_r($leads);

echo "\n=== Дублирование сделок с бюджетом = 4999 ===\n";
$leadService->duplicateLeadsWithBudget4999(
    $config['pipeline_id'],
    $config['status_client_confirmed'],
    $config['status_waiting_client']
);

echo "\n Выполнение завершено\n";
