</div> <div id="overlay"></div>
    
</div> <script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuToggleButton = document.getElementById('menu-toggle-button');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function toggleMenu() {
            body.classList.toggle('sidebar-open');
        }

        if(menuToggleButton) {
            menuToggleButton.addEventListener('click', toggleMenu);
        }
        if(overlay) {
            overlay.addEventListener('click', toggleMenu);
        }
        
        const currentPage = window.location.pathname.split('/').pop();
        const menuItems = document.querySelectorAll('#sidebar-content .sidebar-item');
        
        let hasActive = false;
        menuItems.forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('href') === currentPage) {
                item.classList.add('active');
                hasActive = true;
            }
        });

        if (!hasActive && (currentPage === '' || currentPage === 'index.php')) {
            const dashboardItem = document.querySelector('#sidebar-content a[href="index.php"]');
            if(dashboardItem) {
                dashboardItem.classList.add('active');
            }
        }
    });

    // --- NOVA CORREÇÃO ADICIONADA AQUI ---
    // Força o fechamento de modais do SweetAlert ao usar o botão "Voltar" do navegador.
    window.addEventListener('pageshow', function (event) {
        // A propriedade 'persisted' é true se a página foi restaurada do bfcache.
        if (event.persisted) {
            // Se a biblioteca SweetAlert2 existir e um modal estiver visível, feche-o.
            if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                Swal.close();
            }
        }
    });
</script>

</body>
</html>