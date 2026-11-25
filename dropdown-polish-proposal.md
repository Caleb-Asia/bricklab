# BrickLab Dropdown Polish Proposal

## Inventory

The codebase has 15 native `<select>` controls across the Workshop and public community: `index.php` (chart period), `grid.php` (linked Build), `profile.php` (Build Wall sort), `projects.php` (category and status), `showcase.php` (technology, category and sort), `tasks.php` (three task form controls and three filters), and `includes/community-post-card.php` (Build Log type editor).

These are native selects, not custom popup menus. Their closed controls are styled inconsistently: project/task forms and Showcase filters have dark surfaces and focus styling, while chart, community composer, edit-card, and profile sort controls have partial or isolated rules. Native option menus are rendered by the browser/OS, so reliably animating their opening is not possible without replacing them with custom controls.

Other related interactions: the mobile sidebar is an animated navigation drawer (`includes/header.php`, `assets/js/app.js`, `assets/css/style.css`); Build Log editing uses native `<details>` in `includes/community-post-card.php`. There is no shared dropdown-menu component. Global reduced-motion rules already shorten transitions and disable page-transition animations.

## Recommended approach

1. **Unify native selects first.** Add one shared select treatment in `assets/css/style.css`: consistent height, padding, dark surface, border, focus ring, chevron, disabled state, and hover/focus transitions. Apply it to all 15 controls without changing form names, values, submission behavior, or native keyboard support. Leave the native option popup intact.
2. **Use custom menus only where a true animated popup is useful.** If a future navigation/action menu needs animation, build one small accessible disclosure pattern with a button, `aria-expanded`, Escape/outside-click closing, and focus management. Animate the panel with a short opacity/translate transition, with the existing reduced-motion rules respected. Don’t convert form selects merely to animate them.
3. **Polish existing disclosures separately.** Keep the mobile navigation drawer and `<details>` editor semantics; tune their shared timing and open-state styling only if needed after visual review.

## Likely implementation files

- `assets/css/style.css` — shared native select appearance, focus/disabled states, reduced-motion-safe transitions, and any shared menu-panel styles.
- `assets/js/app.js` — only if a reusable custom popup menu is introduced; existing mobile sidebar behavior already lives here.
- `includes/header.php` and the relevant control templates — add shared classes only if selectors cannot be applied safely by element/context.

No JavaScript dependency is recommended. Preserve native selects unless a specific control has a demonstrated need for searchable or animated popup behavior.
