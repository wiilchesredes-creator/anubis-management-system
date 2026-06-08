<?php
/**
 * diagnostico.php - Diagnóstico de problemas
 * Ejecutar: http://localhost/anubisbox/diagnostico.php
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnóstico AnubisBox</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f1117; color: #e0e0e0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #c9952a; border-bottom: 2px solid #c9952a; padding-bottom: 10px; }
        .test { background: #1a1d27; border: 1px solid #2a2d3a; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .ok { border-left: 4px solid #4ade80; }
        .error { border-left: 4px solid #f87171; }
        .warning { border-left: 4px solid #facc15; }
        code { background: #0f1117; padding: 2px 6px; border-radius: 3px; color: #4ade80; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Diagnóstico AnubisBox</h1>\n";

require_once 'config/database.php';

// Test 1: Conexión a BD
echo "<div class='test ok'><h3>✅ Test 1: Conexión a BD</h3>\n";
try {
    $pdo = conectar();
    echo "<p>Conexión exitosa a: <code>anubisbox</code></p>\n";
} catch (Exception $e) {
    echo "<div class='test error'><p>❌ Error: " . $e->getMessage() . "</p></div>\n";
    die("No se puede continuar sin conexión.");
}
echo "</div>\n";

// Test 2: Tablas existentes
echo "<div class='test ok'><h3>✅ Test 2: Tablas existentes</h3>\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<p>Tablas: " . implode(", ", $tables) . "</p>\n";
echo "</div>\n";

// Test 3: Estructura de tabla clientes
echo "<div class='test'><h3>📋 Test 3: Columnas de tabla 'clientes'</h3>\n";
$columns = $pdo->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_ASSOC);
if (empty($columns)) {
    echo "<div class='error'><p>❌ Tabla clientes no tiene columnas o no existe</p></div>\n";
} else {
    echo "<p>Total columnas: " . count($columns) . "</p>\n";
    echo "<ul>\n";
    foreach ($columns as $col) {
        $extras = $col['Extra'] ? " ({$col['Extra']})" : "";
        echo "  <li><code>{$col['Field']}</code> - {$col['Type']}{$extras}</li>\n";
    }
    echo "</ul>\n";
}
echo "</div>\n";

// Test 4: Columnas de tabla planes
echo "<div class='test'><h3>📋 Test 4: Columnas de tabla 'planes'</h3>\n";
$planesCols = $pdo->query("SHOW COLUMNS FROM planes")->fetchAll(PDO::FETCH_ASSOC);
if (empty($planesCols)) {
    echo "<div class='error'><p>❌ Tabla planes no tiene columnas o no existe</p></div>\n";
} else {
    echo "<p>Total columnas: " . count($planesCols) . "</p>\n";
    echo "<ul>\n";
    foreach ($planesCols as $col) {
        $extras = $col['Extra'] ? " ({$col['Extra']})" : "";
        $highlight = ($col['Field'] === 'basado_en_dias') ? '✨ ' : '';
        echo "  <li>{$highlight}<code>{$col['Field']}</code> - {$col['Type']}{$extras}</li>\n";
    }
    echo "</ul>\n";
    
    // Verificar si existe basado_en_dias
    $hasBasadoDias = array_filter($planesCols, fn($c) => $c['Field'] === 'basado_en_dias');
    if (!$hasBasadoDias) {
        echo "<div class='warning'><p>⚠️ Columna <code>basado_en_dias</code> NO existe en planes</p></div>\n";
    } else {
        echo "<div class='ok'><p>✅ Columna <code>basado_en_dias</code> existe</p></div>\n";
    }
}
echo "</div>\n";

// Test 5: Test de consulta
echo "<div class='test'><h3>🔬 Test 5: Consulta de clientes</h3>\n";
try {
    $stmt = $pdo->query("
        SELECT c.id, c.nombre, p.nombre AS plan_nombre, p.basado_en_dias
        FROM clientes c
        LEFT JOIN planes p ON c.id_plan = p.id
        LIMIT 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "<div class='ok'><p>✅ Consulta exitosa</p>\n";
        echo "<pre>Cliente: {$result['nombre']}\nPlan: {$result['plan_nombre']}\nBasado en días: {$result['basado_en_dias']}</pre></div>\n";
    } else {
        echo "<div class='warning'><p>⚠️ Consulta exitosa pero sin datos</p></div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'><p>❌ Error en consulta: " . $e->getMessage() . "</p></div>\n";
    echo "<p>Probando consulta alternativa sin <code>basado_en_dias</code>...</p>\n";
    try {
        $stmt = $pdo->query("
            SELECT c.id, c.nombre, p.nombre AS plan_nombre, 0 AS basado_en_dias
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            LIMIT 1
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div class='ok'><p>✅ Consulta alternativa exitosa</p></div>\n";
    } catch (Exception $e2) {
        echo "<div class='error'><p>❌ Error incluso en consulta alternativa: " . $e2->getMessage() . "</p></div>\n";
    }
}
echo "</div>\n";

// Test 6: Datos de clientes
echo "<div class='test'><h3>👥 Test 6: Clientes registrados</h3>\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
    echo "<p>Total clientes: <strong>$count</strong></p>\n";
    
    if ($count > 0) {
        $planes = $pdo->query("
            SELECT p.nombre, COUNT(c.id) as cantidad
            FROM clientes c
            JOIN planes p ON c.id_plan = p.id
            GROUP BY p.id, p.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Distribución por plan:</p><ul>\n";
        foreach ($planes as $p) {
            echo "  <li>{$p['nombre']}: {$p['cantidad']} cliente(s)</li>\n";
        }
        echo "</ul>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'><p>❌ Error: " . $e->getMessage() . "</p></div>\n";
}
echo "</div>\n";

// Test 7: Planes registrados
echo "<div class='test'><h3>📦 Test 7: Planes registrados</h3>\n";
try {
    $planes = $pdo->query("SELECT id, nombre, creditos_mes, basado_en_dias FROM planes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($planes)) {
        echo "<div class='warning'><p>⚠️ No hay planes registrados</p></div>\n";
    } else {
        echo "<p>Total planes: " . count($planes) . "</p><ul>\n";
        foreach ($planes as $p) {
            $tipo = $p['basado_en_dias'] ? '(DÍAS)' : '(CRÉDITOS)';
            $creditos = $p['creditos_mes'] ?? '—';
            echo "  <li>#{$p['id']}: {$p['nombre']} {$tipo} - Créditos: $creditos</li>\n";
        }
        echo "</ul>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'><p>❌ Error al consultar planes: " . $e->getMessage() . "</p></div>\n";
}
echo "</div>\n";

// Conclusión
echo "<div class='test ok'>\n";
echo "<h3>✅ Diagnóstico completado</h3>\n";
echo "<p>Si todos los tests están en verde, el problema puede estar en:</p>\n";
echo "<ul>\n";
echo "  <li>El navegador (intenta limpiar caché: Ctrl+Shift+Supr)</li>\n";
echo "  <li>JavaScript en index.html (abre la consola: F12)</li>\n";
echo "  <li>XAMPP - intenta reiniciar Apache y MySQL</li>\n";
echo "</ul>\n";
echo "<p><a href='http://localhost/anubisbox/' style='color: #c9952a'>Volver a AnubisBox</a></p>\n";
echo "</div>\n";

echo "</div></body></html>\n";
?>
