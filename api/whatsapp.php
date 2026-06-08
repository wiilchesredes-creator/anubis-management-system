<?php
/**
 * api/whatsapp.php
 * Endpoint para integración de WhatsApp Bot con AnubisBox
 * 
 * Rutas disponibles:
 * GET /api/whatsapp.php?action=send-test
 * GET /api/whatsapp.php?action=status
 * POST /api/whatsapp.php?action=notify
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';

$pdo = conectar();
$action = $_GET['action'] ?? 'status';
$WHATSAPP_API = 'http://localhost:3000';

// ═══════════════════════════════════════════════════════════════════
// ▶ FUNCIONES AUXILIARES
// ═══════════════════════════════════════════════════════════════════

/**
 * Verificar si el servicio WhatsApp está disponible
 */
function isWhatsAppAvailable() {
    global $WHATSAPP_API;
    try {
        $ch = curl_init($WHATSAPP_API . '/api/status');
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Enviar solicitud a WhatsApp Bot
 */
function callWhatsAppAPI($action, $data = []) {
    global $WHATSAPP_API;
    
    try {
        $url = $WHATSAPP_API . '/api/' . $action;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return ['ok' => false, 'error' => "HTTP $httpCode"];
        
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ═══════════════════════════════════════════════════════════════════
// ▶ RUTAS
// ═══════════════════════════════════════════════════════════════════

// GET: Estado del servicio WhatsApp
if ($action === 'status') {
    $available = isWhatsAppAvailable();
    
    http_response_code($available ? 200 : 503);
    echo json_encode([
        'ok' => $available,
        'message' => $available ? 'Bot WhatsApp disponible' : 'Bot WhatsApp no disponible',
        'url' => $WHATSAPP_API
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// GET: Enviar mensaje de prueba
if ($action === 'send-test') {
    $phoneTest = $_GET['phone'] ?? '573001234567';
    
    if (!isWhatsAppAvailable()) {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'error' => 'Bot WhatsApp no disponible. ¿Está ejecutándose npm start?'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $result = callWhatsAppAPI('send-message', [
        'phoneNumber' => $phoneTest,
        'message' => "🧪 Mensaje de prueba desde AnubisBox\n\nSi ves esto, ¡el bot está funcionando! ✅"
    ]);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: Notificar cliente de vencimiento
if ($action === 'notify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);
    
    if (!isWhatsAppAvailable()) {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'error' => 'Bot WhatsApp no disponible'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (!isset($datos['clienteId']) || !isset($datos['clienteCelular'])) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Faltan datos: clienteId, clienteCelular'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $result = callWhatsAppAPI('notify-vencimiento', [
        'clienteId' => $datos['clienteId'],
        'clienteNombre' => $datos['clienteNombre'] ?? 'Cliente',
        'clienteCelular' => $datos['clienteCelular'],
        'planNombre' => $datos['planNombre'] ?? 'Plan'
    ]);
    
    // Actualizar flag de notificación en base de datos
    if ($result['ok'] ?? false) {
        try {
            $stmt = $pdo->prepare("UPDATE clientes SET notificacion_5_dias = 1 WHERE id = ?");
            $stmt->execute([$datos['clienteId']]);
        } catch (Exception $e) {
            // No es crítico si falla
        }
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// Acción no reconocida
http_response_code(400);
echo json_encode([
    'ok' => false,
    'error' => 'Acción no reconocida',
    'availableActions' => [
        'status' => 'Verificar disponibilidad del bot',
        'send-test' => 'Enviar mensaje de prueba',
        'notify' => 'Notificar cliente (POST)'
    ]
], JSON_UNESCAPED_UNICODE);
?>
