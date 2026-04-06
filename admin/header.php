<?php
session_start();
if (!isset($currentPage)) {
    $currentPage = '';
}
?>

<header class="fixed top-0 left-0 w-full bg-white shadow-sm border-b z-50">
    <div class="max-w-[1489px] mx-auto px-6 py-4 flex justify-between items-center">

        <!-- Left: Logo + Title -->
        <div class="flex items-center gap-4">
            <img src="../assets/images/logo1.png" 
                alt="STI Logo" 
                class="h-12 w-auto object-contain">
            <div>
                <h1 class="text-xl font-bold text-gray-900">STI College Ormoc</h1>
                <p class="text-sm text-gray-500">Library Admin Portal</p>
            </div>
        </div>

        <!-- Right: Admin + Logout -->
        <div class="flex items-center gap-4">
    <div class="relative">
        <button id="adminDropdownBtn"
            type="button"
            class="flex items-center gap-3 bg-purple-100 hover:bg-purple-200 px-4 py-2 rounded-xl transition">
            
            <div class="text-left">
                <p class="text-sm font-semibold text-purple-700">Library Admin</p>
                <p class="text-xs text-purple-600">Administrator</p>
            </div>

            <svg xmlns="http://www.w3.org/2000/svg"
                 class="w-4 h-4 text-purple-600"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

            <div id="adminDropdownMenu"
                    class="hidden absolute right-0 mt-3 w-52 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50">
                    <a href="profile.php"
                    class="block px-4 py-3 text-sm text-slate-700 hover:bg-gray-50">
                        My Profile
                    </a>
                    <a href="../auth/logout.php"
                    class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                        Logout
                    </a>
                </div>
            </div>

            <!-- <a href="../auth/logout.php"
            class="flex items-center gap-2 border px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                <span>↩</span> Logout
            </a> -->
        </div>
    </div>

    <!-- ================= NAVIGATION ================= -->
    <div class="border-t bg-gray-50">
        <div class="max-w-[1489px] mx-auto px-6">
            <nav class="flex gap-8 text-sm font-medium">

                <a href="dashboard.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'dashboard'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Dashboard
                </a>

                <a href="manage_books.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_books'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Manage Books
                </a>

                <a href="manage_borrowings.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_borrowings'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Borrowings
                </a>

                <a href="manage_returns.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_returns'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Returns
                </a>

                <a href="manage_reservations.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'manage_reservations'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Reservations
                </a>

                <a href="reports.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'reports'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Reports
                </a>

                <a href="profile.php"
                class="py-4 border-b-2 transition <?= $currentPage === 'profile'
                        ? 'border-purple-600 text-purple-600'
                        : 'border-transparent hover:text-purple-600 hover:border-purple-600' ?>">
                     Profile
                </a>

            </nav>
        </div>
    </div>

    <script>
const adminDropdownBtn = document.getElementById('adminDropdownBtn');
const adminDropdownMenu = document.getElementById('adminDropdownMenu');

adminDropdownBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    adminDropdownMenu?.classList.toggle('hidden');
});

document.addEventListener('click', function (e) {
    if (!adminDropdownBtn?.contains(e.target) && !adminDropdownMenu?.contains(e.target)) {
        adminDropdownMenu?.classList.add('hidden');
    }
});
</script>
</header>