<?php
/**
 * ANUBIS BOX - Header Include
 * Componente reutilizable del header en todos los archivos HTML
 * 
 * Uso: <?php include 'includes/header.php'; ?>
 */
?>
<header>
    <div class="nav-left">
        <button id="menuToggle" class="menu-toggle" style="background:none;border:none;font-size:24px;cursor:pointer;color:#c9952a;">☰</button>
    </div>
    <div class="nav-center">
        <h1>ANUBIS BOX</h1>
    </div>
    <div class="nav-right">
        <div style="display:flex;align-items:center;gap:15px;">
            <span id="horaActual" style="color:#c9952a;font-weight:bold;"></span>
            <a href="logout.php" 
               style="background:transparent;border:2px solid #c9952a;color:#c9952a;padding:8px 16px;border-radius:5px;cursor:pointer;text-decoration:none;transition:0.3s;"
               onmouseover="this.style.background='rgba(201,149,42,.15)';this.style.color='#c9952a';"
               onmouseout="this.style.background='transparent';this.style.color='#c9952a';">
                🚪 Cerrar sesión
            </a>
        </div>
    </div>
</header>

<div id="sidebarOverlay" style="display:none;"></div>

<script>
// Actualizar hora en tiempo real
setInterval(() => {
    const ahora = new Date();
    const hora = ahora.toLocaleTimeString('es-CO');
    const horaElement = document.getElementById('horaActual');
    if (horaElement) horaElement.textContent = hora;
}, 1000);
</script>
