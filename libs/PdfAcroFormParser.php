<?php

/**
 * Pure PHP AcroForm Parser
 * Extracts form field names (/T), types, and bounding box coordinates (/Rect)
 * from PDF templates without external binaries or dependencies.
 */
class PdfAcroFormParser
{
    private string $pdfPath;
    private array $pages = [];
    private array $fields = [];
    private bool $parsed = false;

    public function __construct(string $pdfPath)
    {
        $this->pdfPath = $pdfPath;
    }

    /**
     * Parse the PDF template and extract field geometries.
     * @return self
     */
    public function parse(): self
    {
        if ($this->parsed) {
            return $this;
        }

        if (!file_exists($this->pdfPath)) {
            throw new InvalidArgumentException("PDF file not found: " . $this->pdfPath);
        }

        $content = file_get_contents($this->pdfPath);
        if ($content === false) {
            throw new RuntimeException("Unable to read PDF file: " . $this->pdfPath);
        }

        // 1. Index all PDF objects: {objNumber => content}
        $objects = [];
        preg_match_all("/(\d+)\s+0\s+obj(.*?)endobj/s", $content, $rawObjs, PREG_SET_ORDER);
        foreach ($rawObjs as $raw) {
            $objNum = (int)$raw[1];
            $objects[$objNum] = $raw[2];
        }

        // 2. Identify Pages and their MediaBox / CropBox / Annots
        $pageNumber = 1;
        $annotToPage = [];

        foreach ($objects as $objNum => $objContent) {
            if (preg_match("/\/Type\s*\/Page\b/", $objContent)) {
                $pageHeight = 792.0; // Default Letter/A4 height
                $pageWidth = 612.0;

                // Extract MediaBox or CropBox [llx lly urx ury]
                if (preg_match("/\/(?:MediaBox|CropBox)\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/", $objContent, $box)) {
                    $pageWidth = (float)$box[3] - (float)$box[1];
                    $pageHeight = (float)$box[4] - (float)$box[2];
                }

                $this->pages[$pageNumber] = [
                    'page' => $pageNumber,
                    'objNum' => $objNum,
                    'width' => $pageWidth,
                    'height' => $pageHeight,
                ];

                // Extract Annots array references: /Annots [ 12 0 R 13 0 R ... ]
                if (preg_match("/\/Annots\s*\[([^\]]+)\]/s", $objContent, $annotsMatch)) {
                    preg_match_all("/(\d+)\s+0\s+R/", $annotsMatch[1], $annotRefs);
                    foreach ($annotRefs[1] as $annotObjNum) {
                        $annotObjNum = (int)$annotObjNum;
                        $annotToPage[$annotObjNum] = $pageNumber;
                        if (isset($objects[$annotObjNum])) {
                            $this->extractFieldFromObject($objects[$annotObjNum], $annotObjNum, $pageNumber, $pageHeight);
                        }
                    }
                }

                $pageNumber++;
            }
        }

        // 3. Scan for any additional AcroForm / Annot objects (e.g. Signatures or unlinked widgets)
        $totalPageCount = max(1, count($this->pages));
        foreach ($objects as $objNum => $objContent) {
            if (isset($annotToPage[$objNum])) {
                continue; // Already processed via /Annots
            }

            if (preg_match("/\/Type\s*\/Annot\b/", $objContent) || preg_match("/\/Subtype\s*\/Widget\b/", $objContent) || preg_match("/\/FT\s*\/(?:Tx|Sig|Btn|Ch)\b/", $objContent)) {
                // Check if page reference /P is given
                $assignedPage = $totalPageCount; // Default to last page if not specified
                if (preg_match("/\/P\s+(\d+)\s+0\s+R/", $objContent, $pMatch)) {
                    $pObjNum = (int)$pMatch[1];
                    foreach ($this->pages as $pNum => $pData) {
                        if ($pData['objNum'] === $pObjNum) {
                            $assignedPage = $pNum;
                            break;
                        }
                    }
                }

                $pHeight = $this->pages[$assignedPage]['height'] ?? 792.0;
                $this->extractFieldFromObject($objContent, $objNum, $assignedPage, $pHeight);
            }
        }

        $this->parsed = true;
        return $this;
    }

