<?php
/**
 * FileValidator.php
 * Advanced File Validation untuk PT Besar
 * Location: api/validators/FileValidator.php
 */

class FileValidator {
    
    private $allowedExtensions = ['pdf', 'ai', 'psd', 'jpg', 'jpeg', 'png', 'cdr', 'eps', 'svg'];
    private $allowedMimeTypes = [
        'application/pdf',
        'application/postscript',
        'image/vnd.adobe.photoshop',
        'image/jpeg',
        'image/png',
        'image/svg+xml',
        'application/x-coreldraw',
        'application/octet-stream',
        'application/illustrator',
        'image/x-eps'
    ];
    
    private $maxFileSize = 52428800; // 50MB in bytes
    private $minImageWidth = 1000;   // Minimum width untuk print quality
    private $minImageHeight = 1000;  // Minimum height untuk print quality
    
    private $validationResults = [];
    private $errors = [];
    private $warnings = [];
    private $confidenceScore = 100;
    
    /**
     * Main validation method
     */
    public function validate($file) {
        $this->resetValidation();
        
        // Check if file exists
        if (!isset($file) || !isset($file['tmp_name'])) {
            $this->addError('File tidak ditemukan atau upload gagal');
            return $this->getResults();
        }
        
        // Run all validation checks
        $this->checkUploadError($file);
        $this->checkFileSize($file);
        $this->checkFileExtension($file);
        $this->checkMimeType($file);
        $this->checkFileIntegrity($file);
        $this->checkImageResolution($file);
        $this->checkFileCorruption($file);
        
        return $this->getResults();
    }
    
