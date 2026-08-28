<?php
declare(strict_types=1);

/* Jednotne dokonceni standardniho formulare pred vystupem HTML. */

function cb_form_return_url(string $fallback): string
{
    $return = trim((string)($_POST['cb_return'] ?? ''));
    if ($return === '' || str_starts_with($return, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $return) === 1) {
        return $fallback;
    }
    return $return;
}

function cb_form_finish(string $fallback, bool $success, string $message, array $input = [], array $errors = []): never
{
    $_SESSION['cb_form_result'] = [
        'success' => $success,
        'message' => $message,
        'input' => $success ? [] : $input,
        'errors' => $success ? [] : $errors,
    ];

    $returnUrl = cb_form_return_url($fallback);
    if (isset($_SERVER['HTTP_X_COMEBACK_FORM'])) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'redirect' => $returnUrl,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Location: ' . $returnUrl, true, 303);
    exit;
}
