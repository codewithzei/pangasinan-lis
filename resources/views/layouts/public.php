<?php
$pageTitle = $pageTitle ?? 'Public Portal';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/output.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Custom styles for public portal */
        .hero-gradient {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        .official-seal {
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        }
    </style>
</head>

<body class="font-poppins bg-gray-50 text-gray-800">

    <!-- Public Navigation Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo and Site Title -->
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>/public/portal" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden">
                            <img 
                                src="<?= BASE_URL ?>/public/assets/images/branding/logo.png" 
                                alt="<?= APP_NAME ?> Logo" 
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <div class="hidden sm:block">
                            <div class="text-sm font-bold text-gray-900 leading-tight">Pangasinan</div>
                            <div class="text-xs text-gray-600">Legislative Portal</div>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="<?= BASE_URL ?>/public/portal" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-home mr-2"></i>Home
                    </a>
                    <a href="<?= BASE_URL ?>/public/portal/members" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-users mr-2"></i>SP Members
                    </a>
                    <a href="<?= BASE_URL ?>/public/portal/documents" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-file-lines mr-2"></i>Legislative Records
                    </a>
                    <a href="<?= BASE_URL ?>/public/portal/committees" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-sitemap mr-2"></i>Committees
                    </a>
                    <a href="<?= BASE_URL ?>/public/portal/about" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-info-circle mr-2"></i>About
                    </a>
                    <a href="<?= BASE_URL ?>/public/portal/contact" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary hover:bg-gray-50 rounded-lg transition-colors">
                        <i class="fa-solid fa-envelope mr-2"></i>Contact
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <div class="flex items-center">
                    <button id="mobileMenuBtn" 
                            class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary"
                            aria-expanded="false">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobileMenu" class="hidden md:hidden border-t border-gray-200 bg-white">
            <nav class="max-w-7xl mx-auto px-4 py-3 space-y-1">
                <a href="<?= BASE_URL ?>/public/portal" 
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-home w-5"></i>Home
                </a>
                <a href="<?= BASE_URL ?>/public/portal/members" 
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-users w-5"></i>SP Members
                </a>
                <a href="<?= BASE_URL ?>/public/portal/documents" 
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-file-lines w-5"></i>Legislative Records
                </a>
                <a href="<?= BASE_URL ?>/public/portal/committees" 
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-sitemap w-5"></i>Committees
                </a>
                <a href="<?= BASE_URL ?>/public/portal/about" 
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-info-circle w-5"></i>About
                </a>
                <a href="<?= BASE_URL ?>/public/portal/contact" 
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                    <i class="fa-solid fa-envelope w-5"></i>Contact
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-screen">
        <?= $content ?? '' ?>
    </main>

    <!-- Public Footer -->
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- About Section -->
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden bg-white/10 rounded-lg">
                            <img 
                                src="<?= BASE_URL ?>/public/assets/images/branding/logo.png" 
                                alt="<?= APP_NAME ?> Logo" 
                                class="h-full w-full object-contain p-1"
                            />
                        </div>
                        <div>
                            <div class="text-sm font-bold text-white">Pangasinan</div>
                            <div class="text-xs text-gray-400">Legislative Portal</div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-400 leading-relaxed">
                        Access public legislative information, view records of ordinances and resolutions, 
                        and learn about your elected officials serving the province of Pangasinan.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="<?= BASE_URL ?>/public/portal" 
                               class="text-sm text-gray-400 hover:text-white transition-colors">
                                Portal Home
                            </a>
                        </li>
                        <li>
                            <a href="<?= BASE_URL ?>/public/portal/members" 
                               class="text-sm text-gray-400 hover:text-white transition-colors">
                                SP Members Directory
                            </a>
                        </li>
                        <li>
                            <a href="<?= BASE_URL ?>/public/portal/documents" 
                               class="text-sm text-gray-400 hover:text-white transition-colors">
                                Legislative Records
                            </a>
                        </li>
                        <li>
                            <a href="<?= BASE_URL ?>/public/portal/committees" 
                               class="text-sm text-gray-400 hover:text-white transition-colors">
                                Committees
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">Contact</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot mt-1 text-primary"></i>
                            <span>Pangasinan Provincial Capitol<br>Lingayen, Pangasinan</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-phone text-primary"></i>
                            <span>(075) 542-5000</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-envelope text-primary"></i>
                            <span>info@pangasinan.gov.ph</span>
                        </li>
                    </ul>
                </div>

            </div>

            <div class="border-t border-gray-800 mt-8 pt-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-4">
                    <p class="text-sm text-gray-500">
                        &copy; <?= date('Y') ?> Provincial Government of Pangasinan. All rights reserved.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-4 text-sm text-gray-500">
                        <a href="<?= BASE_URL ?>/public/portal/privacy" class="hover:text-white transition-colors">Privacy Policy</a>
                        <a href="<?= BASE_URL ?>/public/portal/terms" class="hover:text-white transition-colors">Terms of Use</a>
                        <a href="<?= BASE_URL ?>/public/portal/accessibility" class="hover:text-white transition-colors">Accessibility</a>
                        <a href="<?= BASE_URL ?>/public/portal/disclaimer" class="hover:text-white transition-colors">Disclaimer</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Mobile Menu Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    const isExpanded = this.getAttribute('aria-expanded') === 'true';
                    this.setAttribute('aria-expanded', !isExpanded);
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>

</body>

</html>
