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
     * Generate PDF for given consent token/ID with 100% exact 1:1 vector overlay and 12pt font
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
        $pdf = new Fpdi('P', 'pt');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0, true);

        // Font settings - 12pt font as requested
        $fontChinese = 'cid0cs';
        $pdf->SetTextColor(0, 0, 0);

        $pageCount = $pdf->setSourceFile($this->templatePath);

        // =========================================================================
        // PAGE 1: PARTICULARS & Q1-Q2 (Exact 1:1 Native Dimensions)
        // =========================================================================
        $tplPage1 = $pdf->importPage(1);
        $sizeP1 = $pdf->getTemplateSize($tplPage1);
        $pdf->AddPage($sizeP1['orientation'], [$sizeP1['width'], $sizeP1['height']]);
        $pdf->useTemplate($tplPage1, 0, 0, $sizeP1['width'], $sizeP1['height']);

        $pdf->SetFont($fontChinese, '', 12);

        // Patient Name [135.0, 116.0, 281.0, 127.0] -> y=115.5
        $patientName = $patient['name'] ?? '';
        $pdf->SetXY(136, 115.5);
        $pdf->Cell(144, 12, $patientName, 0, 0, 'L');

        // Sex [333.0, 116.0, 374.0, 127.0] -> y=115.5, left-aligned next to label
        $gender = strtoupper(substr(trim($patient['gender'] ?? ''), 0, 1));
        if ($gender !== 'M' && $gender !== 'F') $gender = $patient['gender'] ?? '';
        $pdf->SetXY(333, 115.5);
        $pdf->Cell(30, 12, $gender, 0, 0, 'L');

        // NRIC [485.0, 117.0, 586.0, 128.0] -> y=115.5
        $pdf->SetXY(487, 115.5);
        $pdf->Cell(98, 12, $patient['nric'] ?? '', 0, 0, 'L');

        // DOB [151.0, 135.0, 282.0, 146.0] -> y=134.0
        $pdf->SetXY(153, 134.0);
        $pdf->Cell(128, 12, $patient['date_of_birth'] ?? '', 0, 0, 'L');

        // Contact Number [485.0, 136.0, 586.0, 147.0] -> y=134.0
        $pdf->SetXY(487, 134.0);
        $pdf->Cell(98, 12, $patient['contact_number'] ?? '', 0, 0, 'L');

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

        // Q1 Heart Disease [499.0, 712.0, 569.0, 723.0]
        $pdf->SetXY(499, 712);
        $pdf->Cell(70, 12, $getAnswerText('heart_disease', ['heart']), 0, 0, 'C');

        // Q2 Pacemaker [499.0, 732.0, 569.0, 743.0]
        $pdf->SetXY(499, 732);
        $pdf->Cell(70, 12, $getAnswerText('pacemaker'), 0, 0, 'C');

        // =========================================================================
        // PAGE 2: Q3-Q14, DECLARATIONS & SIGNATURES (Exact 1:1 Native Dimensions)
        // =========================================================================
        if ($pageCount >= 2) {
            $tplPage2 = $pdf->importPage(2);
            $sizeP2 = $pdf->getTemplateSize($tplPage2);
            $pdf->AddPage($sizeP2['orientation'], [$sizeP2['width'], $sizeP2['height']]);
            $pdf->useTemplate($tplPage2, 0, 0, $sizeP2['width'], $sizeP2['height']);

            $pdf->SetFont($fontChinese, '', 12);

            // Q3 Diabetes [501.0, 35.0, 572.0, 46.0]
            $pdf->SetXY(501, 35);
            $pdf->Cell(71, 12, $getAnswerText('diabetes'), 0, 0, 'C');

            // Q4 High Blood Pressure [501.0, 54.0, 573.0, 65.0]
            $pdf->SetXY(501, 54);
            $pdf->Cell(72, 12, $getAnswerText('high_blood_pressure', ['hbp']), 0, 0, 'C');

            // Q5 High Cholesterol [501.0, 71.0, 573.0, 82.0]
            $pdf->SetXY(501, 71);
            $pdf->Cell(72, 12, $getAnswerText('high_cholesterol', ['cholesterol']), 0, 0, 'C');

            // Q6 Cancer [502.0, 89.0, 574.0, 100.0]
            $pdf->SetXY(502, 89);
            $pdf->Cell(72, 12, $getAnswerText('cancer'), 0, 0, 'C');

            // Cancer specify [142.0, 106.0, 501.0, 117.0]
            $specCancer = $getSpecification('cancer');
            if (!empty($specCancer)) {
                $pdf->SetXY(144, 106);
                $pdf->Cell(355, 12, $specCancer, 0, 0, 'L');
            }

            // Q7 Sensitive Skin [503.0, 125.0, 574.0, 136.0]
            $pdf->SetXY(503, 125);
            $pdf->Cell(71, 12, $getAnswerText('sensitive_skin', ['skin']), 0, 0, 'C');

            // Q8 Allergies [503.0, 144.0, 575.0, 155.0]
            $pdf->SetXY(503, 144);
            $pdf->Cell(72, 12, $getAnswerText('allergies'), 0, 0, 'C');

            // Allergies specify [143.0, 161.0, 503.0, 172.0]
            $specAllergies = $getSpecification('allergies');
            if (!empty($specAllergies)) {
                $pdf->SetXY(145, 161);
                $pdf->Cell(355, 12, $specAllergies, 0, 0, 'L');
            }

            // Q9 HIV / AIDS [504.0, 179.0, 575.0, 190.0]
            $pdf->SetXY(504, 179);
            $pdf->Cell(71, 12, $getAnswerText('hiv_aids', ['hiv']), 0, 0, 'C');

            // Q10 Seizures [504.0, 196.0, 576.0, 207.0]
            $pdf->SetXY(504, 196);
            $pdf->Cell(72, 12, $getAnswerText('seizures'), 0, 0, 'C');

            // Q11 Anti-coagulants [504.0, 215.0, 576.0, 226.0]
            $pdf->SetXY(504, 215);
            $pdf->Cell(72, 12, $getAnswerText('anti_coagulants', ['anticoagulants']), 0, 0, 'C');

            // Q12 Operation [504.0, 232.0, 576.0, 243.0]
            $pdf->SetXY(504, 232);
            $pdf->Cell(72, 12, $getAnswerText('operation'), 0, 0, 'C');

            // Operation specify [142.0, 249.0, 504.0, 260.0]
            $specOp = $getSpecification('operation');
            if (!empty($specOp)) {
                $pdf->SetXY(144, 249);
                $pdf->Cell(355, 12, $specOp, 0, 0, 'L');
            }

            // Q13 Abnormal Bleeding [504.0, 270.0, 576.0, 281.0]
            $pdf->SetXY(504, 270);
            $pdf->Cell(72, 12, $getAnswerText('abnormal_bleeding', ['bleeding']), 0, 0, 'C');

            // Q14 Currently Pregnant [505.0, 319.0, 577.0, 330.0]
            $pdf->SetXY(505, 319);
            $pdf->Cell(72, 12, $getAnswerText('currently_pregnant', ['pregnant']), 0, 0, 'C');

            // Other Conditions [24, 370, 570, 400]
            if (isset($medical['others']) && !empty($medical['others']['specification'])) {
                $specOthers = trim($medical['others']['specification']);
                $pdf->SetXY(24, 370);
                $pdf->MultiCell(546, 14, $specOthers, 0, 'L');
            }

            $signaturesDir = $this->storageDir . '/signatures';

            // --- Patient / Representative Signature ---
            if (isset($signatures['patient'])) {
                $sigFile = $signaturesDir . '/' . $signatures['patient']['image_path'];
                if (file_exists($sigFile)) {
                    // Position above line y=586
                    $pdf->Image($sigFile, 35, 538, 180, 45, 'PNG', '', '', false, 300, '', false, false, 0, 'CM');
                }

                // Patient Signature Date [305.0, 574.0, 377.0, 585.0]
                $signedDate = explode(' ', $signatures['patient']['signed_at'] ?? '')[0];
                if (empty($signedDate)) $signedDate = date('Y-m-d');
                $pdf->SetXY(305, 570);
                $pdf->Cell(72, 12, $signedDate, 0, 0, 'C');
            }

            // Patient Representative Text (Name & Relationship) [24.0, 608.0, 291.0, 619.0]
            if ($guardian && !empty($guardian['name'])) {
                $repText = $guardian['name'];
                if (!empty($guardian['relationship'])) {
                    $repText .= ' (' . $guardian['relationship'] . ')';
                }
                $pdf->SetXY(24, 604);
                $pdf->Cell(267, 12, $repText, 0, 0, 'L');
            }

            // --- Physician / TCM Practitioner Signature ---
            if (isset($signatures['practitioner'])) {
                $docSigFile = $signaturesDir . '/' . $signatures['practitioner']['image_path'];
                if (file_exists($docSigFile)) {
                    // Position above line y=697
                    $pdf->Image($docSigFile, 35, 648, 180, 45, 'PNG', '', '', false, 300, '', false, false, 0, 'CM');
                }

                // Physician Name [24.0, 685.0, 291.0, 696.0]
                $docName = $signatures['practitioner']['signed_by'] ?? 'Dr. Siah Ah Cheok';
                $pdf->SetXY(24, 681);
                $pdf->Cell(267, 12, $docName, 0, 0, 'L');

                // Physician Signature Date [305.0, 684.0, 377.0, 695.0]
                $docSignedDate = explode(' ', $signatures['practitioner']['signed_at'] ?? '')[0];
                if (empty($docSignedDate)) $docSignedDate = date('Y-m-d');
                $pdf->SetXY(305, 681);
                $pdf->Cell(72, 12, $docSignedDate, 0, 0, 'C');
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
