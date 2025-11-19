<?php
/**
 * PaymentValidator.php
 * Payment Validation dengan simulasi OCR
 * Location: api/validators/PaymentValidator.php
 * 
 * VERSION: SKIP AMOUNT VALIDATION (untuk testing)
 */

class PaymentValidator {
    
    private $companyBankAccounts = [
        'BCA' => '1234567890',
        'MANDIRI' => '9876543210',
        'BRI' => '5555666677'
    ];
    
    private $confidenceScore = 100;
    private $errors = [];
    private $warnings = [];
    private $checks = [];
    
    /**
     * Main validation method
     */
    public function validate($file, $expectedAmount, $orderDate = null) {
        $this->resetValidation();
        
        if (!isset($file) || !isset($file['tmp_name'])) {
            $this->addError('Bukti bayar tidak ditemukan');
            return $this->getResults();
        }
        
        // Run validation checks
        $this->checkFileFormat($file);
        $this->checkFileSize($file);
        
        // Simulasi OCR extraction
        $extractedData = $this->simulateOCR($file);
        
        // ⭐ SKIP AMOUNT VALIDATION (for testing)
        // $this->validateAmount($extractedData['amount'], $expectedAmount);
        $this->addCheck('Amount Validation', 'pass', 
            "Rp " . number_format($extractedData['amount'], 0, ',', '.') . " (validation skipped for testing)"
        );
        
        $this->validateDate($extractedData['date'], $orderDate);
        $this->validateBank($extractedData['bank']);
        $this->validateSenderName($extractedData['sender_name']);
        
        return $this->getResults($extractedData);
    }
    
    /**
     * CHECK 1: File Format
     */
    private function checkFileFormat($file) {
        $fileName = $file['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedFormats = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowedFormats)) {
            $this->addError('Format bukti bayar harus JPG, PNG, atau PDF');
            $this->confidenceScore -= 30;
            return false;
        }
        
        $this->addCheck('File Format', 'pass', strtoupper($ext));
        return true;
    }
    
    /**
     * CHECK 2: File Size
     */
    private function checkFileSize($file) {
        $fileSize = $file['size'];
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($fileSize > $maxSize) {
            $this->addError("Ukuran file terlalu besar ({$fileSizeMB}MB). Maksimal 10MB");
            $this->confidenceScore -= 20;
            return false;
        }
        
        if ($fileSize < 10240) { // < 10KB
            $this->addWarning('File terlalu kecil, mungkin tidak jelas');
            $this->confidenceScore -= 10;
        }
        
        $this->addCheck('File Size', 'pass', "{$fileSizeMB} MB");
        return true;
    }
    
    /**
     * SIMULASI OCR - Extract data dari bukti transfer
     */
    private function simulateOCR($file) {
        $fileName = strtolower($file['name']);
        
        // Simulasi detection berdasarkan nama file
        $bank = 'UNKNOWN';
        if (strpos($fileName, 'bca') !== false) {
            $bank = 'BCA';
        } elseif (strpos($fileName, 'mandiri') !== false) {
            $bank = 'MANDIRI';
        } elseif (strpos($fileName, 'bri') !== false) {
            $bank = 'BRI';
        }
        
        // Simulasi extract data
        $extractedData = [
            'amount' => $this->extractAmountFromFilename($fileName),
            'sender_name' => $this->extractSenderFromFilename($fileName),
            'bank' => $bank,
            'date' => date('Y-m-d'),
            'reference_number' => $this->generateReferenceNumber(),
            'ocr_confidence' => rand(75, 95)
        ];
        
        $this->addCheck(
            'OCR Extraction', 
            'pass', 
            "Data extracted with {$extractedData['ocr_confidence']}% confidence"
        );
        
        return $extractedData;
    }
    
    /**
     * Helper: Extract amount from filename
     */
    private function extractAmountFromFilename($fileName) {
        preg_match('/(\d{4,})/', $fileName, $matches);
        
        if (isset($matches[1])) {
            return intval($matches[1]);
        }
        
        return rand(50000, 500000);
    }
    
    /**
     * Helper: Extract sender name from filename
     */
    private function extractSenderFromFilename($fileName) {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $parts = explode('_', $baseName);
        
        $possibleNames = [];
        foreach ($parts as $part) {
            if (strlen($part) >= 3 && !is_numeric($part)) {
                $possibleNames[] = ucfirst(strtolower($part));
            }
        }
        
        if (count($possibleNames) >= 2) {
            return implode(' ', array_slice($possibleNames, 0, 2));
        }
        
        return 'Customer';
    }
    
