<?php
// URL เดิม — ย้ายไปที่ form.php (รหัสฟอร์มเดิม alc/classroom → room_alc/room_classroom)
require __DIR__ . '/inc/bootstrap.php';
if (isset($_GET['ref'])) redirect('form.php?ref=' . rawurlencode((string)$_GET['ref']));
$map = ['alc' => 'room_alc', 'classroom' => 'room_classroom'];
redirect('form.php?form=' . ($map[$_GET['type'] ?? ''] ?? ''));
