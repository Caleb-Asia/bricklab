            </main>
            <footer class="public-footer">
                <div class="public-footer-inner">
                    <a class="brand" href="/bricklab/showcase.php">
                        <span class="brand-icon" aria-hidden="true">B</span>
                        <span class="brand-name">Brick<span class="brand-accent">Lab</span></span>
                    </a>
                    <p>Build. Track. Showcase. A place for developers to share what they make.</p>
                    <div>
                        <a href="/bricklab/showcase.php">Showcase</a>
                        <a href="/bricklab/developers.php">Developers</a>
                        <a href="/bricklab/about.php">About</a>
                        <a href="/bricklab/register.php">Join BrickLab</a>
                    </div>
                    <small>&copy; <?= date('Y') ?> BrickLab</small>
                </div>
            </footer>
        </div>
    </div>
    <?php if (($loadAuthScene ?? false) === true): ?>
        <script
            src="/bricklab/assets/js/auth-scene.js?v=<?= filemtime(__DIR__ . '/../assets/js/auth-scene.js') ?>"
            defer
        ></script>
    <?php endif; ?>
    <?php if (($loadShowcaseScript ?? false) === true): ?>
        <script
            src="/bricklab/assets/js/showcase.js?v=<?= filemtime(__DIR__ . '/../assets/js/showcase.js') ?>"
            defer
        ></script>
    <?php endif; ?>
    <script
        src="/bricklab/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"
        defer
    ></script>
</body>
</html>
