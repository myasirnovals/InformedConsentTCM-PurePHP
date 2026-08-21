<?php
require_once __DIR__ . '/../libs/bootstrap.php';
header('Content-Type: application/json');

$storageDir = getTcmStorageDir();
$sigDir = $storageDir . '/signatures';
$pdfDir = $storageDir . '/pdf';
$logDir = $storageDir . '/logs';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    $pdo = getTcmDatabase();

    // 1. Generate unique consent ID
    $consentId = bin2hex(random_bytes(16));
    $lang = $_POST['lang'] ?? 'en';
    $consentVersion = '2026.01';

    // 2. Validate essential fields
    $patientName = trim($_POST['patient_name'] ?? '');
    $patientNric = trim($_POST['patient_nric'] ?? '');
    if (empty($patientName) || empty($patientNric)) {
        throw new Exception("Patient Name and NRIC are required.");
    }

    $pdo->beginTransaction();

    // 3. Insert into consent_forms
    $stmt = $pdo->prepare("INSERT INTO consent_forms (id, status, language, consent_version) VALUES (?, 'completed', ?, ?)");
    $stmt->execute([$consentId, $lang, $consentVersion]);

    // 4. Insert into patients
    $stmt = $pdo->prepare("INSERT INTO patients (consent_id, name, nric, address, postal_code, contact_number, gender, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $consentId,
        $patientName,
        $patientNric,
        trim($_POST['patient_address'] ?? ''),
        trim($_POST['patient_postal'] ?? ''),
        trim($_POST['patient_contact'] ?? ''),
        trim($_POST['patient_sex'] ?? ''),
        trim($_POST['patient_dob'] ?? '')
    ]);

    // 5. Insert into guardians
    $nokName = trim($_POST['nok_name'] ?? $_POST['guardian_name'] ?? $_POST['representative_name'] ?? '');
    if (!empty($nokName)) {
        $stmt = $pdo->prepare("INSERT INTO guardians (consent_id, name, nric, relationship) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $consentId,
            $nokName,
            trim($_POST['nok_nric'] ?? $_POST['guardian_nric'] ?? $_POST['representative_nric'] ?? ''),
            trim($_POST['nok_relationship'] ?? $_POST['guardian_relationship'] ?? $_POST['representative_relationship'] ?? '')
        ]);
    }

    // 6. Insert medical answers
    $history = $_POST['history'] ?? [];
    $mapping = [
        'heart_disease' => 'heart_disease',
        'pacemaker' => 'pacemaker',
        'diabetes' => 'diabetes',
        'high_blood_pressure' => 'high_blood_pressure',
        'high_cholesterol' => 'high_cholesterol',
        'cancer' => 'cancer',
        'sensitive_skin' => 'sensitive_skin',
        'allergies' => 'allergies',
        'hiv_aids' => 'hiv_aids',
        'seizures' => 'seizures',
        'anti_coagulants' => 'anti_coagulants',
        'operation' => 'operation',
        'abnormal_bleeding' => 'abnormal_bleeding',
        'currently_pregnant' => 'currently_pregnant',
        'heart' => 'heart_disease',
        'hbp' => 'high_blood_pressure',
        'cholesterol' => 'high_cholesterol',
        'skin' => 'sensitive_skin',
        'hiv' => 'hiv_aids',
        'anticoagulants' => 'anti_coagulants',
        'bleeding' => 'abnormal_bleeding',
        'pregnant' => 'currently_pregnant'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO medical_answers (consent_id, question_code, answer, specification) VALUES (?, ?, ?, ?)");
    $processedKeys = [];
    foreach ($mapping as $postKey => $dbKey) {
        if (in_array($dbKey, $processedKeys)) continue;
        $ans = trim($history[$postKey] ?? '');
        if (!empty($ans)) {
            $spec = '';
            if ($dbKey === 'cancer') $spec = trim($_POST['cancer_spec'] ?? '');
            if ($dbKey === 'allergies') $spec = trim($_POST['allergies_spec'] ?? '');
            if ($dbKey === 'operation') $spec = trim($_POST['operation_spec'] ?? '');
            
            $stmt->execute([$consentId, $dbKey, $ans, $spec]);
            $processedKeys[] = $dbKey;
        }
    }
    
    $otherConditions = trim($_POST['other_conditions'] ?? '');
    if (!empty($otherConditions)) {
        $stmt->execute([$consentId, 'others', 'Yes', $otherConditions]);
    }

    // 7. Save Patient Signature
    $signatureData = $_POST['patient_signature_data'] ?? '';
    if (empty($signatureData)) {
        throw new Exception("Patient signature is missing.");
    }

    list($type, $data) = explode(';', $signatureData);
    list(, $data)      = explode(',', $data);
    $data = base64_decode(str_replace(' ', '+', $data));
    if ($data === false) {
        throw new Exception("Invalid signature data.");
    }

    $fileName = 'sig_' . $consentId . '_patient.png';
    $filePath = $sigDir . '/' . $fileName;
    if (flattenPngAlphaToRgb($data, $filePath) === false) {
        throw new Exception("Failed to save signature image.");
    }

    $stmt = $pdo->prepare("INSERT INTO signatures (consent_id, type, image_path, signed_by) VALUES (?, 'patient', ?, ?)");
    $stmt->execute([$consentId, $fileName, $patientName]);
    
    // 8. Save Practitioner Signature
    $practitionerSignatureData = $_POST['practitioner_signature_data'] ?? '';
    if (empty($practitionerSignatureData)) {
        throw new Exception("Practitioner signature is missing.");
    }

    list($type2, $data2) = explode(';', $practitionerSignatureData);
    list(, $data2)       = explode(',', $data2);
    $data2 = base64_decode(str_replace(' ', '+', $data2));
    if ($data2 === false) {
        throw new Exception("Invalid practitioner signature data.");
    }

    $fileName2 = 'sig_' . $consentId . '_practitioner.png';
    $filePath2 = $sigDir . '/' . $fileName2;
    if (flattenPngAlphaToRgb($data2, $filePath2) === false) {
        throw new Exception("Failed to save practitioner signature image.");
    }

    $physicianName = trim($_POST['physician_name'] ?? 'TCM Practitioner');
    $stmt = $pdo->prepare("INSERT INTO signatures (consent_id, type, image_path, signed_by) VALUES (?, 'practitioner', ?, ?)");
    $stmt->execute([$consentId, $fileName2, $physicianName]);

    // 9. Audit log
    $stmt = $pdo->prepare("INSERT INTO audit_logs (consent_id, event) VALUES (?, ?)");
    $stmt->execute([$consentId, 'Patient and Practitioner submitted consent and signed']);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Consent successfully submitted.',
        'token' => $consentId
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
