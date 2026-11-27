Role: You are an expert UI/UX Designer.
Task: Redesign the provided document tracking table into a minimalist, responsive, and modern enterprise SaaS interface. Follow these strict rules:

1. Header & Filter Rules (Fix the Crowding):

Layout Separation: Ilagay ang Title at Record Count sa top-left. Ilagay ang lahat ng controls (Search, Dropdowns, Filter) sa top-right.

Declutter: Huwag pagsiksikin ang mga dropdowns. I-group ang "All Status" at "All Divisions" sa isang neat horizontal flex container. Gawing prominent ang search bar.

Responsive Header: Kapag mobile view, i-stack nang vertical ang Title at Controls. Gawing full-width ang search bar sa ilalim ng title.

2. Required Columns & Data Rules (Strictly follow this):

Column 1 (Tracking Number): Tracking number (bold) na may date sa ilalim nito (small, light gray text).

Column 2 (Subject Matter / Document Type): Subject text (medium weight) na may document type badge sa ilalim (soft pastel background, pill shape).

Column 3 (Status): Pill-shaped badges lamang. Walang harsh borders. Gumamit ng soft colors (Green/Blue = Noted, Orange/Yellow = Pending, Gray = Completed). Maglagay ng maliliit na colored dots sa loob ng badge para sa modernong look.

Column 4 (Division): Plain text lamang o very subtle gray background. Huwag gawing colorful badge para hindi makipag-compete sa Status.

Column 5 (Action): Iwasan ang mahabang "View Details" button sa bawat row. Gumamit ng minimalist icon button (eye icon o three-dot ellipsis) na naka-align sa kanan. Magiging solid button lang ito kapag hinover.

3. Table & Layout Rules:

Whitespace: Dagdagan ang vertical padding ng bawat row (16px top/bottom) para hindi siksik.

Borders: Gumamit ng subtle 1px light gray borders para paghiwalayin ang rows. Walang heavy borders.

Typography: Clean sans-serif font. Bold para sa headers, medium para sa table text, light gray para sa secondary info (tulad ng dates).

Container: Bilugan ang outer container (8px radius) na may napakagaan na drop shadow.

4. Responsive Rules:

Mobile View: Huwag hayaang mag-horizontal scroll ang table. Gawing "stacked cards" ang bawat row kapag mobile. Ipakita pa rin ang lahat ng required columns sa loob ng card.

Filters: Kapag mobile, gawing isang "Filter" icon button na lang ang mga dropdowns na mag-o-open ng bottom sheet kapag pinindot.

## Project rule for pages with tables

Use `resources/views/receiving/inbox/index.php` as the project's visual reference when creating or modifying a table/list page, unless the user names another page to follow.

- Match its page-title banner: section label, title, subtitle, and compact context/date on the right. Do not add a redundant "Back to Inbox" button to the title banner.
- Match its "At a glance" section and compact statistic cards: colored top accent, neutral number, short supporting text, and responsive stacking.
- Keep the list title/count separate from the controls. On desktop, place search and filters together on the right; on small screens, keep search full-width with a filter icon that opens a bottom sheet.
- Show a readable desktop table only at wide breakpoints. On smaller screens, render dedicated stacked cards that show every field represented by the table; do not rely on horizontal scrolling or squeeze a wide table into cards.
- Use the current page's real fields and actions. Adapt the Receiving Inbox visual pattern to the page's data instead of inventing columns such as Division or removing page-specific information.
- Keep subtle row separators, generous row/card spacing, soft status pills with a small dot, understated secondary metadata, and a compact accessible icon action.
- Keep table column headers consistent across inbox and communications pages: use the same `font-bold` weight and `py-3` vertical padding as the Receiving Inbox header; vary horizontal padding only when column fit requires it.
- Preserve existing filtering, pagination, links, empty states, and query behavior while changing presentation. Include active filters in pagination URLs.