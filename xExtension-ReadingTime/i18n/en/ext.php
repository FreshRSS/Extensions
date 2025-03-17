<?php

return array(
	'reading_time' => array(
		'speed' => array(
			'label' => 'Reading speed',
			'help' => 'Conversion factor to reading time from metrics',
			'invalid' => 'Reading speed must be greater than 0',
		),
		'source' => array(
			'label' => 'Source metrics',
			'help' => 'Source of reading time calculation',
			'words' => 'Number of words',
			'letters' => 'Number of letters',
			'invalid' => 'Unsupported source metrics',
		),
	),
);
