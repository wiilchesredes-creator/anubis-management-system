/**
 * whatsapp-bot/server.js
 * Servidor de automatización de WhatsApp para AnubisBox
 * 
 * Manejo de:
 * - Escaneo de QR una sola vez
 * - Almacenamiento de sesión (no necesita rescannear)
 * - Envío de mensajes automáticos
 * - API REST para integración con PHP
 */

const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const cors = require('cors');
const qrcode = require('qrcode-terminal');
const fs = require('fs');
const path = require('path');
const axios = require('axios');
const puppeteer = require('puppeteer');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;
const PHP_API = process.env.PHP_API || 'http://localhost/anubisbox/api';

// Middleware
app.use(cors());
app.use(express.json());

// Variables globales
let client = null;
let isClientReady = false;
const sessionDir = path.join(__dirname, '.wwebjs_auth');

console.log(`🤖 AnubisBox WhatsApp Bot v1.0`);
console.log(`📁 Session directory: ${sessionDir}`);

// Función para obtener ruta de Chrome instalado por Puppeteer
async function getChromeExecutablePath() {
  try {
    // Intentar usar el Chrome instalado por Puppeteer
    const executablePath = await puppeteer.executablePath();
    if (fs.existsSync(executablePath)) {
      console.log(`✅ Chrome encontrado en: ${executablePath}`);
      return executablePath;
    }
  } catch (e) {
    console.log('⚠️ Chrome de Puppeteer no encontrado');
  }
  
  // Alternativas (Chrome/Edge del sistema)
  const chromeAlternatives = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  ];
  
  for (const chromePath of chromeAlternatives) {
    if (fs.existsSync(chromePath)) {
      console.log(`✅ Navegador encontrado en: ${chromePath}`);
      return chromePath;
    }
  }
  
  return null; // Dejar que Puppeteer lo busque
}

// ═══════════════════════════════════════════════════════════════════
// ▶ INICIALIZAR CLIENTE DE WHATSAPP
// ═══════════════════════════════════════════════════════════════════

async function initializeWhatsApp() {
  console.log('🔄 Inicializando cliente de WhatsApp...');
  
  try {
    const chromeExecutablePath = await getChromeExecutablePath();
    
    const puppeteerOptions = {
      headless: false, // Mostrar navegador para ver QR
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    };
    
    if (chromeExecutablePath) {
      puppeteerOptions.executablePath = chromeExecutablePath;
    }

    client = new Client({
      authStrategy: new LocalAuth({
        clientId: 'anubisbox-client'
      }),
      puppeteer: puppeteerOptions
    });

    // Evento: QR Code (solo aparece si es primera vez)
    client.on('qr', (qr) => {
      console.log('\n');
      console.log('╔════════════════════════════════════════╗');
      console.log('║  📱 ESCANEA ESTE CÓDIGO QR            ║');
      console.log('║  Con tu teléfono (WhatsApp)            ║');
      console.log('╚════════════════════════════════════════╝');
      console.log('\n');
      qrcode.generate(qr, { small: true });
      console.log('\n');
    });

    // Evento: Cliente listo
    client.on('ready', () => {
      isClientReady = true;
      console.log('✅ ¡Cliente de WhatsApp listo!');
      console.log('🔐 Sesión guardada - no necesitará rescannear');
      console.log(`🌐 API disponible en http://localhost:${PORT}`);
      
      // Iniciar verificación automática de clientes vencidos
      startAutoNotifications();
    });

    // Evento: Desconexión
    client.on('disconnected', () => {
      isClientReady = false;
      console.log('❌ Cliente desconectado');
    });

    // Evento: Error
    client.on('error', (error) => {
      console.error('⚠️ Error en cliente:', error);
    });

    // Iniciar cliente
    await client.initialize();
    
  } catch (error) {
    console.error('❌ Error fatal al inicializar WhatsApp:');
    console.error(error.message);
    console.log('\n💡 Soluciones:');
    console.log('1. Ejecuta: npx puppeteer browsers install chrome@stable');
    console.log('2. O instala Chrome manualmente desde: https://google.com/chrome');
    console.log('3. O instala Microsoft Edge desde: https://microsoft.com/edge');
    process.exit(1);
  }
}

// ═══════════════════════════════════════════════════════════════════
// ▶ ENVIAR MENSAJE
// ═══════════════════════════════════════════════════════════════════

async function sendMessage(phoneNumber, message) {
  if (!isClientReady) {
    throw new Error('Cliente de WhatsApp no está listo');
  }

  try {
    // Formatear número teléfono (agregar código país si no tiene)
    let chatId = phoneNumber.replace(/\D/g, ''); // Solo números
    if (!chatId.startsWith('57')) {
      chatId = '57' + chatId; // Agregar código de Colombia
    }
    chatId = chatId + '@c.us'; // Formato WhatsApp

    console.log(`📤 Enviando mensaje a ${phoneNumber}...`);
    
    const sentMessage = await client.sendMessage(chatId, message);
    
    console.log(`✅ Mensaje enviado exitosamente a ${phoneNumber}`);
    return {
      ok: true,
      messageId: sentMessage.id.id,
      timestamp: new Date().toISOString()
    };
  } catch (error) {
    console.error(`❌ Error al enviar a ${phoneNumber}:`, error.message);
    throw new Error(`No se pudo enviar mensaje: ${error.message}`);
  }
}

