<?php

declare(strict_types=1);

/**
 * Optimized HTTP request handler for Webhook extension
 *
 * This function sends HTTP requests with proper validation, error handling,
 * and logging capabilities for the FreshRSS Webhook extension.
 *
 * @param string $url The target URL for the HTTP request
 * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
 * @param string $bodyType Content type for the request body ('json' or 'form')
 * @param string $body Request body content as JSON string
 * @param string[] $headers Array of HTTP headers
 * @param bool $logEnabled Whether logging is enabled
 * @param string $additionalLog Additional context for logging
 *
 * @throws InvalidArgumentException When invalid parameters are provided
 * @throws JsonException When JSON encoding/decoding fails
 * @throws Minz_PermissionDeniedException
 * @throws RuntimeException When cURL operations fail
 *
 * @return void
 */
function sendReq(
	string $url,
	string $method,
	string $bodyType,
	string $body,
	array $headers = [],
	bool $logEnabled = true,
	string $additionalLog = "",
): void {
	// Validate inputs
	if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
		throw new InvalidArgumentException("Invalid URL provided: {$url}");
	}

	$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
	if (!in_array(strtoupper($method), $allowedMethods, true)) {
		throw new InvalidArgumentException("Invalid HTTP method: {$method}");
	}

	$allowedBodyTypes = ['json', 'form'];
	if (!in_array($bodyType, $allowedBodyTypes, true)) {
		throw new InvalidArgumentException("Invalid body type: {$bodyType}");
	}

	$ch = curl_init($url);
	if ($ch === false) {
		throw new RuntimeException("Failed to initialize cURL session");
	}

	try {
		// Configure HTTP method
		configureHttpMethod($ch, strtoupper($method));

		// Process and set HTTP body
		$processedBody = processHttpBody($body, $bodyType, $method, $logEnabled);
		if ($processedBody !== null && $method !== 'GET') {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $processedBody);
		}

		// Configure headers
		$finalHeaders = configureHeaders($headers, $bodyType);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);

		// Log the request
		logRequest($logEnabled, $additionalLog, $method, $url, $bodyType, $processedBody, $finalHeaders);

		// Execute request
		executeRequest($ch, $logEnabled);

	} catch (Throwable $err) {
		logError($logEnabled, "Error in sendReq: {$err->getMessage()} | URL: {$url} | Body: {$body}");
		throw $err;
	} finally {
		curl_close($ch);
	}
}

/**
 * Configure cURL HTTP method settings
 *
 * Sets the appropriate cURL options based on the HTTP method.
 *
 * @param CurlHandle $ch The cURL handle
 * @param string $method HTTP method in uppercase
 *
 * @return void
 */
function configureHttpMethod(CurlHandle $ch, string $method): void {
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	switch ($method) {
		case 'POST':
			curl_setopt($ch, CURLOPT_POST, true);
			break;
		case 'PUT':
			curl_setopt($ch, CURLOPT_PUT, true);
			break;
		case 'GET':
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
			break;
		case 'DELETE':
		case 'PATCH':
		case 'OPTIONS':
		case 'HEAD':
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			break;
	}
}

/**
 * Process HTTP body based on content type
 *
 * Converts the request body to the appropriate format based on the body type.
 * Supports JSON and form-encoded data.
 *
 * @param string $body Raw body content as JSON string
 * @param string $bodyType Content type ('json' or 'form')
 * @param string $method HTTP method
 * @param bool $logEnabled Whether logging is enabled
 *
 * @throws JsonException When JSON processing fails
 * @throws InvalidArgumentException When unsupported body type is provided
 * @throws Minz_PermissionDeniedException
 *
 * @return string|null Processed body content or null if no body needed
 */
function processHttpBody(string $body, string $bodyType, string $method, bool $logEnabled): ?string {
	if (empty($body) || $method === 'GET') {
		return null;
	}

	try {
		$bodyObject = json_decode($body, true, 256, JSON_THROW_ON_ERROR);

		return match ($bodyType) {
			'json' => json_encode($bodyObject, JSON_THROW_ON_ERROR),
			'form' => http_build_query($bodyObject ?? []),
			default => throw new InvalidArgumentException("Unsupported body type: {$bodyType}")
		};
	} catch (JsonException $err) {
		logError($logEnabled, "JSON processing error: {$err->getMessage()} | Body: {$body}");
		throw $err;
	}
}

