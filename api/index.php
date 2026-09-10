<?php
/**
 * Oyejo Gas - API stub. JSON endpoints (wallet, spin, tracking, PWA)
 * arrive with their phases. Standalone: no session, no HTML.
 */
header('Content-Type: application/json');
http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'API endpoints arrive in later phases.']);
