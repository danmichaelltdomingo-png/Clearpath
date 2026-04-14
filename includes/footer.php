    </div><!-- end page-content -->
  </div><!-- end main-content -->
</div><!-- end app-layout -->
<script>
// Basic JS enhancements
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(-8px)'; el.style.transition = 'all 0.4s'; setTimeout(() => el.remove(), 400); }, 4000);
});
</script>
</body>
</html>
