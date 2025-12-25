        </div><!-- End Content Area -->
    </div><!-- End Main Content -->
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
        
        // Close sidebar on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                document.getElementById('sidebar').classList.remove('show');
                document.getElementById('sidebarOverlay').classList.remove('show');
            }
        });
        
        // Confirmation for delete actions
        function confirmDelete(url, itemName = 'este elemento') {
            Swal.fire({
                title: '¿Estás seguro?',
                text: `Se eliminará ${itemName}. Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#FF6B6B',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }
        
        // Toast notifications
        function showToast(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            
            Toast.fire({
                icon: type,
                title: message
            });
        }
        
        // Format money (Colombian Pesos)
        function formatMoney(amount) {
            return '$' + new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }
        
        // Format money input in real-time (while typing)
        function formatMoneyInput(input) {
            // Remove non-numeric characters
            let value = input.value.replace(/[^\d]/g, '');
            
            // Convert to number and format
            if (value) {
                const number = parseInt(value, 10);
                input.value = new Intl.NumberFormat('es-CO', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(number);
            }
        }
        
        // Initialize money inputs - call this on page load
        function initMoneyInputs() {
            document.querySelectorAll('.money-input, [data-money-input]').forEach(function(input) {
                // Format on input (while typing)
                input.addEventListener('input', function() {
                    formatMoneyInput(this);
                });
                
                // Also format on paste
                input.addEventListener('paste', function(e) {
                    setTimeout(() => formatMoneyInput(this), 0);
                });
                
                // Format initial value if exists
                if (input.value) {
                    formatMoneyInput(input);
                }
            });
        }
        
        // Auto-initialize money inputs when DOM is ready
        document.addEventListener('DOMContentLoaded', initMoneyInputs);
        
        // Get raw number from formatted money input
        function getMoneyValue(input) {
            return parseInt(input.value.replace(/[^\d]/g, '') || '0', 10);
        }
        
        // Format date
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-CO', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
    </script>
    
    <?php if (isset($extraScripts)): ?>
        <?= $extraScripts ?>
    <?php endif; ?>
</body>
</html>

