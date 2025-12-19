    </div> <!-- .main-content -->
    
<script>
// Toggle sidebar en móviles
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Asegurar que el sidebar esté oculto por defecto en móviles
    if (sidebar && window.innerWidth <= 767.98) {
        sidebar.classList.remove('active');
    }
    
    if (sidebarToggle && sidebar && overlay) {
        // Abrir sidebar
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevenir scroll del body
        });
        
        // Cerrar sidebar al hacer clic en overlay
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
        
        // Cerrar sidebar con botón de cerrar
        const closeBtn = document.getElementById('closeSidebar');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeSidebar();
            });
        }
        
        // Cerrar sidebar al hacer clic en un enlace (solo en móviles)
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 767.98) {
                    closeSidebar();
                }
            });
        });
        
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = ''; // Restaurar scroll
        }
        
        // Cerrar sidebar al redimensionar a desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767.98) {
                closeSidebar();
            }
        });
    }
});
</script>
</body>
</html>
