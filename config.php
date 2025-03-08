<?php
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);

define('OPENEMR_API_URL', 'https://openemr-domain/apis/default/api/');
define('CLIENT_ID', 'TU CLIENTE ID');
define('CLIENT_SECRET', 'TU CLIENTE SECRET');
define('REDIRECT_URI', 'https://client-domain/api/api.php');
define('AUTH_URL', 'https://openemr-domain/oauth2/default/authorize');
define('TOKEN_URL', 'https://openemr-domain/oauth2/default/token');

$scopes = [
    'openid',
    'patient/Patient.read',
    'user/Patient.write',
    'user/Patient.read'
];
?>