<?php

declare(strict_types=1);

namespace AnubisBox\Services;

use AnubisBox\Domain\Entities\Client;
use AnubisBox\Repositories\ClientRepository;
use DateTime;
use DateTimeZone;
use PDO;

/**
 * DayBasedPlanService
 * 
 * Handles business logic for day-based plans (FULL and PAREJA).
 * These plans grant 30 workdays (excluding Sundays) from subscription date.
 * When 5 days remain, a notification flag is set.
 */
final class DayBasedPlanService
{
    private DateTimeZone $tz;

    public function __construct(
        private ClientRepository $clientRepository,
        private PDO $pdo
    ) {
        $this->tz = new DateTimeZone('America/Bogota');
    }

    /**
     * Process day-based plans for all eligible clients.
     */
    public function processDayBasedPlans(): array
    {
        $today = new DateTime('now', $this->tz);
        $fechaHoy = $today->format('Y-m-d');

        // Never run on Sunday (business rule)
        if ((int)$today->format('N') === 7) {
            return [
                'ok' => true,
                'mensaje' => 'No se actualizan planes por días los domingos',
                'actualizados' => 0,
                'detalles' => [],
            ];
        }

        $clients = $this->findActiveDayBasedClients();

        $actualizados = 0;
        $resultados = [];

        foreach ($clients as $client) {
            $resultado = $this->processDayBasedClient($client, $today);
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
     * Get all active clients with day-based plans.
     */
    private function findActiveDayBasedClients(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                p.nombre AS plan_nombre,
                p.valor AS plan_valor,
                p.creditos_mes AS creditos_mes
            FROM clientes c
            JOIN planes p ON c.id_plan = p.id
            WHERE p.basado_en_dias = 1
              AND c.estado = 'ACTIVO'
              AND c.fecha_inicio <= CURDATE()
              AND c.fecha_fin >= CURDATE()
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();

        $clients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clients[] = $this->clientRepository->hydrate($row);
        }
        return $clients;
    }

    /**
     * Process a single day-based plan client.
     */
    private function processDayBasedClient(Client $client, DateTime $today): ?array
    {
        $inicio = new DateTime($client->fechaInicio, $this->tz);
        $finContrato = new DateTime($client->fechaFin, $this->tz);
        
        // For day-based plans, we count 30 workdays (excluding Sundays) from subscription date
        $finPeriodo = $this->addWorkdays($inicio, 30);

        // If we've passed the end date, mark as INACTIVO
        if ($today > $finPeriodo) {
            $this->clientRepository->updateStatus($client->id, 'INACTIVO');
            return [
                'id' => $client->id,
                'nombre' => $client->nombre,
                'estado' => 'INACTIVO',
                'razon' => 'Período de 30 días agotado',
                'mensaje' => 'notificación',
            ];
        }

        // Count workdays used so far
        $diasUsados = $this->countWorkdays($inicio, $today);
        $diasRestantes = 30 - $diasUsados;

        // Check if we need to send notification (5 days remaining)
        $notificacionPendiente = ($diasRestantes === 5 && !$this->hasNotificationBeenSent($client->id));

        if ($notificacionPendiente) {
            $this->markNotificationSent($client->id);
            return [
                'id' => $client->id,
                'nombre' => $client->nombre,
                'dias_restantes' => $diasRestantes,
                'mensaje' => 'notificación de 5 días',
            ];
        }

        // Update client info
        $stmt = $this->pdo->prepare("
            UPDATE clientes 
            SET 
                dias_usados = :dias_usados,
                fecha_creditos_actualizados = :fecha_hoy
            WHERE id = :id
        ");
        $stmt->execute([
            ':dias_usados' => $diasUsados,
            ':fecha_hoy' => $today->format('Y-m-d'),
            ':id' => $client->id,
        ]);

        return [
            'id' => $client->id,
            'nombre' => $client->nombre,
            'dias_usados' => $diasUsados,
            'dias_restantes' => $diasRestantes,
        ];
    }

    /**
     * Add N workdays (excluding Sundays) to a date.
     */
    private function addWorkdays(DateTime $date, int $workdaysToAdd): DateTime
    {
        $result = clone $date;
        $count = 0;

        while ($count < $workdaysToAdd) {
            $result->modify('+1 day');
            $dayOfWeek = (int)$result->format('N'); // 1=Monday ... 7=Sunday
            if ($dayOfWeek <= 6) {
                $count++;
            }
        }

        return $result;
    }

    /**
     * Count workdays (excluding Sundays) between two dates (inclusive).
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

    /**
     * Check if the 5-day notification has already been sent for this client.
     */
    private function hasNotificationBeenSent(int $clientId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT notificacion_5_dias FROM clientes WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $clientId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result && $result['notificacion_5_dias'] == 1;
    }

    /**
     * Mark that the 5-day notification has been sent.
     */
    private function markNotificationSent(int $clientId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE clientes SET notificacion_5_dias = 1 WHERE id = :id
        ");
        $stmt->execute([':id' => $clientId]);
    }
}
