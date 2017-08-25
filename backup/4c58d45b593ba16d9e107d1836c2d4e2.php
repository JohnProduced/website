<?php
echo json_encode(array(
	'type' => 'domain search marker',
	'host' => $_SERVER['HTTP_HOST'],
	'file' => __FILE__,
));