<?php

namespace App\Services;

use App\Services\AmoCrmApiClient;
use App\DTO\LeadDTO;
use App\Services\LoggerService;
use Exception;

class LeadService
{
    private AmoCrmApiClient $apiClient;
    private LoggerService $logger;

    public function __construct(AmoCrmApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->logger = new LoggerService();
    }

    /**
     * Получить сделки по указанному pipeline и статусу.
     */
    public function getLeadsByStatus(int $pipelineId, int $statusId): array
    {
        try {
            echo "Запрос всех сделок через AmoCrmRepository...\n";

            $response = $this->apiClient->get('leads', [
                'limit' => 250,
                'with'  => 'contacts'
            ]);

            $leadsData = $response['_embedded']['leads'] ?? [];
            echo "Всего получено сделок: " . count($leadsData) . "\n";

            return $this->filterLeadsByPipelineAndStatus($leadsData, $pipelineId, $statusId);
        } catch (Exception $e) {
            $this->logger->error("Ошибка получения сделок: " . $e->getMessage());
            echo "Ошибка при запросе сделок: " . $e->getMessage() . "\n";
            return [];
        }
    }

    /**
     * Отфильтровать и преобразовать сделки по pipeline и статусу.
     */
    private function filterLeadsByPipelineAndStatus(array $leadsData, int $pipelineId, int $statusId): array
    {
        $filtered = array_filter($leadsData, fn($lead) =>
            isset($lead['pipeline_id'], $lead['status_id'])
            && $lead['pipeline_id'] == $pipelineId
            && $lead['status_id'] == $statusId
        );

        echo "Отфильтровано сделок по статусу: " . count($filtered) . "\n";

        return array_map(fn($lead) => new LeadDTO($lead), $filtered);
    }

    public function updateLeadStatus(int $leadId, int $pipelineId, int $newStatusId): bool
    {
        try {
            $this->apiClient->send('leads', [
                [
                    'id' => $leadId,
                    'pipeline_id' => $pipelineId,
                    'status_id' => $newStatusId,
                ]
            ], 'PATCH');

            echo "Сделка #$leadId перемещена в статус $newStatusId\n";
            $this->logger->info("Сделка #$leadId перемещена в статус $newStatusId");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Ошибка обновления сделки #$leadId: " . $e->getMessage());
            return false;
        }
    }

    public function moveLeadsWithBudgetOver5000(int $pipelineId, int $fromStatusId, int $toStatusId): void
    {
        echo "=== Поиск сделок с бюджетом > 5000 ===\n";
        $leads = $this->getLeadsByStatus($pipelineId, $fromStatusId);

        foreach ($leads as $lead) {
            if ($lead->price > 5000) {
                $this->updateLeadStatus($lead->id, $pipelineId, $toStatusId);
            }
        }
    }

    public function duplicateLeadsWithBudget4999(int $pipelineId, int $fromStatusId, int $toStatusId): void
    {
        echo "=== Поиск сделок с бюджетом = 4999 ===\n";
        $leads = $this->getLeadsByStatus($pipelineId, $fromStatusId);

        foreach ($leads as $lead) {
            if ((int)$lead->price === 4999) {
                $newLead = new LeadDTO([
                    'name'        => $lead->name . ' (копия)',
                    'price'       => $lead->price,
                    'pipeline_id' => $pipelineId,
                    'status_id'   => $toStatusId,
                ]);

                $this->apiClient->send('leads', [$newLead->toArray()]);
                echo "Создана копия сделки #{$lead->id}\n";
            }
        }
    }
}