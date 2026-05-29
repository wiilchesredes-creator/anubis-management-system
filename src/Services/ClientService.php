<?php

declare(strict_types=1);

namespace AnubisBox\Services;

use AnubisBox\Repositories\ClientRepository;
use PDO;
use Exception;

/**
 * ClientService
 *
 * Contains the main business logic for client creation and related operations.
 *
 * This is the central place for:
 * - Creating a client + automatic ingreso record
 * - Special handling for "Clase Única" plans (only ingreso, no client)
 * - Validation and creation of "Pareja" plans (with acompañante data)
 */
final class ClientService
{
    public function __construct(
        private ClientRepository $clientRepository,
        private PDO $pdo
    ) {}

    /**
     * Main entry point for creating a new client (or handling clase única).
     *
     * Returns an array with the result in the same shape the old api/clientes.php used,
     * so the frontend does not need changes during the migration.
     */
    public function createClientWithIngreso(array $data): array
    {
        // 1. Basic required field validation (same as before)
        $required = ['nombre', 'cedula', 'fecha_nacimiento', 'genero', 'celular', 'eps', 'id_plan', 'metodo_pago'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("El campo '$field' es obligatorio");
            }
        }

        // 2. Load the plan
        $stmtPlan = $this->pdo->prepare("SELECT * FROM planes WHERE id = :id");
        $stmtPlan->execute([':id' => $data['id_plan']]);
        $plan = $stmtPlan->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            throw new Exception('Plan no encontrado');
        }

        $fechaHoy   = date('Y-m-d');
        $fechaFin   = date('Y-m-d', strtotime("+{$plan['duracion_dias']} days"));
        $metodoPago = strtoupper(trim($data['metodo_pago']));

        // 3. Special case: "Clase Única" / "1 sola clase" plans
        if ($this->isOneTimeClassPlan($plan)) {
            $concepto = "Clase suelta plan {$plan['nombre']} - {$data['nombre']}";

            $stmtIngreso = $this->pdo->prepare("
                INSERT INTO ingresos (id_cliente, concepto, monto, fecha, metodo_pago)
                VALUES (NULL, :concepto, :monto, :fecha, :metodo_pago)
            ");
            $stmtIngreso->execute([
                ':concepto'    => $concepto,
                ':monto'       => $plan['valor'],
                ':fecha'       => $fechaHoy,
                ':metodo_pago' => $metodoPago,
            ]);

            return [
                'cliente'            => null,
                'ingreso_registrado' => true,
                'solo_ingreso'       => true,
                'monto_ingreso'      => $plan['valor'],
                'concepto_ingreso'   => $concepto,
                'metodo_pago'        => $metodoPago,
            ];
        }

        // 4. Pareja plan validation
        $datosPareja = [];
        if (stripos($plan['nombre'], 'pareja') !== false) {
            $parejaFields = ['acompanante_nombre', 'acompanante_cedula', 'acompanante_celular', 'acompanante_eps'];
            foreach ($parejaFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("El campo '$field' es obligatorio para el plan Pareja");
                }
                $datosPareja[$field] = trim($data[$field]);
            }
        }

        // 5. Create client + ingreso inside a transaction
        $this->pdo->beginTransaction();

        try {
            // Create client using the new repository method
            $clientData = [
                'nombre'           => trim($data['nombre']),
                'cedula'           => trim($data['cedula']),
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'genero'           => strtoupper(trim($data['genero'])),
                'celular'          => trim($data['celular']),
                'eps'              => trim($data['eps']),
                'id_plan'          => (int)$data['id_plan'],
                'fecha_inicio'     => $fechaHoy,
                'fecha_fin'        => $fechaFin,
                'estado'           => 'ACTIVO',
                'acompanante_nombre'  => $datosPareja['acompanante_nombre'] ?? null,
                'acompanante_cedula'  => $datosPareja['acompanante_cedula'] ?? null,
                'acompanante_celular' => $datosPareja['acompanante_celular'] ?? null,
                'acompanante_eps'     => $datosPareja['acompanante_eps'] ?? null,
                'creditos_usados'     => 0,
            ];

            $newClientId = $this->clientRepository->create($clientData);

            // Create the automatic ingreso
            $concepto = "Inscripción plan {$plan['nombre']} - {$data['nombre']}";
            $stmtIngreso = $this->pdo->prepare("
                INSERT INTO ingresos (id_cliente, concepto, monto, fecha, metodo_pago)
                VALUES (:id_cliente, :concepto, :monto, :fecha, :metodo_pago)
            ");
            $stmtIngreso->execute([
                ':id_cliente'  => $newClientId,
                ':concepto'    => $concepto,
                ':monto'       => $plan['valor'],
                ':fecha'       => $fechaHoy,
                ':metodo_pago' => $metodoPago,
            ]);

            $this->pdo->commit();

            // Return the newly created client with plan info (same shape as before)
            $stmtNuevo = $this->pdo->prepare("
                SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor
                FROM clientes c
                LEFT JOIN planes p ON c.id_plan = p.id
                WHERE c.id = :id
            ");
            $stmtNuevo->execute([':id' => $newClientId]);
            $clienteCreado = $stmtNuevo->fetch(PDO::FETCH_ASSOC);

            return [
                'cliente'            => $clienteCreado,
                'ingreso_registrado' => true,
                'monto_ingreso'      => $plan['valor'],
                'concepto_ingreso'   => $concepto,
                'metodo_pago'        => $metodoPago,
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Error al guardar el cliente: ' . $e->getMessage());
        }
    }

    /**
     * Detects if a plan is a "one-time class" / "clase suelta" type.
     */
    private function isOneTimeClassPlan(array $plan): bool
    {
        $nombre = strtoupper(trim($plan['nombre'] ?? ''));
        $nombre = strtr($nombre, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ü' => 'U', 'Ñ' => 'N',
        ]);

        return (strpos($nombre, 'CLASE UNICA') !== false && strpos($nombre, '1 DIA') !== false)
            || strpos($nombre, '1 SOLA CLASE') !== false
            || strpos($nombre, 'UNA SOLA CLASE') !== false
            || strpos($nombre, '1 CLASE') !== false
            || strpos($nombre, 'CLASE SUELTA') !== false;
    }
}
