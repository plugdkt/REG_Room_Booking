<?php
require __DIR__ . '/inc/bootstrap.php';

logout_user();

if (config('auth.mode') === 'sso') {
    $logoutUrl = (string)config('auth.sso.logout_url', 'https://www.medsci.up.ac.th/msc_acc/sso/logout.php');
    redirect($logoutUrl . '?redirect_uri=' . urlencode(url('index.php')));
}

redirect('login.php');
