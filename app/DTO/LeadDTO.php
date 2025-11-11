<?php

namespace App\DTO;

class LeadDTO
{
    public int $id;
    public string $name;
    public float $price;
    public int $pipelineId;
    public int $statusId;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? 0;
        $this->name = $data['name'] ?? '';
        $this->price = (float) ($data['price'] ?? 0);
        $this->pipelineId = $data['pipeline_id'] ?? 0;
        $this->statusId = $data['status_id'] ?? 0;
    }
}