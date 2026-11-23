<?php if ($isPublicShell ?? false): ?>
        </main>
        <footer class="public-footer">
            <div class="public-footer-inner">
                <a class="brand" href="/bricklab/grid.php">
                    <span class="brand-icon" aria-hidden="true">B</span>
                    <span class="brand-name">Brick<span class="brand-accent">Lab</span></span>
                </a>
                <p>From first brick to final build. Where Builders connect and showcase their work.</p>
                <div>
                    <a href="/bricklab/grid.php">The Grid</a>
                    <a href="/bricklab/showcase.php">Build Wall</a>
                    <a href="/bricklab/about.php">About</a>
                    <?php if (current_user_id() === null): ?>
                        <a href="/bricklab/register.php">Join BrickLab</a>
                    <?php endif; ?>
                </div>
                <small>&copy; <?= date('Y') ?> BrickLab</small>
            </div>
        </footer>
    </div>
</div>
<?php else: ?>
        </main>
    </div>
</div>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> BrickLab. From first brick to final build.</p>
        <span>The Workshop • The Grid • Build Wall</span>
    </div>
</footer>
<?php endif; ?>

<?php if (($currentPage ?? null) === 'dashboard'): ?>
    <script
        src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"
        defer
    ></script>
    <script
        src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"
        defer
    ></script>
    <script
        src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
        defer
    ></script>
    <script src="/bricklab/assets/js/dashboard-charts.js" defer></script>
<?php endif; ?>
<?php if (($loadAuthScene ?? false) === true): ?>
    <script
        src="/bricklab/assets/js/auth-scene.js?v=<?= filemtime(__DIR__ . '/../assets/js/auth-scene.js') ?>"
        defer
    ></script>
<?php endif; ?>
<?php if (($loadExhibitionScene ?? false) === true): ?>
    <script
        src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"
        defer
    ></script>
    <script
        src="/bricklab/assets/js/showcase-exhibition.js?v=<?= filemtime(__DIR__ . '/../assets/js/showcase-exhibition.js') ?>"
        defer
    ></script>
<?php endif; ?>
<?php if (($loadShowcaseScript ?? false) === true): ?>
    <script
        src="/bricklab/assets/js/showcase.js?v=<?= filemtime(__DIR__ . '/../assets/js/showcase.js') ?>"
        defer
    ></script>
<?php endif; ?>
<?php if (($loadCommunityScript ?? false) === true && file_exists(__DIR__ . '/../assets/js/community.js')): ?>
    <script
        src="/bricklab/assets/js/community.js?v=<?= filemtime(__DIR__ . '/../assets/js/community.js') ?>"
        defer
    ></script>
<?php endif; ?>
<script
    src="/bricklab/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"
    defer
></script>
</body>
</html>
