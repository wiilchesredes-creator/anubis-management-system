<?php

declare(strict_types=1);

namespace AnubisBox\Repositories;

use AnubisBox\Domain\Entities\Client;
use PDO;

/**
 * ClientRepository
 * All direct SQL access related to clients lives here.
 * This is the ONLY place that should know the clients table structure.
 */
final class ClientRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * Find a single client by ID with plan credit information.
     */
    public function find(int $id): ?Client
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                p.nombre AS plan_nombre,
                p.valor AS plan_valor,
                p.creditos_mes AS creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Get all active clients that use credits (for the daily credit update job).
     */
    public function findActiveCreditUsers(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                p.nombre AS plan_nombre,
                p.valor AS plan_valor,
                p.creditos_mes AS creditos_mes
            FROM clientes c
            JOIN planes p ON c.id_plan = p.id
            WHERE p.creditos_mes > 0
              AND c.fecha_inicio <= CURDATE()
              AND c.fecha_fin >= CURDATE()
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();

        $clients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clients[] = $this->hydrate($row);
        }
        return $clients;
    }

    /**
     * Update only the credit-related fields for a client.
     * This is called daily by the credit job.
     */
    public function updateCredits(int $clientId, int $newUsed, string $lastUpdatedDate): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE clientes 
            SET 
                creditos_usados = :used,
                fecha_creditos_actualizados = :last_updated
            WHERE id = :id
        ");

        return $stmt->execute([
            ':used'         => $newUsed,
            ':last_updated' => $lastUpdatedDate,
            ':id'           => $clientId,
        ]);
    }

    /**
     * Reactivate a client (remove INACTIVO + fecha_inactivo).
     * Used by the credit job after the 8-day freeze period.
     */
    public function reactivate(int $clientId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE clientes 
            SET 
                estado = 'ACTIVO',
                fecha_inactivo = NULL
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $clientId]);
    }

    /**
     * Set the fecha_inactivo date (used when putting a client into INACTIVO state
     * during the daily credit job to start the 8-day freeze).
     */
    public function updateInactiveDate(int $clientId, string $date): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE clientes 
            SET fecha_inactivo = :fecha 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':fecha' => $date,
            ':id'    => $clientId,
        ]);
    }

    // =====================================================
    // NEW METHODS FOR api/clientes.php REFACTORING (A1)
    // =====================================================

    /**
     * Get all clients with their plan information.
     * Returns an array of hydrated Client entities.
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                c.*,
                p.nombre AS plan_nombre,
                p.valor AS plan_valor,
                p.creditos_mes AS creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            ORDER BY c.created_at DESC
        ");

        $clients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $clients[] = $this->hydrate($row);
        }
        return $clients;
    }

    /**
     * Find a single client by cedula.
     */
    public function findByCedula(string $cedula): ?Client
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                p.nombre AS plan_nombre,
                p.valor AS plan_valor,
                p.creditos_mes AS creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            WHERE c.cedula = :cedula
            LIMIT 1
        ");
        $stmt->execute([':cedula' => $cedula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Create a new client record.
     * Accepts an associative array with client data.
     * Returns the new auto-increment ID.
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO clientes (
                nombre, cedula, fecha_nacimiento, genero, celular, eps,
                id_plan, fecha_inicio, fecha_fin, estado,
                acompanante_nombre, acompanante_cedula, acompanante_celular, acompanante_eps,
                creditos_usados, fecha_creditos_actualizados, fecha_inactivo
            ) VALUES (
                :nombre, :cedula, :fecha_nacimiento, :genero, :celular, :eps,
                :id_plan, :fecha_inicio, :fecha_fin, :estado,
                :acompanante_nombre, :acompanante_cedula, :acompanante_celular, :acompanante_eps,
                :creditos_usados, :fecha_creditos_actualizados, :fecha_inactivo
            )
        ");

        $stmt->execute([
            ':nombre'                       => trim($data['nombre']),
            ':cedula'                       => trim($data['cedula']),
            ':fecha_nacimiento'             => $data['fecha_nacimiento'],
            ':genero'                       => strtoupper(trim($data['genero'] ?? 'NO ESPECIFICAR')),
            ':celular'                      => $data['celular'] ?? null,
            ':eps'                          => $data['eps'] ?? null,
            ':id_plan'                      => (int)$data['id_plan'],
            ':fecha_inicio'                 => $data['fecha_inicio'],
            ':fecha_fin'                    => $data['fecha_fin'],
            ':estado'                       => $data['estado'] ?? 'ACTIVO',
            ':acompanante_nombre'           => $data['acompanante_nombre'] ?? null,
            ':acompanante_cedula'           => $data['acompanante_cedula'] ?? null,
            ':acompanante_celular'          => $data['acompanante_celular'] ?? null,
            ':acompanante_eps'              => $data['acompanante_eps'] ?? null,
            ':creditos_usados'              => (int)($data['creditos_usados'] ?? 0),
            ':fecha_creditos_actualizados'  => $data['fecha_creditos_actualizados'] ?? null,
            ':fecha_inactivo'               => $data['fecha_inactivo'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing client.
     * Only updates the fields present in the $data array.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = [
            'nombre', 'cedula', 'fecha_nacimiento', 'genero', 'celular', 'eps',
            'id_plan', 'fecha_inicio', 'fecha_fin', 'estado',
            'acompanante_nombre', 'acompanante_cedula', 'acompanante_celular', 'acompanante_eps',
            'creditos_usados', 'fecha_creditos_actualizados', 'fecha_inactivo'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $value = $data[$field];

                if ($field === 'genero' && $value !== null) {
                    $value = strtoupper(trim($value));
                }
                if (in_array($field, ['id_plan', 'creditos_usados'])) {
                    $value = (int)$value;
                }

                $params[":$field"] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE clientes SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Find clients whose membership ends in the next X days.
     * Returns raw joined arrays (for current API compatibility with frontend).
     */
    public function findExpiringRaw(int $days): array
    {
        $hoy    = date('Y-m-d');
        $limite = date('Y-m-d', strtotime("+{$days} days"));

        $stmt = $this->pdo->prepare("
            SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor, p.creditos_mes AS plan_creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            WHERE c.fecha_fin BETWEEN :hoy AND :limite
              AND c.estado = 'ACTIVO'
            ORDER BY c.fecha_fin ASC
        ");
        $stmt->execute([':hoy' => $hoy, ':limite' => $limite]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find active clients who have fewer than $threshold credits remaining.
     * Returns raw joined arrays (for current alert system compatibility).
     */
    public function findLowCreditsRaw(int $threshold): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor, p.creditos_mes AS plan_creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            WHERE c.estado = 'ACTIVO'
              AND p.creditos_mes > 0
              AND (p.creditos_mes - c.creditos_usados) < :threshold
            ORDER BY (p.creditos_mes - c.creditos_usados) ASC, c.created_at DESC
        ");
        $stmt->execute([':threshold' => $threshold]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hydrate a raw database row into a Client entity.
     */
    public function hydrate(array $row): Client
    {
        return new Client(
            id: (int)$row['id'],
            nombre: $row['nombre'],
            cedula: $row['cedula'],
            fechaNacimiento: $row['fecha_nacimiento'],
            genero: $row['genero'],
            celular: $row['celular'] ?? null,
            eps: $row['eps'] ?? null,
            idPlan: (int)$row['id_plan'],
            estado: $row['estado'],
            fechaInicio: $row['fecha_inicio'],
            fechaFin: $row['fecha_fin'],
            creditosUsados: (int)$row['creditos_usados'],
            creditosMes: (int)($row['creditos_mes'] ?? 0),
            fechaCreditosActualizados: $row['fecha_creditos_actualizados'] ?? null,
            fechaInactivo: $row['fecha_inactivo'] ?? null,
            planNombre: $row['plan_nombre'] ?? null,
            planValor: isset($row['plan_valor']) ? (float)$row['plan_valor'] : null,
            acompananteNombre: $row['acompanante_nombre'] ?? null,
            acompananteCedula: $row['acompanante_cedula'] ?? null,
            acompananteCelular: $row['acompanante_celular'] ?? null,
            acompananteEps: $row['acompanante_eps'] ?? null,
        );
    }

    /**
     * Update the status (estado) of a client.
     * Used for marking clients as INACTIVO when day-based plan expires.
     */
    public function updateStatus(int $clientId, string $estado): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE clientes 
            SET estado = :estado
            WHERE id = :id
        ");
        return $stmt->execute([
            ':estado' => $estado,
            ':id'    => $clientId,
        ]);
    }
}
