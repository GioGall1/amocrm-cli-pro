<?php

namespace App\Services;

use App\Repositories\AmoCrmRepository;
use App\DTO\LeadDTO;
use App\Helpers\Logger;
use Exception;

class LeadService
{
    private AmoCrmRepository $repository;
    private Logger $logger;

    public function __construct(AmoCrmRepository $repository)
    {
        $this->repository = $repository;
        $this->logger = new Logger();
    }

    /**
     * Получить все сделки по статусу
     */
    public function getLeadsByStatus(int $pipelineId, int $statusId): array
    {
        try {
            $response = $this->repository->get('leads', [
                'filter[statuses][0][pipeline_id]' => $pipelineId,
                'filter[statuses][0][status_id]' => $statusId,
            ]);

            $leadsData = $response['_embedded']['leads'] ?? [];
            return array_map(fn($lead) => new LeadDTO($lead), $leadsData);

        } catch (Exception $e) {
            $this->logger->error("Ошибка получения сделок: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Обновить сделку
     */
    public function updateLeadStatus(int $leadId, int $pipelineId, int $newStatusId): bool
    {
        try {
            $this->repository->send('leads', [
                [
                    'id' => $leadId,
                    'pipeline_id' => $pipelineId,
                    'status_id' => $newStatusId,
                ]
            ], 'PATCH');

            $this->logger->info("Сделка #$leadId перемещена в статус $newStatusId");
            return true;
        } catch (Exception $e) {
            $this->logger->error("Ошибка обновления сделки #$leadId: " . $e->getMessage());
            return false;
        }
    }
}