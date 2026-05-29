<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

$pdo = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

// ── Migración segura: dos precios (venta + compra) ─────────────────────────────
try {
    $cols = $pdo->query("SHOW COLUMNS FROM productos")->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!in_array('precio_venta', $cols)) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER precio");
    }
    if (!in_array('precio_compra', $cols)) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER precio_venta");
    }

    // Migrar datos antiguos: copiar el precio anterior a precio_venta
    $pdo->exec("
        UPDATE productos 
        SET precio_venta = precio 
        WHERE (precio_venta IS NULL OR precio_venta = 0) 
          AND precio > 0
    ");
} catch (Exception $e) {
    // No romper la API si falla la migración
}

// ====================== GET: Listar productos ======================
if ($metodo === 'GET') {
    $stmt = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);
    exit;
}

// ====================== POST: Agregar nuevo producto ======================
if ($metodo === 'POST' && !isset($_GET['venta']) && !isset($_GET['compra'])) {
    $datos = json_decode(file_get_contents('php://input'), true);

    $nombre       = trim($datos['nombre'] ?? '');
    $descripcion  = trim($datos['descripcion'] ?? '');
    $precioVenta  = isset($datos['precio_venta'])  ? (float)$datos['precio_venta']  : (float)($datos['precio'] ?? 0);
    $precioCompra = isset($datos['precio_compra']) ? (float)$datos['precio_compra'] : 0;
    $stock        = (int)($datos['stock'] ?? 0);
    $categoria    = trim($datos['categoria'] ?? '');

    if (!$nombre || $precioVenta <= 0 || $stock < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Nombre, precio de venta y stock válido son obligatorios']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // 1. Crear el producto
        $stmt = $pdo->prepare("
            INSERT INTO productos (nombre, descripcion, precio, precio_venta, precio_compra, stock, categoria)
            VALUES (:nombre, :descripcion, :precio, :precio_venta, :precio_compra, :stock, :categoria)
        ");
        $stmt->execute([
            ':nombre'        => $nombre,
            ':descripcion'   => $descripcion,
            ':precio'        => $precioVenta,
            ':precio_venta'  => $precioVenta,
            ':precio_compra' => $precioCompra,
            ':stock'         => $stock,
            ':categoria'     => $categoria
        ]);

        $nuevoId = (int)$pdo->lastInsertId();

        // 2. Si hay precio de compra y stock inicial > 0 → registrar EGRESO (compra de inventario inicial)
        $montoEgreso = 0;
        $egresoRegistrado = false;

        if ($precioCompra > 0 && $stock > 0) {
            $montoEgreso = $precioCompra * $stock;
            $concepto = "Compra inicial inventario - {$nombre} x{$stock}";

            $pdo->prepare("
                INSERT INTO egresos (concepto, categoria, monto, fecha)
                VALUES (?, 'INSUMOS', ?, CURDATE())
            ")->execute([$concepto, $montoEgreso]);

            $egresoRegistrado = true;
        }

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'id' => $nuevoId,
            'egreso_registrado' => $egresoRegistrado,
            'monto_egreso' => $montoEgreso
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'error' => 'Error al crear el producto',
            'detalle' => $e->getMessage()
        ]);
    }
    exit;
}

