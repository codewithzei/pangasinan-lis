<!-- Hero Section -->
<section class="bg-gradient-to-br from-indigo-600 to-indigo-800 text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 text-indigo-200 mb-3">
            <a href="<?= BASE_URL ?>/public/portal" class="hover:text-white transition-colors">
                <i class="fa-solid fa-home"></i> Portal
            </a>
            <i class="fa-solid fa-chevron-right text-sm"></i>
            <span class="text-white font-medium">Accessibility Statement</span>
        </div>
        
        <h1 class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
            Accessibility Statement
        </h1>
        <p class="text-xl text-indigo-200 max-w-3xl">
            Our commitment to accessible and inclusive digital services
        </p>
    </div>
</section>

<!-- Main Content -->
<section class="py-16 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="prose prose-lg max-w-none">
            
            <p class="text-gray-600 text-sm mb-8">
                <strong>Last Updated:</strong> <?= date('F d, Y') ?>
            </p>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Our Commitment</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    The Sangguniang Panlalawigan of Pangasinan is committed to ensuring digital accessibility for all users, 
                    including persons with disabilities. We strive to provide an inclusive online experience through the 
                    Pangasinan Legislative Information System, making public information accessible to everyone.
                </p>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Standards and Guidelines</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    We aim to conform to the Web Content Accessibility Guidelines (WCAG) 2.1 Level AA standards, which are 
                    internationally recognized guidelines for accessible web content. We also strive to align with the 
                    Philippine Accessibility Law (Batas Pambansa Blg. 344, as amended).
                </p>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Accessibility Features</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    The System includes the following accessibility features:
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    
                    <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white">
                                <i class="fa-solid fa-keyboard"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Keyboard Navigation</h3>
                        </div>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            All interactive elements can be accessed and operated using a keyboard alone, without requiring a mouse.
                        </p>
                    </div>

                    <div class="bg-green-50 rounded-xl p-6 border border-green-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center text-white">
                                <i class="fa-solid fa-eye"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Screen Reader Support</h3>
                        </div>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            Content is structured to be compatible with popular screen readers used by visually impaired users.
                        </p>
                    </div>

                    <div class="bg-primary-50 rounded-xl p-6 border border-primary-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center text-white">
                                <i class="fa-solid fa-adjust"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Text Contrast</h3>
                        </div>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            We maintain sufficient color contrast between text and backgrounds to ensure readability.
                        </p>
                    </div>

                    <div class="bg-purple-50 rounded-xl p-6 border border-purple-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center text-white">
                                <i class="fa-solid fa-mobile-screen"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Responsive Design</h3>
                        </div>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            The System is designed to work across various devices and screen sizes, including mobile devices.
                        </p>
                    </div>

                    <div class="bg-amber-50 rounded-xl p-6 border border-amber-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-amber-600 rounded-lg flex items-center justify-center text-white">
                                <i class="fa-solid fa-heading"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Clear Structure</h3>
                        </div>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            Content is organized with clear headings, lists, and semantic HTML to facilitate navigation.
                        </p>
                    </div>

                    <div class="bg-red-50 rounded-xl p-6 border border-red-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 bg-red-600 rounded-lg flex items-center justify-center text-white">
                                <i class="fa-solid fa-image"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Alternative Text</h3>
                        </div>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            Images and non-text content include alternative text descriptions for screen reader users.
                        </p>
                    </div>

                </div>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Limitations and Challenges</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    Despite our best efforts, some portions of the System may not yet be fully accessible. We acknowledge 
                    the following limitations:
                </p>
                <ul class="list-disc list-inside space-y-2 ml-4 text-gray-700">
                    <li>Some older documents may not be formatted for optimal screen reader compatibility</li>
                    <li>PDF documents may have varying levels of accessibility depending on how they were created</li>
                    <li>Third-party embedded content (if any) may not meet our accessibility standards</li>
                </ul>
                <p class="text-gray-700 leading-relaxed mt-4">
                    We are actively working to address these issues and improve accessibility across all aspects of the System.
                </p>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Assistive Technologies</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    The System is designed to be compatible with the following assistive technologies:
                </p>
                <ul class="list-disc list-inside space-y-2 ml-4 text-gray-700">
                    <li>Screen readers (e.g., JAWS, NVDA, VoiceOver)</li>
                    <li>Screen magnification software</li>
                    <li>Voice recognition software</li>
                    <li>Keyboard navigation aids</li>
                    <li>Modern web browsers with accessibility features enabled</li>
                </ul>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Browser Compatibility</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    For the best experience, we recommend using the latest versions of the following browsers:
                </p>
                <ul class="list-disc list-inside space-y-2 ml-4 text-gray-700">
                    <li>Google Chrome</li>
                    <li>Mozilla Firefox</li>
                    <li>Microsoft Edge</li>
                    <li>Apple Safari</li>
                </ul>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Ongoing Efforts</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    We are committed to continuously improving accessibility. Our ongoing efforts include:
                </p>
                <ul class="list-disc list-inside space-y-2 ml-4 text-gray-700">
                    <li>Regular accessibility audits and testing</li>
                    <li>Training staff on accessible content creation</li>
                    <li>Incorporating accessibility into system design and development processes</li>
                    <li>Soliciting and responding to feedback from users with disabilities</li>
                </ul>
            </div>

            <div class="mb-12 bg-indigo-50 rounded-xl p-8 border border-indigo-200">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Feedback and Assistance</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    We welcome your feedback on the accessibility of the Pangasinan Legislative Information System. 
                    If you encounter accessibility barriers or have suggestions for improvement, please contact us:
                </p>
                <div class="text-gray-700">
                    <p class="font-semibold mb-2">Accessibility Coordinator</p>
                    <p>Sangguniang Panlalawigan Office</p>
                    <p>Provincial Capitol, Lingayen, Pangasinan 2401</p>
                    <p class="mt-2">Email: <a href="mailto:info@pangasinan.gov.ph" class="text-indigo-700 hover:text-indigo-900">info@pangasinan.gov.ph</a></p>
                    <p>Phone: (075) 542-5000</p>
                </div>
                <p class="text-gray-700 leading-relaxed mt-4">
                    We aim to respond to accessibility feedback within 5 business days. If you require assistance accessing 
                    information that is not currently accessible, please let us know, and we will work to provide the information 
                    in an alternative format.
                </p>
            </div>

            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Formal Complaints</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    If you are not satisfied with our response to your accessibility concerns, you may escalate your complaint 
                    through the appropriate channels within the Provincial Government of Pangasinan.
                </p>
            </div>

        </div>

    </div>
</section>

<!-- Call to Action -->
<section class="py-12 bg-gray-50 border-t border-gray-200">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-4">Help Us Improve</h2>
        <p class="text-gray-600 mb-6 max-w-2xl mx-auto">
            Your feedback helps us make the System more accessible. Let us know how we can serve you better.
        </p>
        <a href="<?= BASE_URL ?>/public/portal/contact" 
           class="inline-flex items-center gap-2 px-8 py-3 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition-colors shadow-md">
            <i class="fa-solid fa-envelope"></i>
            Contact Us
        </a>
    </div>
</section>
