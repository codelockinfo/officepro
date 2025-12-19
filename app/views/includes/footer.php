            </main>
        </div>
    </div>
    
    <script src="/officepro/assets/js/app.js"></script>
    <script src="/officepro/assets/js/modal.js"></script>
    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('user-menu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        
        function logout() {
            // Use custom modal instead of system confirm
            const modalContent = `
                <div style="text-align: center; padding: 30px 20px;">
                    <div style="font-size: 64px; margin-bottom: 20px; color: var(--danger-red);"><i class="fas fa-sign-out-alt"></i></div>
                    <h3 style="color: #333; margin-bottom: 15px; font-size: 24px; font-weight: 600;">Confirm Logout</h3>
                    <p style="color: #666; font-size: 16px; margin-bottom: 0;">Are you sure you want to logout?</p>
                </div>
            `;
            
            const modalFooter = `
                <button type="button" class="btn btn-secondary" onclick="closeModal(this.closest('.modal-overlay').id)">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Yes, Logout</button>
            `;
            
            createModal('', modalContent, modalFooter, 'modal-sm');
        }
        
        function confirmLogout() {
            // Close the modal
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
            
            // Show loading
            showLoader();
            
            // Perform logout
            ajaxRequest('/officepro/app/api/auth/logout.php', 'POST', null, () => {
                showMessage('success', 'Logged out successfully!');
                setTimeout(() => {
                    window.location.href = '/officepro/login.php';
                }, 500);
            });
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            const userProfile = document.querySelector('.user-profile');
            const userMenu = document.getElementById('user-menu');
            const notificationWrapper = document.querySelector('.notification-wrapper');
            const notificationDropdown = document.getElementById('notification-dropdown');
            
            // Close user menu if clicking outside
            if (userProfile && userMenu && !userProfile.contains(e.target) && !userMenu.contains(e.target)) {
                userMenu.style.display = 'none';
            }
            
            // Close notification dropdown if clicking outside
            if (notificationWrapper && notificationDropdown && !notificationWrapper.contains(e.target)) {
                notificationDropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>



