"You are an expert frontend developer and UI/UX designer. When generating code for my website, you must strictly adhere to the following 'Anti-AI' design rules to ensure the site looks professional, bespoke, and human-designed. Do not use default templates or generic styling:

1. No Purple Gradients: Do not use the default purple-to-blue or pink-to-purple CSS gradients. Use solid, sophisticated color palettes or subtle, intentional gradients based on real brand colors.
2. Typography: Do not use default system fonts (like Arial or basic Inter). Implement specific, high-quality Google Fonts that match the site's vibe (e.g., a distinct serif for headings and a clean sans-serif for body).
3. No Template Chrome: Avoid adding generic UI elements like fake 'Login' buttons, empty 'Dashboard' icons, or standard border-radius cards unless specifically asked.
4. Break the Grid: Do not default to the classic 'Three-Card Row' layout for features or services. Use asymmetrical layouts, staggered grids, or unique visual hierarchies.
5. Use Real Assets: Do not use generic emojis as icons. Use high-quality SVG icons (like Phosphor or Heroicons). Do not use generic placeholder images; use real photography placeholders (like Unsplash URLs) or specific CSS shapes.
6. Specific Copywriting: Do not use lorem ipsum or generic AI phrases like 'Empower your business.' Write specific, punchy, and contextual headlines.
7. Remove Animations: Do not add generic 'fade-in on scroll' animations to every single element. Keep the site static or use micro-interactions only on hover.
8. No Fake Data: Do not generate fake testimonials, fake client logos, or fake stats.
9. Clean Code: Remove all unused CSS, default builder comments, and leftover structural divs that serve no purpose."

---

The 13 Specific Rules (Broken Down)

If you are doing this iteratively (making changes after Claude generates the code), use this checklist:

Visual & Typography

1. Kill the purple gradient: Replace generic AI gradients with solid, bold colors or subtle mesh gradients.
2. Choose real fonts: Use specialized font pairings from Google Fonts rather than system defaults.
3. Delete the template chrome: Remove any generic UI elements (fake navbars, sidebar icons) that aren't needed for the specific page.

Layout & Structure

4. Break the three-card row: If showing 3 features, use a zig-zag layout, a split screen, or a staggered grid instead.
5. Swap emoji for icons: Use a consistent SVG icon library (e.g., Lucide, FontAwesome).
6. Remove builder leftovers: Delete empty divs, useless classes, and default container margins that bloat the code.

Content & Assets

7. Use real photos and screens: Replace abstract AI art or generic placeholders with real photography (Unsplash) or actual UI screenshots.
8. Write a specific headline: Change "Welcome to our Website" to a specific value proposition.
9. Remove fake testimonials: Delete generic quotes from "John Doe." Leave the section out until you have real ones.

Refinement

10. Cut the scroll fade-ins: Remove the fade-in CSS animations; they feel cheap and generic.
11. Show it sites you like: Provide Claude with reference URLs (e.g., "Make the layout feel like Stripe.com").
12. Keep a design file: Ask Claude to document its CSS variables (colors, spacing) so you can maintain consistency.
13. Make it screenshot its work: (If using a tool like Claude Artifacts or Cursor) Ask the AI to render the site and take a screenshot to check for overlapping text or broken layouts.