<?php
/* =========================
   0c) Touch aktivity (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_TOUCH'])
) {
    if (empty($_SESSION['login_ok']) || !cb_session_validate_after_login()) {
        cb_session_invalidate_auth();
        http_response_code(401);
        exit;
    }

    cb_session_touch_activity();
    http_response_code(204);
    exit;
}
