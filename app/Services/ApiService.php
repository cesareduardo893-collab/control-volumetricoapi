<?php

namespace App\Services;

use App\Services\AlarmaService;
use App\Services\VolumetricCalculationsService;

class ApiService
{
    protected AlarmaService $alarmaService;
    protected VolumetricCalculationsService $volCalc;

    public function __construct(AlarmaService $alarmaService, VolumetricCalculationsService $volCalc)
    {
        $this->alarmaService = $alarmaService;
        $this->volCalc = $volCalc;
    }

    /**
     * Delegates unknown method calls to the underlying services if possible.
     * This keeps the container happy even if the concrete API surface evolves.
     */
    public function __call($name, $arguments)
    {
        foreach ([$this->alarmaService, $this->volCalc] as $service) {
            if (method_exists($service, $name)) {
                return $service->$name(...$arguments);
            }
        }
        throw new \BadMethodCallException("ApiService::{$name} not found on ApiService or delegated services.");
    }
}
