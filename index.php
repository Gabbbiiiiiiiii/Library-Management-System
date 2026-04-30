<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<section id="home" class="hero hero-modern">
    <div class="hero-overlay"></div>
    <div class="hero-glow hero-glow-1"></div>
    <div class="hero-glow hero-glow-2"></div>

    <div class="hero-inner">
        <div class="hero-badge">Web-Based School Library Platform</div>

        <h1>STI Library Management System</h1>

        <p class="hero-description">
            A modern digital platform designed to simplify book browsing, borrowing,
            reservations, and library monitoring for both students and administrators.
        </p>

        <div class="buttons hero-buttons">
            <a href="auth/register.php" class="btn primary">Get Started</a>
            <a href="#features" class="btn secondary">Explore Features</a>
        </div>

        <div class="hero-mini-stats">
            <div class="mini-stat">
                <span class="mini-stat-number">Fast</span>
                <span class="mini-stat-label">Book Search</span>
            </div>
            <div class="mini-stat">
                <span class="mini-stat-number">Easy</span>
                <span class="mini-stat-label">Borrow & Reserve</span>
            </div>
            <div class="mini-stat">
                <span class="mini-stat-number">Smart</span>
                <span class="mini-stat-label">Admin Monitoring</span>
            </div>
        </div>

        <div class="hero-stats">
            <div class="hero-stat-card">
                <div class="stat-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stat-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-width="2" d="M12 6v13m0-13C9.5 5 7 6 7 6v13s2.5-1 5 0m0-13c2.5-1 5 0 5 0v13s-2.5-1-5 0"/>
                    </svg>
                </div>
                <h3>Student Access</h3>
                <p>Browse available books, reserve unavailable titles, and manage borrowings with ease.</p>
            </div>

            <div class="hero-stat-card">
                <div class="stat-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stat-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-width="2" d="M9 17v-2a4 4 0 014-4h6M9 17H5a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v2M9 17h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3>Admin Control</h3>
                <p>Manage books, users, transactions, and reports from one organized dashboard.</p>
            </div>

            <div class="hero-stat-card">
                <div class="stat-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stat-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-width="2" d="M3 3v18h18M7 15l3-3 3 2 4-5"/>
                    </svg>
                </div>
                <h3>Real-Time Tracking</h3>
                <p>Monitor borrowings, returns, reservations, and overdue books more efficiently.</p>
            </div>
        </div>
    </div>
</section>

<section class="quick-showcase">
    <div class="showcase-container">
        <div class="showcase-box">
            <span class="showcase-label">Why Choose This System</span>
            <h2>A More Efficient and Modern School Library Experience</h2>
            <p>
                Designed to reduce manual work, improve organization, and make book access
                easier for the entire school community.
            </p>

            <div class="showcase-points">
                <div class="showcase-point">
                    <span class="point-check">✓</span>
                    <span>Simple and user-friendly interface</span>
                </div>
                <div class="showcase-point">
                    <span class="point-check">✓</span>
                    <span>Faster library transactions and monitoring</span>
                </div>
                <div class="showcase-point">
                    <span class="point-check">✓</span>
                    <span>Organized records for students and admins</span>
                </div>
                <div class="showcase-point">
                    <span class="point-check">✓</span>
                    <span>Professional system design for school use</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="features modern-section">
    <div class="section-heading">
        <span class="section-label">Core Features</span>
        <h2>Designed for a Smarter Library Workflow</h2>
        <p>
            Built to support both students and administrators with a practical,
            organized, and easy-to-use system.
        </p>
    </div>

    <div class="feature-box modern-grid premium-feature-grid">
        <div class="card modern-card premium-card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="feature-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-width="2" d="M12 6v13m0-13C9.5 5 7 6 7 6v13s2.5-1 5 0m0-13c2.5-1 5 0 5 0v13s-2.5-1-5 0"/>
                </svg>
            </div>
            <h3>Book Management</h3>
            <p>
                Organize titles, authors, categories, cover images, availability, and book records in one place.
            </p>
        </div>

        <div class="card modern-card premium-card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="feature-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-width="2" d="M12 14c4 0 7 2 7 4v2H5v-2c0-2 3-4 7-4zm0-2a4 4 0 100-8 4 4 0 000 8z"/>
                </svg>
            </div>
            <h3>Student Borrowing</h3>
            <p>
                Students can browse available books, borrow titles, and reserve books that are not currently available.
            </p>
        </div>

        <div class="card modern-card premium-card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="feature-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-width="2" d="M3 3v18h18M9 17V9m4 8V5m4 12v-6"/>
                </svg>
            </div>
            <h3>Reports & Monitoring</h3>
            <p>
                Track borrowings, returns, reservations, and overdue books for better decision-making.
            </p>
        </div>

        <div class="card modern-card premium-card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="feature-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z"/>
                </svg>
            </div>
            <h3>Reservation Handling</h3>
            <p>
                Easily manage pending and ready reservations so students know when books are available for pickup.
            </p>
        </div>

        <div class="card modern-card premium-card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="feature-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-width="2" d="M12 8c-3.314 0-6 1.79-6 4s2.686 4 6 4 6-1.79 6-4-2.686-4-6-4zm0 0V5m0 11v3"/>
                </svg>
            </div>
            <h3>Availability Tracking</h3>
            <p>
                View total copies, available copies, and current borrowing status in real time.
            </p>
        </div>

        <div class="card modern-card premium-card">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="feature-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12A9 9 0 113 12a9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3>User-Friendly Interface</h3>
            <p>
                Clean layout, modern components, and simple navigation for a better user experience.
            </p>
        </div>
    </div>
</section>

<section class="workflow-section">
    <div class="section-heading">
        <span class="section-label">How It Works</span>
        <h2>Simple Process for Students and Administrators</h2>
    </div>

    <div class="workflow-grid">
        <div class="workflow-card">
            <span class="workflow-number">01</span>
            <h3>Browse Books</h3>
            <p>Students search for books by title, author, or category.</p>
        </div>

        <div class="workflow-card">
            <span class="workflow-number">02</span>
            <h3>Borrow or Reserve</h3>
            <p>Available books can be borrowed, while unavailable books can be reserved.</p>
        </div>

        <div class="workflow-card">
            <span class="workflow-number">03</span>
            <h3>Track Transactions</h3>
            <p>Admins monitor active borrowings, returns, and overdue books.</p>
        </div>

        <div class="workflow-card">
            <span class="workflow-number">04</span>
            <h3>Generate Reports</h3>
            <p>Administrators review records and make better library decisions.</p>
        </div>
    </div>
</section>

<section id="about" class="about modern-about">
    <div class="about-content modern-about-content premium-about-card">
        <div class="section-heading left-align">
            <span class="section-label">About the System</span>
            <h2>Built for Better Library Operations</h2>
        </div>

        <p>
            The <strong>STI Library Management System</strong> is a web-based platform
            designed to simplify and modernize library processes for both students
            and administrators.
        </p>

        <p>
            It gives students a convenient way to browse books, manage borrowings,
            and reserve titles online, while administrators can maintain records,
            track transactions, and oversee library activities more efficiently.
        </p>

        <p>
            The goal of the system is to provide a reliable, organized, and
            user-friendly digital library experience for the school community.
        </p>
    </div>
</section>

<?php include 'includes/footer.php'; ?>