<?php
/**
 * JailTrak - Invite Gate (Access Control)
 * Place this at the top of protected files to require invite/session access.
 */

function checkInviteAccess() {
    if (!isset($_SESSION['invite_code']) || !$_SESSION['invite_code']) {
        // Redirect to landing page or login
        header('Location: /invite.php');
        exit;
    }
}
?>