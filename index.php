<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<section id="home" class="hero">
    <div class="hero-content">
        <h1>STI Library Management System</h1>
        <p>
            Welcome Students! Browse books, manage your borrowings, 
            and explore our digital library system.
        </p>

        <div class="buttons">
            <a href="auth/student_login.php" class="btn primary">Login</a>
            <a href="auth/register.php" class="btn secondary">Register</a>
        </div>
    </div>
</section>

<section class="features">
    <h2>System Features</h2>

    <div class="feature-box">
        <div class="card">
            <h3>📚 Book Management</h3>
            <p>Easily manage library books, availability, and records.</p>
        </div>

        <div class="card">
            <h3>👩‍🎓 Student Borrowing</h3>
            <p>Students can browse and request books anytime.</p>
        </div>

        <div class="card">
            <h3>📊 Reports & Monitoring</h3>
            <p>Track borrowed, returned, and overdue books efficiently.</p>
        </div>
    </div>
</section>

<section id="about" class="about">
    <div class="about-content">
        <h2>About Us</h2>

        <p>
            The <strong>STI Library Management System</strong> is a web-based platform 
            designed to simplify and modernize library operations for both students and administrators.
        </p>

        <p>
            This system allows students to browse available books, manage their borrowings,
            and access library resources efficiently. Administrators can manage book records,
            monitor transactions, and maintain user accounts securely.
        </p>

        <p>
            Our goal is to provide a reliable, organized, and secure digital library 
            experience for the entire academic community.
        </p>
    </div>
</section>

<script>
window.addEventListener("scroll", function () {
    let image = document.getElementById("heroImage");

    if (window.scrollY > 50) {
        image.classList.add("scrolled");
    } else {
        image.classList.remove("scrolled");
    }
});
</script>

<?php include 'includes/footer.php'; ?>