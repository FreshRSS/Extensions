<?php

declare(strict_types=1);

/**
 * HTTP request handler for Webhook extension
 *
 * Sends HTTP requests using the FreshRSS framework's httpGet() utility
 * with proper validation, error handling, and logging capabilities.
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
 * @throws RuntimeException When the HTTP request fails
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
	string $additionalLog = '',
): void {
	// Validate inputs
	if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
		throw new InvalidArgumentException("Invalid URL provided: {$url}");
	}

	$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
	$method = strtoupper($method);
	if (!in_array($method, $allowedMethods, true)) {
		throw new InvalidArgumentException("Invalid HTTP method: {$method}");
	}

	$allowedBodyTypes = ['json', 'form'];
	if (!in_array($bodyType, $allowedBodyTypes, true)) {
		throw new InvalidArgumentException("Invalid body type: {$bodyType}");
	}

	// Process body and headers
	$processedBody = processHttpBody($body, $bodyType, $method, $logEnabled);
	$finalHeaders = configureHeaders($headers, $bodyType);

	// Log the request
	logRequest($logEnabled, $additionalLog, $method, $url, $bodyType, $processedBody, $finalHeaders);

	// Build cURL options for httpGet()
	$curlOptions = [
		CURLOPT_HTTPHEADER => array_values($finalHeaders),
		CURLOPT_TIMEOUT => 10,
	];

	if ($method === 'POST') {
		$curlOptions[CURLOPT_POST] = true;
	} elseif ($method !== 'GET') {
		$curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
	}

	if ($processedBody !== null && $method !== 'GET') {
		$curlOptions[CURLOPT_POSTFIELDS] = $processedBody;
	}

	try {
		$response = FreshRSS_http_Util::httpGet($url, cachePath: null, type: 'json', curl_options: $curlOptions);

		if ($response['fail']) {
			logError($logEnabled, "Request failed for URL: {$url}");
			throw new RuntimeException("HTTP request failed for URL: {$url}");
		}

		logWarning($logEnabled, "Response ✅ {$response['body']}");
	} catch (RuntimeException $err) {
		throw $err;
	} catch (Throwable $err) {
		logError($logEnabled, "Error in sendReq: {$err->getMessage()} | URL: {$url} | Body: {$body}");
		throw $err;
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
			'form' => http_build_query(is_array($bodyObject) ? $bodyObject : []),
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
	$cleanBody = ($body !== null) ? str_replace('\/', '/', $body) : '';
	$headersJson = json_encode($headers);

	$logMessage = trim("{$additionalLog} ♦♦ sendReq ⏩ {$method}: {$cleanUrl} ♦♦ {$bodyType} ♦♦ {$cleanBody} ♦♦ {$headersJson}");

	logWarning($logEnabled, $logMessage);
}

/**
 * Log warning message using FreshRSS logging system
 *
 * Safely logs warning messages through the FreshRSS Minz_Log system
 * with proper class existence checking.
 *
 * @param bool $logEnabled Whether logging is enabled
 * @param string $data Data to log (will be converted to string)
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function logWarning(bool $logEnabled, string $data): void {
	if ($logEnabled && class_exists('Minz_Log')) {
		Minz_Log::warning('[WEBHOOK] ' . $data);
	}
}

/**
 * Log error message using FreshRSS logging system
 *
 * Safely logs error messages through the FreshRSS Minz_Log system
 * with proper class existence checking.
 *
 * @param bool $logEnabled Whether logging is enabled
 * @param string $data Data to log (will be converted to string)
 *
 * @throws Minz_PermissionDeniedException
 *
 * @return void
 */
function logError(bool $logEnabled, string $data): void {
	if ($logEnabled && class_exists('Minz_Log')) {
		Minz_Log::error('[WEBHOOK]❌ ' . $data);
	}
}
