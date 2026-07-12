<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed if users table is empty
        if (DB::table('users')->count() > 0) {
            $this->command->info('Tables already have data, skipping seed.');
            return;
        }

        $hash = Hash::make('Admin@123');

        // Users
        DB::table('users')->insert([
            ['username' => 'admin', 'password' => $hash, 'full_name' => 'Tatiko Admin', 'role' => 'Administrator', 'status' => 'Active', 'created_at' => now()],
            ['username' => 'receptionist', 'password' => $hash, 'full_name' => 'Sara Ahmed', 'role' => 'Receptionist', 'status' => 'Active', 'created_at' => now()],
            ['username' => 'doctor', 'password' => $hash, 'full_name' => 'Dr. Mohamed Ali', 'role' => 'Doctor', 'status' => 'Active', 'created_at' => now()],
            ['username' => 'inventory', 'password' => $hash, 'full_name' => 'Hassan Omar', 'role' => 'Inventory Officer', 'status' => 'Active', 'created_at' => now()],
            ['username' => 'pharmacy', 'password' => $hash, 'full_name' => 'Fatima Yusuf', 'role' => 'Pharmacy User', 'status' => 'Active', 'created_at' => now()],
            ['username' => 'lab', 'password' => $hash, 'full_name' => 'Lab Technician', 'role' => 'Lab User', 'status' => 'Active', 'created_at' => now()],
        ]);

        $doctorUserId = DB::table('users')->where('username', 'doctor')->value('id');

        // Doctor profile
        DB::table('doctors')->insert([
            'user_id' => $doctorUserId,
            'full_name' => 'Dr. Mohamed Ali',
            'specialization' => 'Hair Loss Specialist',
            'qualification' => 'MBBS, MD Dermatology',
            'phone' => '+252 61 234 5678',
            'email' => 'dr.mohamed@hairclinic.com',
            'license_number' => 'HC-MD-1001',
            'experience_years' => 12,
            'bio' => 'Specialist in FUE and FUT hair transplantation with over 12 years of clinical experience.',
            'status' => 'Active',
            'created_at' => now(),
        ]);

        $doctorId = DB::table('doctors')->where('license_number', 'HC-MD-1001')->value('id');

        // Doctor schedules
        $days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
        foreach ($days as $day) {
            DB::table('doctor_schedules')->insert([
                'doctor_id' => $doctorId,
                'day_of_week' => $day,
                'start_time' => '08:00:00',
                'end_time' => '11:00:00',
                'slot_minutes' => 24,
                'is_working' => in_array($day, ['Saturday','Sunday','Monday','Tuesday','Wednesday']) ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Patients (8)
        $patientNames = [
            ['full_name' => 'Ahmed Hassan', 'phone' => '0615551001', 'gender' => 'Male'],
            ['full_name' => 'Fatima Omar', 'phone' => '0615551002', 'gender' => 'Female'],
            ['full_name' => 'Ali Mohamed', 'phone' => '0615551003', 'gender' => 'Male'],
            ['full_name' => 'Halima Abdi', 'phone' => '0615551004', 'gender' => 'Female'],
            ['full_name' => 'Yusuf Ibrahim', 'phone' => '0615551005', 'gender' => 'Male'],
            ['full_name' => 'Mariam Aden', 'phone' => '0615551006', 'gender' => 'Female'],
            ['full_name' => 'Abdi Rashid', 'phone' => '0615551007', 'gender' => 'Male'],
            ['full_name' => 'Amina Warsame', 'phone' => '0615551008', 'gender' => 'Female'],
        ];
        foreach ($patientNames as $p) {
            DB::table('patients')->insert(array_merge($p, [
                'email' => null,
                'date_of_birth' => '1990-01-15',
                'address' => 'Mogadishu, Somalia',
                'medical_notes' => null,
                'assigned_doctor_id' => $doctorId,
                'created_at' => now(),
            ]));
        }

        // Medicines (4)
        DB::table('medicines')->insert([
            ['medicine_name' => 'Minoxidil 5%', 'generic_name' => 'Minoxidil', 'category' => 'Topical', 'quantity' => 100, 'unit_price' => 15.00, 'expiry_date' => '2027-12-31', 'reorder_level' => 10, 'supplier' => 'MedSupply Co', 'created_at' => now()],
            ['medicine_name' => 'Finasteride 1mg', 'generic_name' => 'Finasteride', 'category' => 'Oral', 'quantity' => 200, 'unit_price' => 8.50, 'expiry_date' => '2027-06-30', 'reorder_level' => 20, 'supplier' => 'PharmaCorp', 'created_at' => now()],
            ['medicine_name' => 'Biotin 5000mcg', 'generic_name' => 'Biotin', 'category' => 'Supplement', 'quantity' => 150, 'unit_price' => 5.00, 'expiry_date' => '2028-03-31', 'reorder_level' => 15, 'supplier' => 'VitaHealth', 'created_at' => now()],
            ['medicine_name' => 'Ketoconazole Shampoo', 'generic_name' => 'Ketoconazole', 'category' => 'Topical', 'quantity' => 80, 'unit_price' => 12.00, 'expiry_date' => '2027-09-30', 'reorder_level' => 10, 'supplier' => 'MedSupply Co', 'created_at' => now()],
        ]);

        // Suppliers (3)
        DB::table('suppliers')->insert([
            ['company_name' => 'MedSupply Co', 'contact_person' => 'John Smith', 'phone' => '+252611000001', 'email' => 'info@medsupply.com', 'address' => 'Mogadishu', 'created_at' => now()],
            ['company_name' => 'PharmaCorp', 'contact_person' => 'Jane Doe', 'phone' => '+252611000002', 'email' => 'sales@pharmacorp.com', 'address' => 'Nairobi', 'created_at' => now()],
            ['company_name' => 'VitaHealth', 'contact_person' => 'Ali Hassan', 'phone' => '+252611000003', 'email' => 'orders@vitahealth.com', 'address' => 'Dubai', 'created_at' => now()],
        ]);

        DB::table('lab_tests')->insert([
            ['test_name' => 'Hair Mineral Analysis', 'category' => 'Hair & Scalp Lab', 'price' => 20.00, 'sample_type' => 'Hair Sample', 'status' => 'Active', 'description' => 'Checks mineral imbalance related to hair loss.', 'created_at' => now(), 'updated_at' => now()],
            ['test_name' => 'Vitamin D Test', 'category' => 'Blood Test', 'price' => 15.00, 'sample_type' => 'Blood', 'status' => 'Active', 'description' => 'Vitamin D level screening.', 'created_at' => now(), 'updated_at' => now()],
            ['test_name' => 'Thyroid Profile', 'category' => 'Hormone Test', 'price' => 25.00, 'sample_type' => 'Blood', 'status' => 'Active', 'description' => 'TSH/T3/T4 screening for hair loss evaluation.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->command->info('Database seeded successfully!');
    }
}
