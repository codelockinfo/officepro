            </main>
        </div>
    </div>
    
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/modal.js"></script>
    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('user-menu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                ajaxRequest('/app/api/auth/logout.php', 'POST', null, () => {
                    window.location.href = '/login.php';
                });
            }
        }
        
        // Close user menu when clicking outside
        document.addEventListener('click', (e) => {
            const userProfile = document.querySelector('.user-profile');
            const userMenu = document.getElementById('user-menu');
            
            if (userProfile && userMenu && !userProfile.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.style.display = 'none';
            }
        });
    </script>
</body>
</html>


