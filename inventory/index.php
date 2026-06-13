<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
redirect('/inventory/medicines.php');
