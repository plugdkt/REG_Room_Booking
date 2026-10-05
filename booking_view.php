<?php
// URL เดิม — ย้ายไปที่ view.php
require __DIR__ . '/inc/bootstrap.php';
redirect('view.php?ref=' . rawurlencode((string)($_GET['ref'] ?? '')));