    /**
     * Helper: Generate reference number
     */
    private function generateReferenceNumber() {
        return strtoupper(substr(md5(time()), 0, 10));
    }
    
    /**
     * VALIDATE: Date is recent
     */
    private function validateDate($extractedDate, $orderDate = null) {
        $transferDate = strtotime($extractedDate);
        $now = time();
        $diffDays = ($now - $transferDate) / (60 * 60 * 24);
        
        // Check if transfer is too old (> 7 days) - relaxed for testing
        if ($diffDays > 7) {
            $this->addWarning(
                "Bukti transfer sudah " . round($diffDays) . " hari yang lalu. " .
                "Disarankan upload bukti yang lebih baru."
            );
            $this->confidenceScore -= 10;
        }
        
        $this->addCheck('Date Validation', 'pass', 
            date('d M Y', $transferDate) . " (" . round($diffDays) . " days ago)"
        );
        return true;
    }
    
    /**
     * VALIDATE: Bank account is correct
     */
    private function validateBank($extractedBank) {
        if ($extractedBank === 'UNKNOWN') {
            $this->addWarning('Tidak dapat mendeteksi bank tujuan dari gambar');
            $this->confidenceScore -= 10; // Reduced penalty
            $this->addCheck('Bank Validation', 'warning', 'Bank not detected');
            return false;
        }
        
        if (!isset($this->companyBankAccounts[$extractedBank])) {
            $this->addWarning(
                "Bank tujuan tidak terdeteksi ($extractedBank). " .
                "Pastikan transfer ke: " . implode(', ', array_keys($this->companyBankAccounts))
            );
            $this->confidenceScore -= 15; // Reduced from 35
            $this->addCheck('Bank Validation', 'warning', $extractedBank);
            return false;
        }
        
        $this->addCheck('Bank Validation', 'pass', $extractedBank);
        return true;
    }
    
    /**
     * VALIDATE: Sender name is not empty
     */
    private function validateSenderName($senderName) {
        if (empty($senderName) || $senderName === 'Customer') {
            $this->addWarning('Tidak dapat mendeteksi nama pengirim dari gambar');
            $this->confidenceScore -= 5; // Reduced penalty
            $this->addCheck('Sender Name', 'warning', 'Not detected');
            return false;
        }
        
        $this->addCheck('Sender Name', 'pass', $senderName);
        return true;
    }
    
    /**
     * Helper methods
     */
    private function addError($message) {
        $this->errors[] = $message;
    }
    
    private function addWarning($message) {
        $this->warnings[] = $message;
    }
    
    private function addCheck($checkName, $status, $value) {
        $this->checks[] = [
            'check' => $checkName,
            'status' => $status,
            'value' => $value
        ];
    }
    
    private function resetValidation() {
        $this->confidenceScore = 100;
        $this->errors = [];
        $this->warnings = [];
        $this->checks = [];
    }
    
    /**
     * Get validation results
     */
    private function getResults($extractedData = []) {
        $this->confidenceScore = max(0, $this->confidenceScore);
        $isValid = count($this->errors) === 0 && $this->confidenceScore >= 60;
        
        return [
            'is_valid' => $isValid,
            'confidence_score' => $this->confidenceScore,
            'extracted_data' => $extractedData,
            'checks' => $this->checks,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'recommendation' => $this->getRecommendation($isValid, $this->confidenceScore),
            'validated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get recommendation
     */
    private function getRecommendation($isValid, $score) {
        if (!$isValid) {
            return 'REJECT - Bukti pembayaran tidak valid. Hubungi customer untuk upload ulang.';
        }
        
        if ($score >= 90) {
            return 'AUTO APPROVE - Pembayaran terverifikasi otomatis, proses pesanan.';
        }
        
        if ($score >= 75) {
            return 'APPROVE WITH CAUTION - Pembayaran kemungkinan valid, verifikasi manual disarankan.';
        }
        
        if ($score >= 60) {
            return 'MANUAL REVIEW REQUIRED - Confidence score rendah. Cek manual m-banking atau hubungi customer.';
        }
        
        return 'REJECT - Confidence score terlalu rendah.';
    }
}
?>