        </main>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('animate-reveal');
                        }, index * 80); // Staggered delay
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -20px 0px' });
            
            const elementsToAnimate = document.querySelectorAll('.stat-card, .table-wrap, .form-panel, .metric-card, .recent-panel, .appointments-panel, .dashboard-appointment-hub, .hub-appointment-card, .appointment-card, .patient-row');
            elementsToAnimate.forEach(el => observer.observe(el));
        });
    </script>
<script>
function toggleDark() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('hcp_theme', 'light');
        document.getElementById('darkIcon').className = 'bi bi-moon-fill';
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('hcp_theme', 'dark');
        document.getElementById('darkIcon').className = 'bi bi-sun-fill';
    }
}
(function () {
    if (localStorage.getItem('hcp_theme') === 'dark') {
        var icon = document.getElementById('darkIcon');
        if (icon) icon.className = 'bi bi-sun-fill';
    }
})();
</script>
<script id="bulletproof-dark-toggle">
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('darkToggle');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var icon = document.getElementById('darkIcon');
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('hcp_theme', 'light');
                if (icon) icon.className = 'bi bi-moon-fill';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('hcp_theme', 'dark');
                if (icon) icon.className = 'bi bi-sun-fill';
            }
        });
    }
});
</script>
</body>
</html>




