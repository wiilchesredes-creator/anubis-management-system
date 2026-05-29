# AnubisBox

Sistema de gestión integral para **Anubis Box** (gimnasio / box de entrenamiento).

## Descripción

Plataforma web para la administración de:

- Clientes y membresías
- Planes de entrenamiento (incluyendo planes Pareja)
- Control de asistencia con sistema de **créditos mensuales**
- Finanzas (ingresos, egresos y balance)
- Tienda interna
- Gestión de estados (ACTIVO / INACTIVO / SUSPENDIDO) con reglas de congelamiento de 8 días

## Stack Tecnológico

- **Backend**: PHP 8+
- **Base de datos**: MySQL (XAMPP)
- **Gestión de dependencias**: Composer (PSR-4)
- **Frontend**: HTML + CSS + JavaScript vanilla (multi-página)
- **Arquitectura**: En proceso de migración hacia **OOP + Repository + Service** (Domain-Driven style)

## Estructura Actual

```
anubisbox/
├── api/                  # Endpoints PHP (clientes, asistencia, finanzas, tienda...)
├── src/
│   ├── Domain/Entities/  # Entidades ricas de dominio (Client, etc.)
│   ├── Repositories/     # Acceso a datos (ClientRepository)
│   └── Services/         # Lógica de negocio (CreditService)
├── config/
│   └── database.php
├── assets/
├── backups/              # Respaldos de base de datos
├── launcher/             # Accesos directos para Windows
├── docs/                 # Documentación e instrucciones
├── *.html                # Vistas principales (index, membresias, asistencia, finanzas, tienda...)
├── auth_guard.js
└── composer.json
```

## Lógica de Negocio Destacada

El sistema de **créditos** es la pieza más importante:

- Cada plan tiene una cantidad de créditos mensuales.
- Se descuentan automáticamente solo en días laborales (lunes a sábado).
- Si un cliente se queda sin créditos → pasa a estado **INACTIVO** por **8 días** (créditos congelados).
- Después de los 8 días se reactiva automáticamente.
- Los domingos **no** se descuentan créditos.

## Instalación (Desarrollo Local)

1. Clonar el repositorio dentro de `C:\xampp\htdocs\`
2. Crear la base de datos `anubisbox` en MySQL
3. Importar el esquema desde la carpeta `backups/` si es necesario
4. Ajustar credenciales en `config/database.php`
5. Ejecutar `composer install` (cuando se agreguen dependencias)
6. Abrir `http://localhost/anubisbox`

## Estado del Proyecto

- Arquitectura en transición (código procedural antiguo + nueva capa OOP limpia).
- El `CreditService` ya contiene la lógica crítica de créditos de forma testeable.
- Pendiente: migrar el resto de las APIs al nuevo estilo.

## Contribuir

Este es un proyecto privado en desarrollo activo.

---

**Desarrollado para Anubis Box** — 2026