// ====================== PUT: Editar producto ======================
if ($metodo === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    $datos = json_decode(file_get_contents('php://input'), true);

    $precioVenta   = isset($datos['precio_venta'])   ? (float)$datos['precio_venta']   : (float)($datos['precio'] ?? 0);
    $precioCompra  = isset($datos['precio_compra'])  ? (float)$datos['precio_compra']  : 0;

    $stmt = $pdo->prepare("
        UPDATE productos 
        SET nombre = :nombre,
            descripcion = :descripcion,
            precio = :precio,
            precio_venta = :precio_venta,
            precio_compra = :precio_compra,
            stock = :stock,
            categoria = :categoria
        WHERE id = :id
    ");

    $stmt->execute([
        ':nombre'        => trim($datos['nombre']),
        ':descripcion'   => trim($datos['descripcion'] ?? ''),
        ':precio'        => $precioVenta,
        ':precio_venta'  => $precioVenta,
        ':precio_compra' => $precioCompra,
        ':stock'         => (int)$datos['stock'],
        ':categoria'     => trim($datos['categoria']),
        ':id'            => $id
    ]);

    echo json_encode(['ok' => true]);
    exit;
}

// ====================== DELETE: Eliminar producto ======================
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("UPDATE productos SET activo = 0 WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ====================== POST VENTA ======================
if ($metodo === 'POST' && isset($_GET['venta'])) {
    $datos = json_decode(file_get_contents('php://input'), true);

    $producto_id = (int)$datos['producto_id'];
    $cantidad    = (int)$datos['cantidad'];
    $metodo_pago = strtoupper($datos['metodo_pago'] ?? 'EFECTIVO');

    // Obtener datos del producto (usamos precio_venta para la venta)
    $stmt = $pdo->prepare("SELECT nombre, precio, precio_venta, stock FROM productos WHERE id = ? AND activo = 1");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();

    if (!$producto || $producto['stock'] < $cantidad) {
        http_response_code(400);
        echo json_encode(['error' => 'Stock insuficiente o producto no encontrado']);
        exit;
    }

    // Preferimos precio_venta; caemos a precio si no existe (compatibilidad)
    $precioVenta = !empty($producto['precio_venta']) ? (float)$producto['precio_venta'] : (float)$producto['precio'];
    $monto = $precioVenta * $cantidad;

    $pdo->beginTransaction();
    try {
        // Descontar stock
        $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?")
            ->execute([$cantidad, $producto_id]);

        // Registrar ingreso
        $concepto = "Tienda - {$producto['nombre']} x{$cantidad}";
        $pdo->prepare("
            INSERT INTO ingresos (id_cliente, concepto, monto, fecha, metodo_pago)
            VALUES (NULL, ?, ?, CURDATE(), ?)
        ")->execute([$concepto, $monto, $metodo_pago]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'monto' => $monto,
            'concepto' => $concepto
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al procesar la venta']);
    }
    exit;
}

// ====================== POST COMPRA DE INVENTARIO (EGRESO) ======================
if ($metodo === 'POST' && isset($_GET['compra'])) {
    $datos = json_decode(file_get_contents('php://input'), true);

    $producto_id = (int)($datos['producto_id'] ?? 0);
    $cantidad    = (int)($datos['cantidad'] ?? 0);

    if ($producto_id <= 0 || $cantidad <= 0) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Producto y cantidad válidos son requeridos',
            'recibido' => [
                'producto_id' => $datos['producto_id'] ?? null,
                'cantidad' => $datos['cantidad'] ?? null,
                'raw' => $datos
            ]
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nombre, precio_compra, stock FROM productos WHERE id = ? AND activo = 1");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        http_response_code(404);
        echo json_encode(['error' => 'Producto no encontrado']);
        exit;
    }

    $precioCompra = !empty($producto['precio_compra']) ? (float)$producto['precio_compra'] : 0;
    if ($precioCompra <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'El producto no tiene precio de compra registrado']);
        exit;
    }

    $monto = $precioCompra * $cantidad;

    $pdo->beginTransaction();
    try {
        // Aumentar stock
        $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?")
            ->execute([$cantidad, $producto_id]);

        // Registrar egreso
        $concepto = "Abastecimiento tienda - {$producto['nombre']} x{$cantidad}";
        $pdo->prepare("
            INSERT INTO egresos (concepto, categoria, monto, fecha)
            VALUES (?, 'INSUMOS', ?, CURDATE())
        ")->execute([$concepto, $monto]);

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'monto' => $monto,
            'concepto' => $concepto,
            'nuevo_stock' => (int)$producto['stock'] + $cantidad
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'error' => 'Error al registrar la compra de inventario',
            'detalle' => $e->getMessage()   // Ayuda a depurar
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
