<?php

require_once __DIR__ . '/tcpdf/tcpdf.php';
require_once __DIR__ . '/fpdi/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

class TcmPdfGenerator
{
    private $pdo;
    private $storageDir;
    private $templatePath;

    public function __construct(PDO $pdo, $storageDir = null, $templatePath = null)
    {
        $this->pdo = $pdo;
        $this->storageDir = $storageDir ?? realpath(__DIR__ . '/../storage');
        $this->templatePath = $templatePath;

        if (!$this->templatePath) {
            $candidates = [
                realpath(__DIR__ . '/../public/template/sctcm-treatment-template-read-only.pdf'),
                realpath(__DIR__ . '/../public/template/sctcm-treatment.pdf'),
                realpath(__DIR__ . '/../public/template/INFORMED-CONSENT.pdf'),
            ];
            foreach ($candidates as $cand) {
                if ($cand && file_exists($cand)) {
                    $this->templatePath = $cand;
                    break;
                }
            }
        }
    }

    /**
     * Generate PDF for given consent token/ID with 100% exact AcroForm coordinates
     * @param string $token
     * @return string Path to generated PDF file
     * @throws Exception
     */
    public function generate($token)
    {
        if (!$this->templatePath || !file_exists($this->templatePath)) {
            throw new Exception("Template PDF klinik tidak ditemukan di public/template/");
        }

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

        // Prepare FPDI Instance with TCPDF
        $pdf = new Fpdi('P', 'pt', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        // Font settings
        $fontChinese = 'cid0cs';
        $pdf->SetTextColor(0, 0, 0);

        $pageCount = $pdf->setSourceFile($this->templatePath);

        // =========================================================================
        // PAGE 1: PARTICULARS & Q1-Q2
        // =========================================================================
        $tplPage1 = $pdf->importPage(1);
        $pdf->AddPage('P', 'A4');
        $pdf->useTemplate($tplPage1, 0, 0, 595.28, 841.89);

        $pdf->SetFont($fontChinese, '', 10);

        // Patient Name [138, 115, 275, 129]
        $patientName = $patient['name'] ?? '';
        $pdf->SetXY(138, 115);
        $pdf->Cell(137, 14, $patientName, 0, 0, 'L');

        // Sex [334, 115, 375, 129]
        $gender = strtoupper(substr(trim($patient['gender'] ?? ''), 0, 1));
        if ($gender !== 'M' && $gender !== 'F') $gender = $patient['gender'] ?? '';
        $pdf->SetXY(334, 115);
        $pdf->Cell(41, 14, $gender, 0, 0, 'C');

        // NRIC [488, 115, 580, 129]
        $pdf->SetXY(488, 115);
        $pdf->Cell(92, 14, $patient['nric'] ?? '', 0, 0, 'L');

        // DOB [154, 134, 275, 148]
        $pdf->SetXY(154, 134);
        $pdf->Cell(121, 14, $patient['date_of_birth'] ?? '', 0, 0, 'L');

        // Contact Number [488, 134, 580, 148]
        $pdf->SetXY(488, 134);
        $pdf->Cell(92, 14, $patient['contact_number'] ?? '', 0, 0, 'L');

        // Medical History Helper
        $getAnswerText = function($key, $aliases = []) use ($medical) {
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

        $getSpecification = function($key) use ($medical) {
            return trim($medical[$key]['specification'] ?? '');
        };

        // Q1 Heart Disease [490, 710, 580, 725]
        $pdf->SetXY(490, 710);
        $pdf->Cell(90, 15, $getAnswerText('heart_disease', ['heart']), 0, 0, 'C');

        // Q2 Pacemaker [490, 727, 580, 742]
        $pdf->SetXY(490, 727);
        $pdf->Cell(90, 15, $getAnswerText('pacemaker'), 0, 0, 'C');

        // =========================================================================
        // PAGE 2: Q3-Q14, DECLARATIONS & SIGNATURES
        // =========================================================================
        if ($pageCount >= 2) {
            $tplPage2 = $pdf->importPage(2);
            $pdf->AddPage('P', 'A4');
            $pdf->useTemplate($tplPage2, 0, 0, 595.28, 841.89);

            $pdf->SetFont($fontChinese, '', 10);

            // Q3 Diabetes [490, 32, 580, 47]
            $pdf->SetXY(490, 32);
            $pdf->Cell(90, 15, $getAnswerText('diabetes'), 0, 0, 'C');

            // Q4 High Blood Pressure [490, 48, 580, 63]
            $pdf->SetXY(490, 48);
            $pdf->Cell(90, 15, $getAnswerText('high_blood_pressure', ['hbp']), 0, 0, 'C');

            // Q5 High Cholesterol [490, 64, 580, 79]
            $pdf->SetXY(490, 64);
            $pdf->Cell(90, 15, $getAnswerText('high_cholesterol', ['cholesterol']), 0, 0, 'C');

            // Q6 Cancer [490, 82, 580, 97]
            $pdf->SetXY(490, 82);
            $pdf->Cell(90, 15, $getAnswerText('cancer'), 0, 0, 'C');

            // Cancer specify [143, 103, 490, 118]
            $specCancer = $getSpecification('cancer');
            if (!empty($specCancer)) {
                $pdf->SetFont($fontChinese, '', 9);
                $pdf->SetXY(143, 103);
                $pdf->Cell(347, 15, $specCancer, 0, 0, 'L');
                $pdf->SetFont($fontChinese, '', 10);
            }

            // Q7 Sensitive Skin [490, 120, 580, 135]
            $pdf->SetXY(490, 120);
            $pdf->Cell(90, 15, $getAnswerText('sensitive_skin', ['skin']), 0, 0, 'C');

            // Q8 Allergies [490, 136, 580, 151]
            $pdf->SetXY(490, 136);
            $pdf->Cell(90, 15, $getAnswerText('allergies'), 0, 0, 'C');

            // Allergies specify [143, 158, 490, 173]
            $specAllergies = $getSpecification('allergies');
            if (!empty($specAllergies)) {
                $pdf->SetFont($fontChinese, '', 9);
                $pdf->SetXY(143, 158);
                $pdf->Cell(347, 15, $specAllergies, 0, 0, 'L');
                $pdf->SetFont($fontChinese, '', 10);
            }

            // Q9 HIV / AIDS [490, 174, 580, 189]
            $pdf->SetXY(490, 174);
            $pdf->Cell(90, 15, $getAnswerText('hiv_aids', ['hiv']), 0, 0, 'C');

            // Q10 Seizures [490, 190, 580, 205]
            $pdf->SetXY(490, 190);
            $pdf->Cell(90, 15, $getAnswerText('seizures'), 0, 0, 'C');

            // Q11 Anti-coagulants [490, 207, 580, 222]
            $pdf->SetXY(490, 207);
            $pdf->Cell(90, 15, $getAnswerText('anti_coagulants', ['anticoagulants']), 0, 0, 'C');

            // Q12 Operation [490, 228, 580, 243]
            $pdf->SetXY(490, 228);
            $pdf->Cell(90, 15, $getAnswerText('operation'), 0, 0, 'C');

            // Operation specify [143, 246, 490, 261]
            $specOp = $getSpecification('operation');
            if (!empty($specOp)) {
                $pdf->SetFont($fontChinese, '', 9);
                $pdf->SetXY(143, 246);
                $pdf->Cell(347, 15, $specOp, 0, 0, 'L');
                $pdf->SetFont($fontChinese, '', 10);
            }

            // Q13 Abnormal Bleeding [490, 268, 580, 283]
            $pdf->SetXY(490, 268);
            $pdf->Cell(90, 15, $getAnswerText('abnormal_bleeding', ['bleeding']), 0, 0, 'C');

            // Q14 Currently Pregnant [490, 316, 580, 331]
            $pdf->SetXY(490, 316);
            $pdf->Cell(90, 15, $getAnswerText('currently_pregnant', ['pregnant']), 0, 0, 'C');

            // Other Conditions [25, 362, 580, 395]
            if (isset($medical['others']) && !empty($medical['others']['specification'])) {
                $specOthers = trim($medical['others']['specification']);
                $pdf->SetFont($fontChinese, '', 9);
                $pdf->SetXY(25, 362);
                $pdf->MultiCell(555, 12, $specOthers, 0, 'L');
                $pdf->SetFont($fontChinese, '', 10);
            }

            $signaturesDir = $this->storageDir . '/signatures';

            // --- Patient / Representative Signature ---
            if (isset($signatures['patient'])) {
                $sigFile = $signaturesDir . '/' . $signatures['patient']['image_path'];
                if (file_exists($sigFile)) {
                    // Position above the signature line (line is at Y ≈ 730 pt)
                    $pdf->Image($sigFile, 45, 675, 185, 50, 'PNG', '', '', false, 300, '', false, false, 0, 'CM');
                }

                // Patient Signature Date (above the Date line on the right, line is at Y ≈ 730 pt)
                $signedDate = explode(' ', $signatures['patient']['signed_at'] ?? '')[0];
                if (empty($signedDate)) $signedDate = date('Y-m-d');
                $pdf->SetXY(490, 715);
                $pdf->Cell(90, 15, $signedDate, 0, 0, 'C');
            }

            // Patient Representative Text (Name & Relationship) - in the bottom line area (Y ≈ 795 pt)
            if ($guardian && !empty($guardian['name'])) {
                $repText = $guardian['name'];
                if (!empty($guardian['relationship'])) {
                    $repText .= ' (' . $guardian['relationship'] . ')';
                }
                $pdf->SetXY(35, 795);
                $pdf->Cell(250, 15, $repText, 0, 0, 'L');
            }

            // --- Physician / TCM Practitioner Signature ---
            if (isset($signatures['practitioner'])) {
                // Physician Name
                $docName = $signatures['practitioner']['signed_by'] ?? 'TCM Practitioner';
                if (!$guardian || empty($guardian['name'])) {
                    $pdf->SetXY(35, 795);
                    $pdf->Cell(250, 15, $docName, 0, 0, 'L');
                }

                // Physician Signature Date
                $docSignedDate = explode(' ', $signatures['practitioner']['signed_at'] ?? '')[0];
                if (empty($docSignedDate)) $docSignedDate = date('Y-m-d');
                $pdf->SetXY(490, 795);
                $pdf->Cell(90, 15, $docSignedDate, 0, 0, 'C');
            }
        }

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
