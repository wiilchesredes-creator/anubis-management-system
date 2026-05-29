<?php

declare(strict_types=1);

namespace AnubisBox\Domain\Entities;

/**
 * Client Entity
 * Represents a client (miembro) in the AnubisBox system.
 * This is a rich domain object — not just a data bag.
 */
final class Client
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $cedula,
        public readonly string $fechaNacimiento,
        public readonly string $genero,
        public readonly ?string $celular,
        public readonly ?string $eps,
        public readonly int $idPlan,
        public readonly string $estado,                    // ACTIVO | INACTIVO | SUSPENDIDO
        public readonly string $fechaInicio,
        public readonly string $fechaFin,
        public int $creditosUsados,
        public readonly int $creditosMes,                  // from plan (denormalized for convenience)
        public readonly ?string $fechaCreditosActualizados,
        public readonly ?string $fechaInactivo,
        public readonly ?string $planNombre = null,
        public readonly ?float $planValor = null,

        // Pareja support
        public readonly ?string $acompananteNombre = null,
        public readonly ?string $acompananteCedula = null,
        public readonly ?string $acompananteCelular = null,
        public readonly ?string $acompananteEps = null,
    ) {}

    /**
     * Returns true if the client is currently in INACTIVO state.
     */
    public function isInactive(): bool
    {
        return strtoupper($this->estado) === 'INACTIVO';
    }

    /**
     * Returns how many credits the client still has available.
     */
    public function remainingCredits(): int
    {
        return max(0, $this->creditosMes - $this->creditosUsados);
    }

    /**
     * Returns true when the client is close to running out of credits.
     */
    public function hasLowCredits(int $threshold = 4): bool
    {
        return $this->creditosMes > 0 && $this->remainingCredits() <= $threshold;
    }

    /**
     * Whether this client follows a credit-based plan (not "clase única").
     */
    public function usesCredits(): bool
    {
        return $this->creditosMes > 0;
    }
}
