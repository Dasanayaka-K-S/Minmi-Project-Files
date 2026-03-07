<?php
// ============================================================
//  LOGOUT — Minmi Restaurent Admin
//  Place in: dashboard/logout.php
// ============================================================
session_start();
session_destroy();
header('Location: login.php');
exit;