    /**
     * CHECK 1: Upload Errors
     */
    private function checkUploadError($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi batas server)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi batas form)',
                UPLOAD_ERR_PARTIAL => 'Upload tidak lengkap, coba lagi',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                UPLOAD_ERR_NO_TMP_DIR => 'Error server: direktori temporary tidak ada',
                UPLOAD_ERR_CANT_WRITE => 'Error server: tidak bisa menulis file',
                UPLOAD_ERR_EXTENSION => 'Upload dibatalkan oleh extension',
            ];
            
            $errorMsg = $errorMessages[$file['error']] ?? 'Error upload tidak diketahui';
            $this->addError($errorMsg);
            $this->confidenceScore -= 50;
            return false;
        }
        
        $this->addCheck('Upload Status', 'pass', 'File berhasil diupload');
        return true;
    }
    
    /**
     * CHECK 2: File Size
     */
    private function checkFileSize($file) {
        $fileSize = $file['size'];
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        
        if ($fileSize > $this->maxFileSize) {
            $this->addError("Ukuran file terlalu besar ({$fileSizeMB}MB). Maksimal 50MB");
            $this->confidenceScore -= 30;
            return false;
        }
        
        // Warning untuk file yang sangat kecil
        if ($fileSize < 10240) { // < 10KB
            $this->addWarning("File sangat kecil ({$fileSizeMB}MB). Pastikan kualitas cukup untuk cetak");
            $this->confidenceScore -= 5;
        }
        
        $this->addCheck('File Size', 'pass', "{$fileSizeMB} MB");
        return true;
    }
    
    /**
     * CHECK 3: File Extension
     */
    private function checkFileExtension($file) {
        $fileName = $file['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $this->allowedExtensions)) {
            $this->addError(
                "Format file tidak didukung (.$fileExtension). " .
                "Gunakan: " . implode(', ', $this->allowedExtensions)
            );
            $this->confidenceScore -= 40;
            return false;
        }
        
        $this->addCheck('File Format', 'pass', strtoupper($fileExtension));
        return true;
    }
    
    /**
     * CHECK 4: MIME Type
     */
    private function checkMimeType($file) {
        $filePath = $file['tmp_name'];
        
        if (!file_exists($filePath)) {
            $this->addError('File temporary tidak ditemukan');
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            $this->addWarning("MIME type tidak standar: {$mimeType}. File mungkin bermasalah");
            $this->confidenceScore -= 10;
        } else {
            $this->addCheck('MIME Type', 'pass', $mimeType);
        }
        
        return true;
    }
    
    /**
     * CHECK 5: File Integrity
     */
    private function checkFileIntegrity($file) {
        $filePath = $file['tmp_name'];
        
        if (!is_readable($filePath)) {
            $this->addError('File tidak dapat dibaca atau corrupt');
            $this->confidenceScore -= 40;
            return false;
        }
        
        // Check if file is actually a file and not empty
        if (filesize($filePath) === 0) {
            $this->addError('File kosong (0 bytes)');
            $this->confidenceScore -= 40;
            return false;
        }
        
        $this->addCheck('File Integrity', 'pass', 'File dapat dibaca');
        return true;
    }
    
    /**
     * CHECK 6: Image Resolution (untuk JPG, PNG)
     */
    private function checkImageResolution($file) {
        $fileName = $file['name'];
        $filePath = $file['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Only check for image files
        if (!in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $this->addCheck('Image Resolution', 'skip', 'Bukan file gambar');
            return true;
        }
        
        $imageInfo = @getimagesize($filePath);
        
        if (!$imageInfo) {
            $this->addWarning('Tidak dapat membaca informasi gambar. File mungkin corrupt');
            $this->confidenceScore -= 15;
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Calculate approximate DPI for A4 size
        // A4 at 300 DPI = 2480x3508 pixels
        $estimatedDPI = min($width / 8.27, $height / 11.69) * 2.54; // rough calculation
        
        if ($width < $this->minImageWidth || $height < $this->minImageHeight) {
            $this->addWarning(
                "Resolusi rendah ({$width}x{$height}px, ~" . round($estimatedDPI) . " DPI). " .
                "Hasil cetak mungkin kurang tajam. Minimum rekomendasi: 1000x1000px"
            );
            $this->confidenceScore -= 15;
            $this->addCheck('Image Resolution', 'warning', "{$width}x{$height}px");
        } else {
            $this->addCheck('Image Resolution', 'pass', "{$width}x{$height}px (~" . round($estimatedDPI) . " DPI)");
        }
        
        return true;
    }
    
    /**
     * CHECK 7: File Corruption (advanced)
     */
    private function checkFileCorruption($file) {
        $fileName = $file['name'];
        $filePath = $file['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check PDF files
        if ($fileExtension === 'pdf') {
            $fileContent = file_get_contents($filePath);
            
            // Check PDF header
            if (strpos($fileContent, '%PDF') !== 0) {
                $this->addError('File PDF corrupt atau tidak valid (header hilang)');
                $this->confidenceScore -= 30;
                return false;
            }
            
            // Check PDF footer
            if (strpos($fileContent, '%%EOF') === false) {
                $this->addWarning('File PDF mungkin tidak lengkap (footer hilang)');
                $this->confidenceScore -= 10;
            }
            
            $this->addCheck('PDF Validation', 'pass', 'PDF structure valid');
        }
        
        // Check image files
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $imageInfo = @getimagesize($filePath);
            
            if (!$imageInfo) {
                $this->addError('File gambar corrupt atau tidak valid');
                $this->confidenceScore -= 30;
                return false;
            }
            
            $this->addCheck('Image Validation', 'pass', 'Image structure valid');
        }
        
        return true;
    }
    
    /**
     * Helper: Add error
     */
    private function addError($message) {
        $this->errors[] = $message;
    }
    
    /**
     * Helper: Add warning
     */
    private function addWarning($message) {
        $this->warnings[] = $message;
    }
    
    /**
     * Helper: Add check result
     */
    private function addCheck($checkName, $status, $value) {
        $this->validationResults[] = [
            'check' => $checkName,
            'status' => $status,
            'value' => $value
        ];
    }
    
    /**
     * Reset validation state
     */
    private function resetValidation() {
        $this->validationResults = [];
        $this->errors = [];
        $this->warnings = [];
        $this->confidenceScore = 100;
    }
    
    /**
     * Get final validation results
     */
    private function getResults() {
        // Ensure confidence score doesn't go below 0
        $this->confidenceScore = max(0, $this->confidenceScore);
        
        $isValid = count($this->errors) === 0 && $this->confidenceScore >= 60;
        
        return [
            'is_valid' => $isValid,
            'confidence_score' => $this->confidenceScore,
            'checks' => $this->validationResults,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'recommendation' => $this->getRecommendation($isValid, $this->confidenceScore),
            'validated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get recommendation based on validation result
     */
    private function getRecommendation($isValid, $score) {
        if (!$isValid) {
            return 'REJECT - File tidak memenuhi syarat. Hubungi customer untuk upload ulang.';
        }
        
        if ($score >= 90) {
            return 'AUTO APPROVE - File berkualitas tinggi, siap produksi.';
        }
        
        if ($score >= 75) {
            return 'APPROVE WITH CAUTION - File OK tapi ada minor issues. Review manual disarankan.';
        }
        
        if ($score >= 60) {
            return 'MANUAL REVIEW REQUIRED - File marginal. Perlu konfirmasi dengan customer.';
        }
        
        return 'REJECT - Confidence score terlalu rendah.';
    }
    
    /**
     * Get detailed file information
     */
    public function getFileInfo($file) {
        $filePath = $file['tmp_name'];
        $fileName = $file['name'];
        
        return [
            'filename' => $fileName,
            'size' => $file['size'],
            'size_mb' => round($file['size'] / 1024 / 1024, 2),
            'extension' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
            'mime_type' => mime_content_type($filePath),
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }
}
?>