<?php

require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/bootstrap.php';

class TcmPdfGenerator
{
    private PDO $pdo;
    private string $storageDir;

    public function __construct(PDO $pdo, ?string $storageDir = null)
    {
        $this->pdo = $pdo;
        $this->storageDir = $storageDir ?? (function_exists('getTcmStorageDir') ? getTcmStorageDir() : __DIR__ . '/../storage');
    }

    /**
     * Generate 100% Pure PHP PDF document from clean HTML/CSS template
     * @param string $token
     * @return string Path to generated PDF file
     * @throws Exception
     */
    public function generate(string $token): string
    {
        // 1. Fetch Patient
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE consent_id = ?");
        $stmt->execute([$token]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient) {
            throw new Exception("Data pasien tidak ditemukan untuk token: " . htmlspecialchars($token));
        }

        // 2. Fetch Guardian
        $stmt = $this->pdo->prepare("SELECT * FROM guardians WHERE consent_id = ?");
        $stmt->execute([$token]);
        $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Fetch Medical Answers
        $stmt = $this->pdo->prepare("SELECT * FROM medical_answers WHERE consent_id = ?");
        $stmt->execute([$token]);
        $medicalRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $medical = [];
        foreach ($medicalRows as $row) {
            $medical[$row['question_code']] = $row;
        }

        // 4. Fetch Signatures
        $stmt = $this->pdo->prepare("SELECT * FROM signatures WHERE consent_id = ?");
        $stmt->execute([$token]);
        $signatureRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $signatures = [];
        foreach ($signatureRows as $row) {
            $signatures[$row['type']] = $row;
        }

        // Prepare Variables for Template
        $patientName = trim($patient['name'] ?? '');
        $gender = strtoupper(substr(trim($patient['gender'] ?? ''), 0, 1));
        if ($gender !== 'M' && $gender !== 'F') $gender = $patient['gender'] ?? '';
        $patientSex = ($gender === 'M') ? 'Male / 男' : (($gender === 'F') ? 'Female / 女' : $gender);
        $patientNric = trim($patient['nric'] ?? '');
        $patientDob = trim($patient['date_of_birth'] ?? '');
        $patientContact = trim($patient['contact_number'] ?? '');

        // Helper closures for answers & specifications
        $getAns = function($key, $aliases = []) use ($medical) {
            $row = $medical[$key] ?? null;
            if (!$row && !empty($aliases)) {
                foreach ($aliases as $alt) {
                    if (isset($medical[$alt])) {
                        $row = $medical[$alt];
                        break;
                    }
                }
            }
            if (!$row) return '';
            $ans = trim($row['answer'] ?? '');
            if ($ans === 'Yes') return 'Yes 有';
            if ($ans === 'No') return 'No 无';
            if ($ans === 'Unsure') return 'Unsure 不确定';
            return $ans;
        };

        $getSpec = function($key) use ($medical) {
            return trim($medical[$key]['specification'] ?? '');
        };

        $renderSpecify = function($spec) {
            if (!empty($spec)) {
                return '<span style="color: #003366; font-weight: bold; text-decoration: underline;">&nbsp;&nbsp;' . htmlspecialchars($spec) . '&nbsp;&nbsp;</span>';
            }
            return '<span style="color: #888888;">....................................................................</span>';
        };

        // Signatures files & dates
        $signaturesDir = $this->storageDir . '/signatures';
        $patientSigFile = null;
        $patientSigDate = date('Y-m-d');
        if (isset($signatures['patient'])) {
            $rawSig = $signaturesDir . '/' . $signatures['patient']['image_path'];
            if (file_exists($rawSig)) {
                if (function_exists('flattenPngAlphaToRgb')) {
                    flattenPngAlphaToRgb($rawSig, $rawSig);
                }
                $patientSigFile = $rawSig;
            }
            $sDate = explode(' ', $signatures['patient']['signed_at'] ?? '')[0];
            if (!empty($sDate)) $patientSigDate = $sDate;
        }

        $patientRepText = '';
        if ($guardian && !empty($guardian['name'])) {
            $patientRepText = $guardian['name'];
            if (!empty($guardian['relationship'])) {
                $patientRepText .= ' (' . $guardian['relationship'] . ')';
            }
        }

        $docSigFile = null;
        $physicianName = 'Dr. Siah Ah Cheok';
        $docSigDate = date('Y-m-d');
        if (isset($signatures['practitioner'])) {
            $rawDocSig = $signaturesDir . '/' . $signatures['practitioner']['image_path'];
            if (file_exists($rawDocSig)) {
                if (function_exists('flattenPngAlphaToRgb')) {
                    flattenPngAlphaToRgb($rawDocSig, $rawDocSig);
                }
                $docSigFile = $rawDocSig;
            }
            if (!empty($signatures['practitioner']['signed_by'])) {
                $physicianName = $signatures['practitioner']['signed_by'];
            }
            $dDate = explode(' ', $signatures['practitioner']['signed_at'] ?? '')[0];
            if (!empty($dDate)) $docSigDate = $dDate;
        }

        // Render HTML
        $templateFile = __DIR__ . '/../templates/pdf_consent_template.php';
        if (!file_exists($templateFile)) {
            throw new Exception("HTML template file not found at templates/pdf_consent_template.php");
        }

        ob_start();
        include $templateFile;
        $html = ob_get_clean();

        // Initialize TCPDF
        $pdf = new TCPDF('P', 'pt', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('InformedConsentTCM Pure PHP');
        $pdf->SetAuthor('SIAH AH CHEOK CHINESE SIN-SEH CLINIC');
        $pdf->SetTitle('Health Questionnaire TCM - ' . $patientName);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Page geometry: Letter is 612 x 792 pt
        $pdf->SetMargins(38, 30, 38, true);
        $pdf->SetAutoPageBreak(true, 28);
        $pdf->SetFont('cid0cs', '', 9.5);
        $pdf->SetTextColor(20, 20, 20);

        // Add first page and write HTML content (page-break is handled automatically by CSS)
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        // Output Directory
        $pdfDir = $this->storageDir . '/pdf';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0777, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $patientName);
        $timestamp = date('Ymd_His');
        $filename = "TCM_Consent_{$safeName}_{$timestamp}.pdf";
        $outputPath = $pdfDir . '/' . $filename;

        $pdf->Output($outputPath, 'F');

        return $outputPath;
    }
}
