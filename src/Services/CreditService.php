<?php

declare(strict_types=1);

namespace AnubisBox\Services;

use AnubisBox\Domain\Entities\Client;
use AnubisBox\Repositories\ClientRepository;
use DateTime;
use DateTimeZone;

/**
 * CreditService
 * 
 * Contains all the business rules for the daily credit deduction system.
 * This is the most complex and valuable piece of business logic in AnubisBox.
 * 
 * Previously this logic lived inside actualizar_creditos.php as one giant procedural script.
 * Now it is centralized, testable, and easy to extend.
 */
final class CreditService
{
    private DateTimeZone $tz;

    public function __construct(
        private ClientRepository $clientRepository
    ) {
        $this->tz = new DateTimeZone('America/Bogota');
    }

    /**
     * Main entry point: process credit deduction for all relevant clients today.
     * Returns summary of what was done.
     */
    public function processDailyCreditUpdate(): array
    {
        $today = new DateTime('now', $this->tz);
        $fechaHoy = $today->format('Y-m-d');

        // Never run on Sunday (business rule)
        if ((int)$today->format('N') === 7) {
            return [
                'ok' => true,
                'mensaje' => 'No se actualizan créditos los domingos',
                'actualizados' => 0,
                'detalles' => [],
            ];
        }

        $clients = $this->clientRepository->findActiveCreditUsers();

        $actualizados = 0;
        $resultados = [];

        foreach ($clients as $client) {
            $resultado = $this->processClientCredits($client, $today);
            if ($resultado !== null) {
                $actualizados++;
                $resultados[] = $resultado;
            }
        }

        return [
            'ok' => true,
            'hoy' => $fechaHoy,
            'actualizados' => $actualizados,
            'detalles' => $resultados,
        ];
    }

    /**
     * Process credit deduction for a single client.
     */
    private function processClientCredits(Client $client, DateTime $today): ?array
    {
        $inicio = new DateTime($client->fechaInicio, $this->tz);
        $finContrato = new DateTime($client->fechaFin, $this->tz);
        $ultimo = $client->fechaCreditosActualizados 
            ? new DateTime($client->fechaCreditosActualizados, $this->tz) 
            : null;
        $fechaInactivo = $client->fechaInactivo 
            ? new DateTime($client->fechaInactivo, $this->tz) 
            : null;

        $estadoActual = $client->estado;
        $estadoAnterior = $estadoActual;
        $skipCount = false;
        $desde = null;

        // === INACTIVO handling (8-day freeze) ===
        if ($estadoActual === 'INACTIVO') {
            if (!$fechaInactivo) {
                // Fix data inconsistency
                $this->clientRepository->updateInactiveDate($client->id, $today->format('Y-m-d'));
                return null;
            }

            $reactivarEn = (clone $fechaInactivo)->modify('+8 days');
            if ($today < $reactivarEn) {
                return null; // still frozen
            }

            // Time to reactivate
            $estadoActual = 'ACTIVO';
            $desde = clone $reactivarEn;
            $this->clientRepository->reactivate($client->id);
        }

        // Calculate "desde" date for counting
        if ($ultimo) {
            $desdeUlt = (clone $ultimo)->modify('+1 day');
            $desde = $desde ? max($desde, $desdeUlt) : $desdeUlt;
            if ($desde < $inicio) {
                $desde = clone $inicio;
            }
        } elseif ($client->creditosUsados === 0) {
            $desde = $desde ? max($desde, $inicio) : clone $inicio;
        } else {
            $desde = $desde ?: clone $today;
            $skipCount = true;
        }

        $hasta = $finContrato > $today ? clone $today : clone $finContrato;

        if ($desde > $hasta) {
            return null;
        }

        $diasLaborales = $skipCount ? 0 : $this->countWorkdays($desde, $hasta);

        if ($diasLaborales <= 0 && $ultimo && $ultimo->format('Y-m-d') === $hasta->format('Y-m-d')) {
            return null;
        }

        $nuevoUsado = min(
            $client->creditosUsados + $diasLaborales, 
            $client->creditosMes
        );

        $fechaActualizado = $hasta->format('Y-m-d');

        // Persist the change
        $this->clientRepository->updateCredits($client->id, $nuevoUsado, $fechaActualizado);

        return [
            'id' => $client->id,
            'nombre' => $client->nombre,
            'creditos_usados' => $nuevoUsado,
            'dias_agregados' => $diasLaborales,
            'estado' => $estadoActual,
        ];
    }

    /**
     * Count only Monday–Saturday between two dates (inclusive).
     */
    private function countWorkdays(DateTime $desde, DateTime $hasta): int
    {
        $count = 0;
        $cursor = clone $desde;

        while ($cursor <= $hasta) {
            $dayOfWeek = (int)$cursor->format('N'); // 1=Monday ... 7=Sunday
            if ($dayOfWeek <= 6) {
                $count++;
            }
            $cursor->modify('+1 day');
        }

        return $count;
    }
}
