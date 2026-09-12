</section>
  </main>
</div>

<style>
  .profile-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    z-index: 1000;
    display: none;
    max-width: calc(100vw - 32px);
  }

  .profile-dropdown.show {
    display: block;
  }

  @media (max-width: 480px) {
    .profile-dropdown {
      right: -10px;
      width: 220px;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const profileMenuBtn = document.getElementById('profileMenuBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    const bellBtn = document.getElementById('bellBtn');

    if (profileMenuBtn && profileDropdown) {
      profileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
      });

      document.addEventListener('click', (e) => {
        if (!profileDropdown.contains(e.target) && profileDropdown.classList.contains('show')) {
          profileDropdown.classList.remove('show');
        }
      });
    }

    if (bellBtn) {
      bellBtn.addEventListener('click', () => {
        alert('No new unread notifications.');
      });
    }
  });
</script>
</body>
</html>