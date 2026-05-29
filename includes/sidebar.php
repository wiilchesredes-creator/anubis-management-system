<?php
/**
 * ANUBIS BOX - Sidebar Include
 * Componente reutilizable del sidebar en todos los archivos HTML
 * 
 * Uso: <?php include 'includes/sidebar.php'; ?>
 */
?>
<aside class="sidebar" id="sidebar">
    <div class="logo">
        <img src="assets/logo.png" alt="ANUBIS BOX">
        <strong style="color:#c9952a">ANUBIS BOX</strong>
    </div>
    <nav>
        <a href="index.html">👥 Clientes</a>
        <a href="planes.html">📋 Planes</a>
        <a href="membresias.html">💳 Membresías</a>
        <a href="tienda.html">🛍️ Tienda</a>
        <a href="finanzas.html">💰 Finanzas</a>
        <a href="asistencia.html">✅ Asistencia</a>
        <a href="horarios.html">⏰ Horarios</a>
        <a href="logout.php" style="background:transparent;border:2px solid #c9952a;color:#c9952a;padding:8px 16px;border-radius:5px;cursor:pointer;text-decoration:none;transition:0.3s;margin-top:20px;display:block;text-align:center;"
           onmouseover="this.style.background='rgba(201,149,42,.15)';this.style.color='#c9952a';"
           onmouseout="this.style.background='transparent';this.style.color='#c9952a';">
            🚪 Cerrar sesión
        </a>
    </nav>
</aside>
