</section>
  </main>
</div>

<style>
  .profile-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    z-index: 1000;
    display: none;
    width: 220px;
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
        profileMenuBtn.classList.toggle('active');
      });

      document.addEventListener('click', (e) => {
        if (
          !profileMenuBtn.contains(e.target) &&
          !profileDropdown.contains(e.target)
        ) {
          profileDropdown.classList.remove('show');
          profileMenuBtn.classList.remove('active');
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