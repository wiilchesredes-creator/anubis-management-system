<?php
/**
 * migration.php - Asegurar que todos los campos existen
 * Ejecutar: http://localhost/anubisbox/migration.php
 */

require_once 'config/database.php';

$pdo = conectar();

echo "<pre style='background:#1a1d27;color:#4ade80;padding:20px;font-family:monospace'>\n";
echo "🔧 INICIANDO MIGRACIÓN DE BASE DE DATOS...\n\n";

try {
    // ── 1. TABLA CLIENTES: CAMPOS NUEVOS ──
    echo "1️⃣ Verificando tabla 'clientes'...\n";
    
    $campos = ['dias_usados', 'notificacion_5_dias'];
    foreach ($campos as $campo) {
        $existe = $pdo->query("SHOW COLUMNS FROM clientes LIKE '$campo'")->fetch(PDO::FETCH_ASSOC);
        if (!$existe) {
            if ($campo === 'dias_usados') {
                $pdo->exec("ALTER TABLE clientes ADD COLUMN dias_usados INT DEFAULT 0");
                echo "   ✅ Agregado: dias_usados\n";
            } elseif ($campo === 'notificacion_5_dias') {
                $pdo->exec("ALTER TABLE clientes ADD COLUMN notificacion_5_dias TINYINT DEFAULT 0");
                echo "   ✅ Agregado: notificacion_5_dias\n";
            }
        } else {
            echo "   ✓ Ya existe: $campo\n";
        }
    }
    
    // ── 2. TABLA PLANES: CAMPO basado_en_dias ──
    echo "\n2️⃣ Verificando tabla 'planes'...\n";
    
    $existe = $pdo->query("SHOW COLUMNS FROM planes LIKE 'basado_en_dias'")->fetch(PDO::FETCH_ASSOC);
    if (!$existe) {
        $pdo->exec("ALTER TABLE planes ADD COLUMN basado_en_dias TINYINT DEFAULT 0");
        echo "   ✅ Agregado: basado_en_dias\n";
        
        // Marcar FULL y PAREJA como planes basados en días
        $pdo->exec("UPDATE planes SET basado_en_dias = 1 WHERE UPPER(nombre) LIKE '%FULL%'");
        $pdo->exec("UPDATE planes SET basado_en_dias = 1 WHERE UPPER(nombre) LIKE '%PAREJA%'");
        echo "   ✅ Marcados FULL y PAREJA como basados_en_dias\n";
    } else {
        echo "   ✓ Ya existe: basado_en_dias\n";
    }
    
    // ── 3. VERIFICAR PLANES EXISTENTES ──
    echo "\n3️⃣ Verificando planes...\n";
    
    $planes = $pdo->query("SELECT id, nombre, basado_en_dias FROM planes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($planes)) {
        echo "   ⚠️ No hay planes registrados\n";
    } else {
        foreach ($planes as $p) {
            $tipo = $p['basado_en_dias'] ? '(DÍAS)' : '(CRÉDITOS)';
            echo "   - Plan #{$p['id']}: {$p['nombre']} $tipo\n";
        }
    }
    
    // ── 4. VERIFICAR CLIENTES ──
    echo "\n4️⃣ Verificando clientes...\n";
    
    $count = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    echo "   ✓ Total clientes: $count\n";
    
    // ── 5. TEST DE CONSULTA ──
    echo "\n5️⃣ Test de consulta principal...\n";
    
    $test = $pdo->query("
        SELECT c.id, c.nombre, p.nombre AS plan_nombre, p.basado_en_dias
        FROM clientes c
        LEFT JOIN planes p ON c.id_plan = p.id
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($test) {
        echo "   ✅ Consulta exitosa\n";
        echo "   Ejemplo: Cliente #{$test['id']}: {$test['nombre']}, Plan: {$test['plan_nombre']}, Basado en días: {$test['basado_en_dias']}\n";
    } else {
        echo "   ✓ Sin datos pero consulta funciona\n";
    }
    
    echo "\n✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "\nAhora prueba accediendo a: http://localhost/anubisbox/\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "</pre>\n";
?>
