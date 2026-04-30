<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/library-management-system/index.php" class="brand-link">
                <div class="brand-logo-wrap">
                    <img src="/library-management-system/assets/images/logo1.png" alt="STI Logo" class="brand-logo">
                </div>

                <div class="brand-text">
                    <span class="brand-title">STI College Ormoc</span>
                    <span class="brand-subtitle">Library Management System</span>
                </div>
            </a>
        </div>

        <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navLinks">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <ul class="nav-links" id="navLinks">
            <li><a href="#home" class="nav-item active">Home</a></li>
            <li><a href="#features" class="nav-item">Features</a></li>
            <li><a href="#about" class="nav-item">About</a></li>
            <li><a href="auth/student_login.php" class="nav-login">Login</a></li>
        </ul>
    </div>
</nav>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const menuToggle = document.getElementById("menuToggle");
    const navLinks = document.getElementById("navLinks");
    const navbar = document.querySelector(".navbar");
    const navItems = document.querySelectorAll(".nav-item");

    if (menuToggle && navLinks) {
        menuToggle.addEventListener("click", function () {
            navLinks.classList.toggle("active");
            menuToggle.classList.toggle("active");

            const expanded = menuToggle.classList.contains("active");
            menuToggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        });

        navLinks.querySelectorAll("a").forEach(link => {
            link.addEventListener("click", function () {
                navLinks.classList.remove("active");
                menuToggle.classList.remove("active");
                menuToggle.setAttribute("aria-expanded", "false");
            });
        });
    }

    window.addEventListener("scroll", function () {
        if (!navbar) return;

        if (window.scrollY > 20) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
        }
    });

    const sections = document.querySelectorAll("section[id]");

    function setActiveLink() {
        let current = "";

        sections.forEach(section => {
            const sectionTop = section.offsetTop - 140;
            const sectionHeight = section.offsetHeight;

            if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                current = section.getAttribute("id");
            }
        });

        navItems.forEach(link => {
            link.classList.remove("active");
            if (link.getAttribute("href") === "#" + current) {
                link.classList.add("active");
            }
        });
    }

    window.addEventListener("scroll", setActiveLink);
    setActiveLink();
});
</script>