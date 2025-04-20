<?php
header('Content-Type: application/json');

$sriLankanSchools = [
    'Royal College Colombo',
    'Visakha Vidyalaya',
    'Ananda College',
    'Nalanda College',
    'St. Thomas\' College',
    'St. Bridget\'s Convent',
    'St. Joseph\'s College',
    'Ladies\' College',
    'Devi Balika Vidyalaya',
    'Mahanama College',
    'D.S. Senanayake College',
    'Isipathana College',
    'Thurstan College',
    'St. Peter\'s College',
    'Wesley College'
];

$term = isset($_GET['term']) ? strtolower(trim($_GET['term'])) : '';
$suggestions = [];

if (strlen($term) >= 2) {
    foreach ($sriLankanSchools as $school) {
        if (stripos($school, $term) !== false) {
            $suggestions[] = $school;
        }
    }
}

echo json_encode($suggestions);
