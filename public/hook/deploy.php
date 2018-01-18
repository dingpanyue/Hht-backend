<?php #!/usr/bin/env /usr/bin/php
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    file_put_contents('/var/www/hht-backend/storage/logs/github.txt', file_get_contents('php://input'));
    //$payload = json_decode($_REQUEST['payload']);
}
catch(Exception $e) {
    //log the error
    file_put_contents('/var/www/hht-backend/storage/logs/github.txt', $e . ' ' . $payload, FILE_APPEND);
    exit(0);
}

if ($payload['ref'] === 'refs/heads/develop') {

    $project_directory = '/var/www/hht-backend';

    //$output = shell_exec("/var/www/qadoor/qadoor_site/public/hook/deploy.sh");
    $output = exec("cd /var/www/hht-backend && /usr/bin/git pull");

    //log the request
    file_put_contents('/var/www/hht-backend/storage/logs/github.txt', $output, FILE_APPEND);

}
?>