// ═══════════════════════════════════════════════════════════════════
// ▶ NOTIFICACIONES AUTOMÁTICAS
// ═══════════════════════════════════════════════════════════════════

function startAutoNotifications() {
  console.log('⏰ Iniciando verificación automática de clientes vencidos...');

  // Verificar cada 6 horas
  setInterval(async () => {
    try {
      console.log('🔍 Verificando clientes con vencimiento próximo...');

      // Llamar al API PHP para obtener clientes que necesitan notificación
      const response = await axios.get(`${PHP_API}/clientes.php?notificacion_dias=1`);
      const clientesParaNotificar = response.data;

      if (clientesParaNotificar.length === 0) {
        console.log('✅ No hay clientes que notificar en este momento');
        return;
      }

      console.log(`📢 Notificando a ${clientesParaNotificar.length} cliente(s)...`);

      for (const cliente of clientesParaNotificar) {
        const mensaje = `
*¡Hola ${cliente.nombre}!* 👋

Te contactamos de *AnubisBox* para notificarte que tu plan *${cliente.plan_nombre}* está próximo a vencerse.

📅 *Tu plan vence mañana*

Para renovar o consultar, contáctanos al:
📞 +57 3XX XXX XXXX
🌐 https://anubisbox.com

¡Gracias por tu confianza! 💪
        `.trim();

        try {
          await sendMessage(cliente.celular, mensaje);
          
          // Marcar que ya fue notificado
          await axios.post(`${PHP_API}/clientes.php?id=${cliente.id}`, {
            notificacion_5_dias: 1
          });

        } catch (err) {
          console.error(`Error notificando a ${cliente.nombre}:`, err.message);
        }

        // Esperar un poco entre mensajes (evitar spam)
        await new Promise(resolve => setTimeout(resolve, 2000));
      }

    } catch (error) {
      console.error('Error en verificación automática:', error.message);
    }
  }, 6 * 60 * 60 * 1000); // 6 horas
}

// ═══════════════════════════════════════════════════════════════════
// ▶ RUTAS API REST
// ═══════════════════════════════════════════════════════════════════

// GET: Estado del cliente
app.get('/api/status', (req, res) => {
  res.json({
    ok: true,
    ready: isClientReady,
    message: isClientReady ? 'Cliente listo' : 'Cliente iniciando...',
    timestamp: new Date().toISOString()
  });
});

// POST: Enviar mensaje único
app.post('/api/send-message', async (req, res) => {
  try {
    const { phoneNumber, message } = req.body;

    if (!phoneNumber || !message) {
      return res.status(400).json({
        ok: false,
        error: 'phoneNumber y message son requeridos'
      });
    }

    const result = await sendMessage(phoneNumber, message);
    res.json(result);

  } catch (error) {
    res.status(500).json({
      ok: false,
      error: error.message
    });
  }
});

// POST: Enviar notificación a cliente
app.post('/api/notify-vencimiento', async (req, res) => {
  try {
    const { clienteId, clienteNombre, clienteCelular, planNombre } = req.body;

    if (!clienteCelular || !clienteNombre || !planNombre) {
      return res.status(400).json({
        ok: false,
        error: 'Faltan datos del cliente (celular, nombre, plan)'
      });
    }

    const mensaje = `
*¡Hola ${clienteNombre}!* 👋

Tu plan *${planNombre}* en *AnubisBox* vence mañana. 📅

Para renovar o consultar más información, contáctanos.

¡Gracias por tu confianza! 💪
    `.trim();

    const result = await sendMessage(clienteCelular, mensaje);
    res.json(result);

  } catch (error) {
    res.status(500).json({
      ok: false,
      error: error.message
    });
  }
});

// GET: Logs en tiempo real
app.get('/api/logs', (req, res) => {
  const logFile = path.join(__dirname, 'bot.log');
  if (fs.existsSync(logFile)) {
    const logs = fs.readFileSync(logFile, 'utf8');
    res.json({ ok: true, logs });
  } else {
    res.json({ ok: true, logs: 'Sin logs aún' });
  }
});

// ═══════════════════════════════════════════════════════════════════
// ▶ INICIAR SERVIDOR
// ═══════════════════════════════════════════════════════════════════

app.listen(PORT, () => {
  console.log(`\n🚀 Servidor WhatsApp corriendo en puerto ${PORT}`);
  console.log(`📍 URL local: http://localhost:${PORT}`);
  console.log(`\n`);
});

// Inicializar WhatsApp
initializeWhatsApp().catch(error => {
  console.error('Error fatal:', error);
  process.exit(1);
});

// Manejo de errores global
process.on('unhandledRejection', (reason, promise) => {
  console.error('❌ Promise Rejection:', reason);
});
