<?php

const VERSION = 0.1;
const TYPE_GIT = 'git';

$extensions = [];

// --------------------------------------------------------------- //
// Parse the repositories.json file to extract extension locations //
// --------------------------------------------------------------- //
$repositories = json_decode(file_get_contents('repositories.json'), true);
if (JSON_ERROR_NONE !== json_last_error()) {
	echo 'The repositories.json file is not a valid JSON file.', PHP_EOL;
	die;
}

$gitRepositories = [];
foreach ($repositories as $repository) {
	if (null === $url = $repository['url'] ?? null) {
		continue;
	}
	if (TYPE_GIT === $repository['type'] ?? null) {
		$gitRepositories[] = $url;
	}
}

// ---------------------------------------- //
// Clone git repository to extract metadata //
// ---------------------------------------- //
foreach ($gitRepositories as $key => $gitRepository) {
	echo 'Processing ', $gitRepository, ' repository', PHP_EOL;
	exec("git clone --quiet --single-branch --depth 1 --no-tags {$gitRepository} /tmp/extensions/{$key}");

	unset($metadataFiles);
	exec("find /tmp/extensions/{$key} -iname metadata.json", $metadataFiles);
	foreach ($metadataFiles as $metadataFile) {
		$metadata = json_decode(file_get_contents($metadataFile), true);
		if (JSON_ERROR_NONE !== json_last_error()) {
			continue;
		}
		$metadata['url'] = $gitRepository;
		$metadata['method'] = TYPE_GIT;
		$metadata['directory'] = basename(dirname($metadataFile));
		$extensions[] = $metadata;
	}
}

// --------------- //
// Generate output //
// --------------- //
usort($extensions, function ($a, $b) {
	return $a['name'] <=> $b['name'];
});
$output = [
	'version' => VERSION,
	'extensions' => $extensions,
];
file_put_contents('extensions.json', json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL);

echo PHP_EOL;
echo \count($extensions), ' extensions found', PHP_EOL;
