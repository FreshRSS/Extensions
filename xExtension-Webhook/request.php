<?php

function sendReq(
    string $url,
    string $method,
    string $bodyType,
    string $body,
    array $headers = [],
    bool $logEnabled = true,
    string $additionalLog = "",
): void {
    // LOG_WARN($logEnabled, "> sendReq ⏩ ( {$method}: " . $url . ", " . $bodyType . ", " . json_encode($body));

    /** @var CurlHandle $ch */
    $ch = curl_init($url);
    try {
        // ----------------------[ HTTP Method: ]-----------------------------------

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        if ($method === "PUT") {
            curl_setopt($ch, CURLOPT_PUT, true);
        }
        if ($method === "GET") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        }
        if ($method === "DELETE") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        // ----------------------[ HTTP Body: ]-----------------------------------

        $bodyObject = null;
        $bodyToSend = null;
        try {
            // $bodyObject = json_decode(json_encode($body ?? ""), true, 64, JSON_THROW_ON_ERROR);
            $bodyObject = json_decode(($body ?? "{}"), true, 256, JSON_THROW_ON_ERROR);

            // LOG_WARN($logEnabled, "bodyObject: " . json_encode($bodyObject));

            if ($bodyType === "json") {
                $bodyToSend = json_encode($bodyObject);
                // LOG_WARN($logEnabled, "> json_encode ⏩: {$bodyToSend}");
            }
            if ($bodyType === "form") {
                $bodyToSend = http_build_query($bodyObject);
                // LOG_WARN($logEnabled, "> http_build_query ⏩: " . $body);
            }

            if (!empty($body) && $method !== "GET") {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyToSend);
            }

        } catch (Throwable $err) {
            LOG_ERR($logEnabled, "ERROR during parsing HTTP Body, ERROR: {$err} | Body: {$body}");
            LOG_ERR($logEnabled, "ERROR during parsing HTTP Body, ERROR: " . json_encode($err) . "| Body: {$body}");
            throw $err;
        }

        // LOG_WARN($logEnabled, "> sendReq ⏩ {$method}: {$url}  ♦♦  {$bodyType}  ♦♦  {$bodyToSend}");

        // ----------------------[ HTTP Headers: ]-----------------------------------

        if (empty($headers)) {
            if ($bodyType === "form") {
                array_push($headers, "Content-Type: application/x-www-form-urlencoded");
            }
            if ($bodyType === "json") {
                array_push($headers, "Content-Type: application/json");
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        LOG_WARN($logEnabled, "{$additionalLog} ♦♦ sendReq ⏩ {$method}: " . urldecode($url) ." ♦♦  {$bodyType}  ♦♦  " . str_replace("\/", "/", $bodyToSend) . "  ♦♦  " . json_encode($headers));

        // ----------------------[ 🚀 SEND Request! ]-----------------------------------

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        // ----------------------[ Check for errors: ]-----------------------------------

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            LOG_ERR($logEnabled, "< ERROR: " . $error);
        } else {
            LOG_WARN($logEnabled, "< Response ✅ (" . $info["http_code"] . ") response:" . $response);
        }

    } catch (Throwable $err) {
        LOG_ERR($logEnabled, "< ERROR in sendReq: " . $err . " ♦♦ body: {$body} ♦♦");
    } finally {
        // Close the cURL session
        curl_close($ch);
    }
}

function LOG_WARN(bool $logEnabled, $data): void {
    if ($logEnabled) {
        Minz_Log::warning("[WEBHOOK] " . $data);
    }
}

function LOG_ERR(bool $logEnabled, $data): void {
    if ($logEnabled) {
        Minz_Log::error("[WEBHOOK]❌ " . $data);
    }
}
