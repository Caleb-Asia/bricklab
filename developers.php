<?php

require_once __DIR__ . '/includes/auth.php';

// Developers directory has been combined with Showcase into Community
header('Location: /bricklab/community.php', true, 301);
exit;

