<?php

$url = 'https://artisanslms.onrender.com/backend/api/export_student_performance.php?action=get_overview';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: 0fvBAvRhGAkES6QVHXYojIVDQq5iPiRl']);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
echo "hello";
?> 