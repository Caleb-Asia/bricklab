# Private Project Image Serving Plan

## Current state

`projects.php` validates uploaded image content, saves files in `uploads/projects/` under `bin2hex(random_bytes(16))` names, and stores `/bricklab/uploads/projects/<filename>` in `project_images.image_path`. The random name reduces guessing; it does not authorize access. `uploads/projects/.htaccess` disables directory listing and blocks executable extensions, but permits direct image requests. Therefore, changing an `<img>` URL alone would not protect a Private image whose static URL is known.

## Proposed approach

1. Keep Public image files in the current public directory and keep their current static URLs.
2. Store Private images outside the web root (or in a separately denied directory). A mixed public/private folder cannot enforce the desired policy using PHP template changes alone. Move existing Private files and update their `project_images.image_path` values as part of a separately reviewed migration; preserve Public paths.
3. Add an authenticated image route, for example `serve-image.php?project_id=<id>&image_id=<id>`. Require a logged-in user, validate positive IDs, and use a prepared query joining the image to its project with `project.user_id = current_user_id`. Return the same not-found response for missing and non-owned projects. Never accept a filesystem path from the request.
4. Resolve the stored path only beneath the private image directory (canonicalize and verify containment), require a regular file, determine the MIME type from the file, allow only the upload formats already accepted, and stream with `X-Content-Type-Options: nosniff` and private/no-store caching. Use an image ID because a project can have multiple `project_images` rows.
5. Add one URL-selection helper for image templates: Public uses the stored static URL; Private uses the authenticated route only for its owner; other viewers receive no private image URL. Keep the existing placeholder for absent/unavailable images.
6. Make the upload, replacement, and deletion paths in `projects.php` aware of the two storage locations. Ensure replacement/deletion unlinks only a validated path under the correct image root. Do not expose Unlisted through this route until its token authorization rules are implemented.

`fetch_owned_project()` currently lives in `projects.php`, which is an executable page and should not be included by the new route. Extract its ownership query into a small reusable include/helper, or implement the equivalent owner-scoped prepared query in the route. The route must not rely on a submitted project ID as proof of ownership.

## Current image URL/data references (blast radius)

These templates render `image_path` values returned as `cover_image` or `project_cover`; `includes/showcase.php` and `includes/community.php` supply those values from `project_images`.

- `includes/public-project-card.php:9-12` — shared public Build card used by Showcase and Builder profiles; select static versus authenticated source based on visibility/viewer.
- `includes/community-post-card.php:29, 90-95` — linked Build preview in community cards; the query in `includes/community.php` supplies `project_cover`.
- `build.php:22-23, 83-84` — Build detail cover. Its current page query is Public-only (`is_public = 1`), so it should remain static for Public Builds.
- `showcase.php:129` — Showcase rendering through the shared public card.
- `profile.php:153` — public project filtering; its cards render through `includes/public-project-card.php`.
- `projects.php:42-65, 492-495, 543-549, 816-819` — owner-scoped edit preview and Workshop cards; use the private route for Private Builds and preserve Public static URLs.
- `includes/showcase.php:159-164` — public Showcase query obtains `cover_image`.
- `includes/community.php:146-200, 267-294` — Grid/profile post queries supply linked Build cover paths, including owner-specific branches; ensure only the owner receives a private image URL and public feed rows never receive private paths.
- `tests/manual/cross_user_project_delete_test.php:205-210` — manual deletion test reads the saved path to check the disk file; update only if the storage layout changes and this test is meant to cover private images.

The search found no other PHP/JS/CSS literal references to `/bricklab/uploads/projects/`. Avatar URLs and the BrickLab placeholder are separate image sources and are outside this change.

## Rollout and checks

Implement storage and route authorization before switching any Private template URLs. Move existing Private files before enabling Private routing; otherwise their old static URLs remain usable. Verify owner access, logged-out and non-owner denial, Public static delivery, missing/deleted files, replacement and deletion in both directories, and that Grid/profile queries do not emit private image paths. Also confirm web-server configuration cannot execute files from either upload directory.
