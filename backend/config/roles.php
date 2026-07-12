<?php
return [
    'module_permissions' => [
        'Administrator' => ['dashboard','users','doctors','patients','appointments','doctor_appointments','payments','finance','audit_logs','treatments','followups','inventory','pharmacy','prescriptions','laboratory','reports','settings'],
        'Receptionist' => ['dashboard','patients','appointments','payments','reports'],
        'Doctor' => ['dashboard','patients','doctor_appointments','treatments','followups','prescriptions','reports'],
        'Inventory Officer' => ['dashboard','inventory'],
        'Pharmacy User' => ['dashboard','inventory','pharmacy','prescriptions','reports'],
        'Lab User' => ['dashboard','patients','appointments','laboratory','reports'],
    ],
    'report_permissions' => [
        'Administrator' => ['users','patients','appointments','treatments','followups','consultations','medical_history','inventory','stock_in','stock_out','low_stock','expired','payments','finance','expenses','profit_loss','audit_logs','pharmacy','pharmacy_sales','prescriptions','top_medicines','inventory_movement','doctor_performance','laboratory','activity'],
        'Receptionist' => ['patients','appointments','payments'],
        'Doctor' => ['treatments','followups','consultations','medical_history'],
        'Inventory Officer' => ['inventory','stock_in','stock_out','low_stock','expired','inventory_movement'],
        'Pharmacy User' => ['pharmacy','pharmacy_sales','prescriptions','top_medicines','low_stock','expired','inventory_movement'],
        'Lab User' => ['laboratory','patients','appointments'],
    ],
];
