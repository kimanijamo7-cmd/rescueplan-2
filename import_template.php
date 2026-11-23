<?php
declare(strict_types=1);

/** Blank CSV layout for partners who collect records offline. */
require_once __DIR__ . '/includes/bootstrap.php';
require_admin();

csv_download('rescueplan_import_template.csv',
    ['full_name', 'national_id', 'household_size', 'camp_location', 'phone'],
    [['Jane Wanjiru', '12345678', 4, 'Kisumu IDP Camp', '0700000000']]);
