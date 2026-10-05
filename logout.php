<?php
require __DIR__ . '/inc/bootstrap.php';

logout_user();

if (config('auth.mode') === 'sso') {
    $logoutUrl = 'https://www.medsci.up.ac.th/msc_acc/sso/logout.php';
    $returnUrl = url('index.php');
    header('Location: ' . $logoutUrl . '?redirect_uri=' . urlencode($returnUrl));
    exit;
}

redirect('login.php');
