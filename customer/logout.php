<?php
/**
 * Oyejo Gas - logout (Phase 5). Destroys the session completely.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
reject_path_info();
auth_logout();
