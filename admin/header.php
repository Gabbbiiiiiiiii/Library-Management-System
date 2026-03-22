<?php
if (!isset($currentPage)) {
    $currentPage = '';
}
?>

<header class="fixed top-0 left-0 w-full bg-white shadow-sm border-b z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">

        <!-- Left: Logo + Title -->
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center">
                <span class="text-white text-xl font-bold">🛡</span>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">STI College Ormoc</h1>
                <p class="text-sm text-gray-500">Library Admin Portal</p>
            </div>
        </div>

        <!-- Right: Admin + Logout -->
        <div class="flex items-center gap-4">
            <div class="bg-purple-100 px-4 py-2 rounded-xl">
                <p class="text-sm font-semibold text-purple-700">Library Admin</p>
                <p class="text-xs text-purple-600">Administrator</p>
            </div>

            <a href="../auth/logout.php"
               class="flex items-center gap-2 border px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                <span>↩</span> Logout
            </a>
        </div>
    </div>

    <!-- ================= NAVIGATION ================= -->
    <div class="border-t bg-gray-50">
        <div class="max-w-7xl mx-auto px-6">
            <nav class="flex gap-8 text-sm font-medium">

                <a href="dashboard.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'dashboard'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                    🏠 Dashboard
                </a>

                <a href="manage_books.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_books'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                    📚 Manage Books
                </a>

                <a href="manage_borrowings.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_borrowings'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                    ⏱ Borrowings
                </a>

                <a href="manage_returns.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_returns'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                    🔄 Returns
                </a>

                <a href="manage_reservations.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_reservations'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                    📌 Reservations
                </a>

            </nav>
        </div>
    </div>
</header>