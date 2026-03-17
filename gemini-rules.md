# ConsuCorner Styles & Architecture Guide (Rules)

This file dictates the coding and design standards for the ConsuCorner Headless WordPress project. It must be strictly followed to maintain a high-quality, modern, and premium codebase.

## 1. Design & Styling (Premium UI/UX)

### Colors
- **Primary Blue (`--primary`)**: `#2597e0`
- **Secondary Teal/Mint (`--secondary`)**: `#64e9cc`
- **Accent Color (`--accent`)**: `#00a392`
- **Background/Supporting (`--background`)**: `#d0e8e3`
- **Dark Text**: `#1f2937`
- **Light Text**: `#ffffff`
- **Page Background**: `#ffffff`

### Typography
- **Primary Font (Headings & Key Text)**: `Manrope` (Weights: 200-800)
- **Secondary Font (Body & Supporting Content)**: `Poppins` (Weights: 400, 500, 600, 800)
  *Note: Previously used Inter; we have migrated to Poppins for a premium feel.*

### Spacing & Layout Grids
- **Max Width Container**: `1280px` for main content wrappers.
- **Fluid Layout**: Use `clamp()` for flexible spacing and typography on large displays down to mobile. Example: `clamp(24px, 5vw, 80px)` for padding.
- **Component Gaps**: 
  - Standard flex/grid gaps: `16px` to `24px` for cards.
  - Section spacing: `60px` to `120px` vertical padding.

### Component States & Interaction Patterns
- **Hover States**: 
  - Cards: `transform: translateY(-6px)` and enhanced `box-shadow: 0 16px 40px rgba(37, 151, 224, 0.3)`.
  - Buttons: Subtle background darken (e.g., `#64e9cc` to `#50d9bc`) and a slight scale up (`transform: scale(1.02)` / `1.05` for icon buttons).
- **Transitions**:
  - Standard smooth: `transition: 0.2s ease` or `0.25s ease`.
  - Card 3D/Bouncy: `transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94)`.
- **Card Aesthetics**: Overlapping pills, rounded borders (`12px` to `16px`), and gradient backgrounds. Ensure parent containers have adequate padding to prevent `.card:hover` clipping.

### Responsive Breakpoints
- **Desktop**: `> 1100px` (Max container `1280px`).
- **Tablet**: `≤ 1100px` (Adjusted nav and padding).
- **Small Tablet / Mobile**: `≤ 768px` (Mobile header, drawer navigation, vertical stacking).
- **Mobile Strict**: `≤ 480px` (Card stacks, 3D staggered sliders, compact fonts).

### Accessibility Standards
- Ensure sufficient color contrast on all text elements (especially white text on light-cyan backgrounds).
- Make sure buttons and links have `aria-label` where text content is implicit (e.g., icons).

## 2. WordPress Headless CMS Architecture (Vanilla JS)

To optimize the frontend for a headless WordPress / WooCommerce integration without external libraries:

### API Consumption (`assets/js/browse-specialty.js`)
- Built on native `fetch()`. Components directly hit WP REST API.
- Product category filtering: `GET /wp-json/wc/v3/products?category=<slug>&per_page=10`
- Configurable API base via `window.CC_CONFIG.apiBase`.
- All calls use standard `fetch()` with no polyfills or libraries.

### Caching Strategy
- **Session-level caching**: API responses are stored in `sessionStorage` keyed by `cc_specialty_<slug>`.
- Prevents redundant network requests within the same browser tab session.

### Dynamic Routing & In-Page Filtering
- Specialty pill links use `href="shop.html?specialty=<slug>"` but handle clicks natively via AJAX on the homepage.
- **In-page AJAX**: Clicking a pill updates the product grid via `fetch` and updates the URL using `history.pushState` without a full page reload.
- **Back/Forward support**: Uses the `popstate` event listener to sync the UI and grid content with browser navigation history.
- No heavy SPA framework — provides a seamless, fast experience using Vanilla JS and native browser APIs.

### SEO & Meta Tags
- Page `<title>` and meta description should be updated dynamically when navigating to shop.html with a `?specialty=` param.
- Pattern: `document.title = 'Shop ' + specialtyName + ' | ConsuCorner'`

### Image Optimization
- Product cards use `<picture>` + `srcset` built from WooCommerce image sizes.
- `loading="lazy"` on all product images.

### Performance Monitoring
- `performance.mark('specialty-fetch-start')` / `.mark('specialty-fetch-end')` + `.measure()` wrap every API call.

---

## 3. Component Specifications

### Product Card (`.card-shop`)
- **Width**: `242px`
- **Height**: `328px`
- **Image wrapper height**: `171px` (top area, background `#F8F9FA`)
- **Border**: `1.5px solid #DCE0E5` | Border-radius: `15px`
- **Body Padding**: `10px 16px`
- **Clean Aesthetic**: No "Company" pill; focus on product name and CTA within fixed bounds.

### Specialty Pills (`.specialty-pill`)
- Implemented as `<a>` tags — **no `<button>` tags** for these elements.
- `href="shop.html?specialty=<slug>"` for WooCommerce category filtering.
- `data-specialty="<slug>"` attribute for JS reference.
- Active state: `background: #64e9cc`.
- Width: `177.05px` | Height: `43px` | Border-radius: `37.1px`
- Padding: `10.12px 20.23px` | Gap between pills: `13px`

### "All Specialties" Button (`.btn-all-specialties`)
- Implemented as `<a>` tag.
- Width: `210px` | Height: `51px` | Border-radius: `44px`
- Font: Manrope 600, 16px

*Note: This file will be updated dynamically as the project grows and new rules or themes are established.*