/**
 * Configure HTTP headers for the request
 *
 * Sets appropriate Content-Type headers if none are provided,
 * based on the body type.
 *
 * @param string[] $headers Array of custom headers
 * @param string $bodyType Content type ('json' or 'form')
 *
 * @return string[] Final array of headers to use
 */
function configureHeaders(array $headers, string $bodyType): array {
	if (empty($headers)) {
		return match ($bodyType) {
			'form' => ['Content-Type: application/x-www-form-urlencoded'],
			'json' => ['Content-Type: application/json'],
			default => []
		};
	}

	return $headers;
}

/**
 * Log the outgoing HTTP request details
 *
 * Logs comprehensive information about the request being sent,
 * including URL, method, body, and headers.
 *
 * @param bool $logEnabled Whether logging is enabled
 * @param string $additionalLog Additional context information
 * @param string $method HTTP method
 * @param string $url Target URL
 * @param string $bodyType Content type
 * @param string|null $body Processed request body
 * @param string[] $headers Array of HTTP headers
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function logRequest(
	bool $logEnabled,
	string $additionalLog,
	string $method,
	string $url,
	string $bodyType,
	?string $body,
	array $headers
): void {
	if (!$logEnabled) {
		return;
	}

	$cleanUrl = urldecode($url);
	$cleanBody = $body ? str_replace('\/', '/', $body) : '';
	$headersJson = json_encode($headers);

	$logMessage = trim("{$additionalLog} ♦♦ sendReq ⏩ {$method}: {$cleanUrl} ♦♦ {$bodyType} ♦♦ {$cleanBody} ♦♦ {$headersJson}");

	logWarning($logEnabled, $logMessage);
}

/**
 * Execute cURL request and handle response
 *
 * Executes the configured cURL request and handles both success
 * and error responses with appropriate logging.
 *
 * @param CurlHandle $ch The configured cURL handle
 * @param bool $logEnabled Whether logging is enabled
 *
 * @throws RuntimeException When cURL execution fails
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function executeRequest(CurlHandle $ch, bool $logEnabled): void {
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		$error = curl_error($ch);
		logError($logEnabled, "cURL error: {$error}");
		throw new RuntimeException("cURL error: {$error}");
	}

	$info = curl_getinfo($ch);
	$httpCode = $info['http_code'] ?? 'unknown';

	logWarning($logEnabled, "Response ✅ ({$httpCode}) {$response}");
}

/**
 * Log warning message using FreshRSS logging system
 *
 * Safely logs warning messages through the FreshRSS Minz_Log system
 * with proper class existence checking.
 *
 * @param bool $logEnabled Whether logging is enabled
 * @param mixed $data Data to log (will be converted to string)
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function logWarning(bool $logEnabled, $data): void {
	if ($logEnabled && class_exists('Minz_Log')) {
		Minz_Log::warning("[WEBHOOK] " . $data);
	}
}

/**
 * Log error message using FreshRSS logging system
 *
 * Safely logs error messages through the FreshRSS Minz_Log system
 * with proper class existence checking.
 *
 * @param bool $logEnabled Whether logging is enabled
 * @param mixed $data Data to log (will be converted to string)
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function logError(bool $logEnabled, $data): void {
	if ($logEnabled && class_exists('Minz_Log')) {
		Minz_Log::error("[WEBHOOK]❌ " . $data);
	}
}

/**
 * Backward compatibility alias for logWarning function
 *
 * @deprecated Use logWarning() instead
 * @param bool $logEnabled Whether logging is enabled
 * @param mixed $data Data to log
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function LOG_WARN(bool $logEnabled, $data): void {
	logWarning($logEnabled, $data);
}

/**
 * Backward compatibility alias for logError function
 *
 * @deprecated Use logError() instead
 * @param bool $logEnabled Whether logging is enabled
 * @param mixed $data Data to log
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function LOG_ERR(bool $logEnabled, $data): void {
	logError($logEnabled, $data);
}
