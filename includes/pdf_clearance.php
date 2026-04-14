<?php
/**
 * ClearPath PDF Certificate Generator
 * ─────────────────────────────────────────────────────────
 * Generates the official school clearance certificate as PDF.
 * Uses FPDF (no Composer needed).
 *
 * SETUP:
 *   1. Download FPDF from: http://www.fpdf.org/ (click "Download")
 *   2. Extract and copy fpdf.php into: includes/lib/fpdf.php
 *   That's it — no other setup needed.
 * ─────────────────────────────────────────────────────────
 */

if (file_exists(__DIR__ . '/lib/fpdf.php')) {
    require_once __DIR__ . '/lib/fpdf.php';
}

/**
 * Generates a clearance certificate PDF for a given clearance request.
 *
 * @param mysqli $db          Database connection
 * @param int    $requestId   clearance_requests.id
 * @param bool   $download    If true, sends to browser for download. If false, saves temp file.
 * @return string|null        Path to temp PDF file (when $download=false), or null on error
 */
function generateClearancePDF($db, int $requestId, bool $download = false): ?string {
    if (!class_exists('FPDF')) {
        error_log('[ClearPath PDF] FPDF not found. Place fpdf.php in includes/lib/');
        return null;
    }

    // ── Fetch student + clearance data ─────────────────────
    $req = $db->query("
        SELECT cr.*, cr.school_year, cr.semester, cr.submitted_at,
               u.full_name, u.student_id AS sid, u.email,
               u.year_level, u.section, u.course,
               u.profile_photo
        FROM clearance_requests cr
        JOIN users u ON u.id = cr.student_id
        WHERE cr.id = $requestId
        LIMIT 1
    ")->fetch_assoc();

    if (!$req) return null;

    // ── Fetch all clearance items with office + requirements ─
    $items = $db->query("
        SELECT ci.status, ci.remarks, ci.reviewed_at,
               o.name AS office_name,
               GROUP_CONCAT(oreq.requirement_name SEPARATOR '|') AS requirements
        FROM clearance_items ci
        JOIN offices o ON o.id = ci.office_id
        LEFT JOIN office_requirements oreq ON oreq.office_id = ci.office_id
        WHERE ci.clearance_request_id = $requestId
        GROUP BY ci.id
        ORDER BY o.sort_order
    ")->fetch_all(MYSQLI_ASSOC);

    // ── Build PDF ──────────────────────────────────────────
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 20);

    // -- Header bar
    $pdf->SetFillColor(15, 31, 92); // dark blue
    $pdf->Rect(0, 0, 210, 40, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetXY(20, 10);
    $pdf->Cell(170, 10, 'BOHOL PENINSULA STATE COLLEGE', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    $pdf->SetXY(20, 22);
    $pdf->Cell(170, 8, 'ClearPath — School Clearance Certificate', 0, 1, 'C');

    // -- Title
    $pdf->SetTextColor(15, 31, 92);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetXY(20, 48);
    $pdf->Cell(170, 10, 'OFFICIAL CLEARANCE CERTIFICATE', 0, 1, 'C');

    // -- Status badge
    $status = strtoupper($req['status']);
    $statusColor = $status === 'CLEARED' ? [5, 150, 105] : [220, 38, 38];
    $pdf->SetFillColor(...$statusColor);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetXY(80, 60);
    $pdf->Cell(50, 9, $status, 0, 1, 'C', true);
    $pdf->SetBorderRadius = 4;

    // -- Student info box
    $pdf->SetDrawColor(209, 213, 219);
    $pdf->SetFillColor(248, 249, 252);
    $pdf->SetTextColor(30, 34, 64);
    $pdf->RoundedRect(20, 75, 170, 50, 3, 'DF');

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetXY(25, 80);
    $pdf->Cell(60, 7, 'Student Information', 0, 1);

    $pdf->SetFont('Arial', '', 10);
    $fields = [
        ['Name',         $req['full_name']],
        ['Student ID',   $req['sid']],
        ['Course',       $req['course'] ?? 'N/A'],
        ['Year / Section', trim(($req['year_level'] ?? '') . ' ' . ($req['section'] ?? '')) ?: 'N/A'],
        ['School Year',  $req['school_year'] . ' — ' . $req['semester'] . ' Semester'],
    ];

    $y = 89;
    foreach ($fields as [$label, $value]) {
        $pdf->SetXY(25, $y);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 6, $label . ':', 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(110, 6, $value, 0, 1);
        $y += 6;
    }

    // -- Requirements table
    $pdf->SetXY(20, 132);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(15, 31, 92);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(80, 8, 'Office / Department', 1, 0, 'C', true);
    $pdf->Cell(55, 8, 'Requirements', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Status', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $fillAlt = false;
    foreach ($items as $item) {
        $fillAlt = !$fillAlt;
        $pdf->SetFillColor($fillAlt ? 248 : 255, $fillAlt ? 249 : 255, $fillAlt ? 252 : 255);
        $pdf->SetTextColor(30, 34, 64);

        $statusText = ucfirst($item['status']);
        $reqText    = $item['requirements'] ? implode(', ', explode('|', $item['requirements'])) : '—';
        $reqText    = mb_strimwidth($reqText, 0, 40, '...');

        $h = 8;
        $pdf->Cell(80, $h, $item['office_name'], 1, 0, 'L', true);
        $pdf->Cell(55, $h, $reqText, 1, 0, 'L', true);

        // Color-code status
        if ($item['status'] === 'approved')      $pdf->SetTextColor(5, 150, 105);
        elseif ($item['status'] === 'rejected')  $pdf->SetTextColor(220, 38, 38);
        else                                      $pdf->SetTextColor(217, 119, 6);

        $pdf->Cell(35, $h, $statusText, 1, 1, 'C', true);
        $pdf->SetTextColor(30, 34, 64);
    }

    // -- Signature area
    $signY = $pdf->GetY() + 16;
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->SetXY(20, $signY);
    $pdf->Cell(170, 6, 'This document is electronically generated by ClearPath BPC and is valid without a wet signature.', 0, 1, 'C');
    $pdf->SetXY(20, $signY + 8);
    $pdf->Cell(170, 6, 'Generated on: ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');

    // -- Footer
    $pdf->SetFillColor(15, 31, 92);
    $pdf->Rect(0, 282, 210, 15, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(20, 285);
    $pdf->Cell(170, 6, 'ClearPath BPC — Digital School Clearance System   |   bpc.edu.ph', 0, 0, 'C');

    // ── Output ─────────────────────────────────────────────
    if ($download) {
        $filename = 'Clearance_' . preg_replace('/[^A-Za-z0-9_-]/', '', $req['sid']) . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }

    // Save to temp file (for email attachment)
    $tempPath = sys_get_temp_dir() . '/clearpath_cert_' . $requestId . '_' . time() . '.pdf';
    $pdf->Output('F', $tempPath);
    return $tempPath;
}

/**
 * FPDF extension to draw rounded rectangles (FPDF doesn't have this natively)
 * Used for the status badge and info box
 */
if (!function_exists('fpdf_RoundedRect') && class_exists('FPDF')) {
    class FPDF extends \FPDF {
        public function RoundedRect($x, $y, $w, $h, $r, $style = '') {
            $k  = $this->k;
            $hp = $this->h;
            if ($style === 'F') $op = 'f';
            elseif ($style === 'FD' || $style === 'DF') $op = 'B';
            else $op = 'S';
            $MyArc = 4 / 3 * (M_SQRT2 - 1);
            $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
            $xc = $x + $w - $r; $yc = $y + $r;
            $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
            $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
            $xc = $x + $w - $r; $yc = $y + $h - $r;
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
            $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
            $xc = $x + $r; $yc = $y + $h - $r;
            $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
            $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
            $xc = $x + $r; $yc = $y + $r;
            $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
            $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
            $this->_out($op);
        }
        protected function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
            $h = $this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
        }
    }
}
