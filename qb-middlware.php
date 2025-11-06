<?php
function  custom_log($msg) {
    file_put_contents(__DIR__ . '/qbwc_debug.log', "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

// --- START: Proxy forward to another connector (paste near top, before SOAP routing) ---
$requestBody = file_get_contents("php://input");

// Configure remote connector URL (change as needed)
$remote_url = 'https://shop.ballettechglobal.com/wp-json/wc/v3/orders';

// Prepare headers to forward (preserve Content-Type and any QBWC headers)
$forward_headers = [];
if (!empty($_SERVER['CONTENT_TYPE'])) {
    custom_log("Forwarding Content-Type: " . $_SERVER['CONTENT_TYPE']);
    $forward_headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
} else {
    custom_log("No Content-Type header found; defaulting to text/xml");
    $forward_headers[] = 'Content-Type: text/xml';
}

// Forward basic custom headers if present (optional)
$possible_headers = ['HTTP_USER_AGENT','HTTP_ACCEPT','HTTP_ACCEPT_ENCODING','HTTP_AUTHORIZATION'];
foreach ($possible_headers as $h) {
    if (!empty($_SERVER[$h])) {
        $name = str_replace('HTTP_', '', $h);
        $name = str_replace('_', '-', $name);
        $forward_headers[] = $name . ': ' . $_SERVER[$h];
    }
}

// cURL: forward the raw body and capture response headers + body
$ch = curl_init($remote_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // we want headers + body
curl_setopt($ch, CURLOPT_HTTPHEADER, $forward_headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // set to false only for testing (not recommended)
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// execute
$response = curl_exec($ch);
$curl_errno = curl_errno($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($curl_errno) {
    // log error and fall back to local handling
    custom_log("Proxy to $remote_url failed: [$curl_errno] $curl_error");
    // do NOT exit — continue to local SOAP routing below
} else {
    // Separate headers and body returned from remote
    $resp_headers_raw = substr($response, 0, $header_size);
    $resp_body = substr($response, $header_size);

    // attempt to pick Content-Type from remote headers to send back
    $resp_headers_lines = preg_split("/\r\n|\n|\r/", $resp_headers_raw);
    $content_type_to_send = null;
    foreach ($resp_headers_lines as $hline) {
        if (stripos($hline, 'Content-Type:') === 0) {
            $content_type_to_send = trim(substr($hline, strlen('Content-Type:')));
            break;
        }
    }

    if ($content_type_to_send) {
        header('Content-Type: ' . $content_type_to_send);
    } else {
        // default if remote didn't provide
        header('Content-Type: text/xml');
    }

    // optional: forward HTTP status code (QBWC expects 200 usually)
    // but we will still output the response body as-is
    http_response_code($http_code ?: 200);
    custom_log("request body data" . $requestBody);
    custom_log("reposne body data" . $resp_body);

    // log the proxied request + response for debugging
    custom_log("Proxied request to $remote_url; HTTP code: $http_code; request length: " . strlen($requestBody) . "; response length: " . strlen($resp_body));

    // echo remote body and exit — this returns remote connector's response to the QBWC
    echo $resp_body;
    exit; // Important: stop local processing because remote handled it
}
// --- END: Proxy forward to another connector ---
?>