    /**
     * Helper to extract field metadata and convert PDF coordinate system to Top-Left origin.
     */
    private function extractFieldFromObject(string $objContent, int $objNum, int $pageNumber, float $pageHeight): void
    {
        // Field name: /T (fieldName) or /T <hexName>
        $fieldName = null;
        if (preg_match("/\/T\s*\(([^)]+)\)/", $objContent, $tMatch)) {
            $fieldName = trim($tMatch[1]);
        } elseif (preg_match("/\/T\s*<([0-9A-Fa-f]+)>/", $objContent, $hexMatch)) {
            $fieldName = trim(hex2bin($hexMatch[1]));
        }

        if (!$fieldName || $fieldName === 'myasi') {
            // Ignore system placeholder field names
            return;
        }

        // Bounding box: /Rect [llx lly urx ury]
        if (preg_match("/\/Rect\s*\[\s*([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s+([0-9\.\-]+)\s*\]/", $objContent, $rMatch)) {
            $llx = (float)$rMatch[1];
            $lly = (float)$rMatch[2];
            $urx = (float)$rMatch[3];
            $ury = (float)$rMatch[4];

            $width = abs($urx - $llx);
            $height = abs($ury - $lly);

            // Convert PDF coordinates (bottom-left origin) to TCPDF/FPDI coordinates (top-left origin)
            $x = min($llx, $urx);
            $y = $pageHeight - max($lly, $ury);

            // Field type: /FT /Tx (text), /Btn (button/checkbox), /Sig (signature), /Ch (choice)
            $fieldType = 'text';
            if (preg_match("/\/FT\s*\/(\w+)/", $objContent, $ftMatch)) {
                $fieldType = strtolower($ftMatch[1]);
            }

            $fieldInfo = [
                'name' => $fieldName,
                'page' => $pageNumber,
                'objNum' => $objNum,
                'type' => $fieldType,
                'rect_pdf' => [$llx, $lly, $urx, $ury],
                'x' => round($x, 2),
                'y' => round($y, 2),
                'width' => round($width, 2),
                'height' => round($height, 2),
            ];

            // Store indexed by field name
            if (!isset($this->fields[$fieldName])) {
                $this->fields[$fieldName] = $fieldInfo;
            } else {
                if (!isset($this->fields[$fieldName]['instances'])) {
                    $this->fields[$fieldName]['instances'] = [$this->fields[$fieldName]];
                }
                $this->fields[$fieldName]['instances'][] = $fieldInfo;
            }
        }
    }

    /**
     * Get all extracted fields indexed by field name.
     */
    public function getFields(): array
    {
        $this->parse();
        return $this->fields;
    }

    /**
     * Get metadata for a specific field name.
     */
    public function getField(string $name, ?int $pageNumber = null): ?array
    {
        $this->parse();
        if (!isset($this->fields[$name])) {
            return null;
        }

        $field = $this->fields[$name];
        if ($pageNumber !== null && isset($field['instances'])) {
            foreach ($field['instances'] as $inst) {
                if ($inst['page'] === $pageNumber) {
                    return $inst;
                }
            }
        }

        return $field;
    }

    /**
     * Get all fields belonging to a specific page.
     */
    public function getFieldsByPage(int $pageNumber): array
    {
        $this->parse();
        $result = [];
        foreach ($this->fields as $name => $info) {
            if (isset($info['instances'])) {
                foreach ($info['instances'] as $inst) {
                    if ($inst['page'] === $pageNumber) {
                        $result[$name] = $inst;
                    }
                }
            } elseif (($info['page'] ?? 1) === $pageNumber) {
                $result[$name] = $info;
            }
        }
        return $result;
    }

    /**
     * Get page dimensions.
     */
    public function getPageInfo(int $pageNumber): ?array
    {
        $this->parse();
        return $this->pages[$pageNumber] ?? null;
    }
}
