<?php
// Wrap this in ob because Cassis outputs to stdout when loaded
ob_start();
require_once __DIR__ . '/../vendor/autoload.php';
ob_end_clean();
