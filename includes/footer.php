
        </main>
    </div>
</div>

<footer class="site-footer">
    <div class="container footer-inner">
        <p>
            &copy; <?= date('Y') ?> BrickLab. Build. Track. Showcase.
        </p>
        <span>Every great build starts with one brick.</span>
    </div>
</footer>

<?php if (($currentPage ?? null) === 'dashboard'): ?>
    <script
        src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"
        defer
    ></script>
<?php endif; ?>
<script src="/bricklab/assets/js/app.js" defer></script>
</body>
</html>
