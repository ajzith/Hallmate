<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to homepage (change 'index.html' if needed)
header("Location: /hallmate/frontend/homepg.html");
exit;
