const roles = ['Administrator', 'Receptionist', 'Doctor', 'Inventory Officer', 'Pharmacy User', 'Lab User'];
const statuses = ['Active', 'Inactive'];
const doctorSpecializations = [
  'Hair Transplant Surgeon',
  'FUE Hair Transplant Specialist',
  'DHI Hair Transplant Specialist',
  'Dermatologist',
  'Hair Loss Specialist',
  'PRP Specialist',
  'Scalp Treatment Specialist',
  'Cosmetic Hair Transplant Surgeon',
];
const doctorQualifications = [
  'MBBS',
  'MBBS, MD Dermatology',
  'MD Dermatology',
  'Fellowship in Hair Restoration',
  'Board Certified Dermatologist',
  'Cosmetic Surgery Fellowship',
];
const field = (name, label, type = 'text', extra = {}) => ({ name, label, type, ...extra });

export const modules = {
  users: {
    endpoint: '/users', columns: ['full_name', 'username', 'role', 'status'],
    labels: { full_name: 'Full Name', username: 'Username', role: 'Role', status: 'Status' },
    fields: [field('full_name', 'Full Name'), field('username', 'Username'), field('role', 'Role', 'select', { options: roles }), field('password', 'Password', 'password'), field('status', 'Status', 'select', { options: statuses })],
  },
  doctors: {
    endpoint: '/doctors', columns: ['full_name', 'specialization', 'phone', 'consultation_fee', 'license_number', 'status'],
    labels: { full_name: 'Doctor', specialization: 'Specialization', phone: 'Phone', consultation_fee: 'Fee', license_number: 'License', status: 'Status' },
    fields: [field('user_id', 'Doctor User Account', 'lookup', { lookup: 'doctor_users' }), field('specialization', 'Specialization', 'select', { options: doctorSpecializations }), field('qualification', 'Qualification', 'select', { options: doctorQualifications }), field('phone', 'Phone'), field('consultation_fee', 'Consultation Fee', 'number', { min: 0 }), field('email', 'Email', 'email'), field('license_number', 'License Number'), field('experience_years', 'Experience (years)', 'number', { step: 1, min: 0, max: 80 }), field('bio', 'Biography', 'textarea'), field('status', 'Status', 'select', { options: statuses }), field('photo', 'Photo', 'file')],
  },
  patients: {
    endpoint: '/patients', columns: ['patient_code', 'full_name', 'phone', 'email', 'gender', 'created_at'],
    labels: { patient_code: 'Patient ID', full_name: 'Patient Name', phone: 'Phone', email: 'Email', gender: 'Gender', created_at: 'Registered' },
    fields: [field('full_name', 'Full Name'), field('phone', 'Phone'), field('email', 'Email', 'email'), field('gender', 'Gender', 'select', { options: ['Male', 'Female'] }), field('date_of_birth', 'Date of Birth', 'date', { max: new Date().toISOString().slice(0, 10) }), field('age', 'Age (calculated)', 'number', { step: 1, min: 0, max: 120, readOnly: true }), field('address', 'Address'), field('assigned_doctor_id', 'Assigned Doctor', 'lookup', { lookup: 'doctors' }), field('medical_notes', 'Medical Notes', 'textarea')],
  },
  appointments: {
    endpoint: '/appointments', columns: ['patient.full_name', 'doctor.full_name', 'appointment_date', 'appointment_time', 'reason', 'status'],
    labels: { 'patient.full_name': 'Patient', 'doctor.full_name': 'Doctor', appointment_date: 'Date', appointment_time: 'Time', reason: 'Reason', status: 'Status' },
    createDefaults: { patient_mode: 'existing' },
    fields: [field('patient_mode', 'Patient Mode', 'hidden'), field('patient_id', 'Patient', 'lookup', { lookup: 'patients' }), field('doctor_id', 'Doctor', 'lookup', { lookup: 'doctors' }), field('appointment_date', 'Date', 'date'), field('appointment_time', 'Time', 'time'), field('reason', 'Reason'), field('status', 'Status', 'select', { options: ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'], editOnly: true }), field('notes', 'Notes', 'textarea', { editOnly: true })],
  },
  treatments: {
    endpoint: '/treatments', columns: ['patient.full_name', 'treatment_name', 'treatment_date', 'treatment_stage', 'progress', 'cost'],
    labels: { 'patient.full_name': 'Patient', treatment_name: 'Treatment', treatment_date: 'Date', treatment_stage: 'Stage', progress: 'Progress', cost: 'Cost' },
    fields: [field('patient_id', 'Patient', 'lookup', { lookup: 'patients' }), field('treatment_name', 'Treatment Name'), field('treatment_date', 'Date', 'date'), field('treatment_stage', 'Stage', 'select', { options: ['Pre-Treatment Evaluation', 'Surgery', 'Post-Treatment Review'] }), field('progress', 'Progress', 'select', { options: ['Started', 'In Progress', 'Completed'] }), field('cost', 'Cost', 'number'), field('grafts_planned', 'Grafts Planned', 'number'), field('grafts_extracted', 'Grafts Extracted', 'number'), field('grafts_implanted', 'Grafts Implanted', 'number'), field('notes', 'Notes', 'textarea'), field('pre_op_photo', 'Pre-op Photo', 'file'), field('post_op_photo', 'Post-op Photo', 'file')],
  },
  followups: {
    endpoint: '/followups', columns: ['patient.full_name', 'treatment.treatment_name', 'followup_date', 'status', 'result'],
    labels: { 'patient.full_name': 'Patient', 'treatment.treatment_name': 'Treatment', followup_date: 'Date', status: 'Status', result: 'Result' },
    fields: [field('patient_id', 'Patient', 'lookup', { lookup: 'patients' }), field('treatment_id', 'Treatment', 'lookup', { lookup: 'treatments' }), field('followup_date', 'Date', 'date'), field('status', 'Status', 'select', { options: ['Scheduled', 'Done', 'Missed'] }), field('result', 'Result / Notes', 'textarea')],
  },
  payments: {
    endpoint: '/payments', noEdit: true, columns: ['patient.full_name', 'amount', 'payment_method', 'payment_status', 'reference_number', 'created_at'],
    labels: { 'patient.full_name': 'Patient', amount: 'Amount', payment_method: 'Method', payment_status: 'Status', reference_number: 'Reference', notes: 'Message', created_at: 'Date' },
    createDefaults: { payment_method: 'Cash', payment_status: 'Paid' },
    fields: [field('patient_id', 'Patient', 'lookup', { lookup: 'patients' }), field('appointment_id', 'Appointment', 'lookup', { lookup: 'appointments' }), field('amount', 'Amount', 'number', { min: 0.01, step: 0.01 }), field('payment_method', 'Payment Method', 'select', { options: ['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer'] }), field('payment_status', 'Payment Status', 'select', { options: ['Paid', 'Partial', 'Outstanding'] }), field('reference_number', 'Reference Number'), field('account_no', 'Mobile Account'), field('notes', 'Message / Description', 'textarea')],
  },
  finance: {
    endpoint: '/expenses', payloadKey: 'expenses', columns: ['expense_date', 'category', 'vendor', 'description', 'amount'],
    labels: { expense_date: 'Date', category: 'Category', vendor: 'Vendor', description: 'Description', amount: 'Amount' },
    fields: [field('expense_date', 'Expense Date', 'date'), field('category', 'Category', 'select', { options: ['Staff Salaries', 'Medical Supplies', 'Medicine Purchases', 'Rent', 'Electricity', 'Water', 'Internet', 'Equipment Maintenance', 'Administrative Expenses', 'Miscellaneous Expenses'] }), field('amount', 'Amount', 'number'), field('vendor', 'Vendor'), field('description', 'Description', 'textarea'), field('receipt', 'Receipt', 'file')],
  },
  inventory: {
    endpoint: '/medicines', columns: ['medicine_name', 'category', 'quantity', 'reorder_level', 'unit_price', 'expiry_date', 'supplier'],
    labels: { medicine_name: 'Medicine', category: 'Category', quantity: 'Stock', reorder_level: 'Reorder At', unit_price: 'Price', expiry_date: 'Expiry', supplier: 'Supplier' },
    fields: [field('medicine_name', 'Medicine Name'), field('generic_name', 'Generic Name'), field('category', 'Category'), field('batch_number', 'Batch Number'), field('barcode', 'Barcode'), field('quantity', 'Quantity', 'number'), field('reorder_level', 'Reorder Level', 'number'), field('unit_price', 'Unit Price', 'number'), field('expiry_date', 'Expiry Date', 'date'), field('supplier', 'Supplier'), field('description', 'Description', 'textarea')],
  },
  suppliers: {
    endpoint: '/suppliers', columns: ['company_name', 'contact_person', 'phone', 'email', 'address'],
    labels: { company_name: 'Company', contact_person: 'Contact Person', phone: 'Phone', email: 'Email', address: 'Address' },
    fields: [field('company_name', 'Company Name'), field('contact_person', 'Contact Person'), field('phone', 'Phone'), field('email', 'Email', 'email'), field('address', 'Address', 'textarea')],
  },
};
